<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves stored timestamps from UTC to Asia/Phnom_Penh, to go with
 * app.timezone = 'Asia/Phnom_Penh' and the MySQL session time_zone = '+07:00'
 * pinned in config/database.php. After this, every existing row reads back as
 * the same instant it always meant.
 *
 * Until now PHP wrote UTC wall-clock strings, and MySQL interpreted them in its
 * own server time zone (the session default). So per column type:
 *
 *   DATETIME  — stores the string verbatim: +7h.
 *   TIMESTAMP — stored internally as UTC, converted via the session time zone.
 *               The new +07:00 session already adds 7h on read; what's left
 *               to correct is the server's own offset, which the old session
 *               applied when the value was written. 0 when the server runs in
 *               UTC, so large tables usually need no rewrite at all.
 *   TIMESTAMP filled by MySQL itself (useCurrent) — already the correct
 *               instant: untouched.
 *
 * Run with the app in maintenance mode and queue workers stopped.
 */
return new class extends Migration
{
    // Asia/Phnom_Penh has no DST.
    private const LOCAL_OFFSET_MINUTES = 420;

    // Only ever set by MySQL's own CURRENT_TIMESTAMP default.
    private const DB_GENERATED = ['failed_jobs.failed_at'];

    public function up(): void
    {
        $this->shift(1);
    }

    public function down(): void
    {
        $this->shift(-1);
    }

    private function shift(int $direction): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $serverOffset = $this->serverOffsetMinutes();

        DB::transaction(function () use ($direction, $serverOffset) {
            foreach ($this->columns() as $col) {
                $minutes = match (true) {
                    $col->type === 'datetime'                                   => self::LOCAL_OFFSET_MINUTES,
                    in_array("{$col->tbl}.{$col->col}", self::DB_GENERATED, true) => 0,
                    default                                                     => $serverOffset,
                };

                if ($minutes === 0) {
                    continue;
                }

                DB::update(sprintf(
                    'UPDATE `%1$s` SET `%2$s` = `%2$s` + INTERVAL %3$d MINUTE WHERE `%2$s` IS NOT NULL',
                    $col->tbl, $col->col, $direction * $minutes,
                ));
            }
        });
    }

    /** @return array<object{tbl: string, col: string, type: string}> */
    private function columns(): array
    {
        return DB::select(
            "SELECT c.table_name AS tbl, c.column_name AS col, c.data_type AS type
               FROM information_schema.columns c
               JOIN information_schema.tables t
                 ON t.table_schema = c.table_schema AND t.table_name = c.table_name
              WHERE c.table_schema = DATABASE()
                AND t.table_type = 'BASE TABLE'
                AND c.data_type IN ('datetime', 'timestamp')
              ORDER BY c.table_name, c.column_name"
        );
    }

    /**
     * UTC offset (minutes) of the MySQL server's own time zone — the session
     * zone every connection used before config/database.php pinned '+07:00'.
     */
    private function serverOffsetMinutes(): int
    {
        $session = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;

        DB::statement('SET time_zone = @@global.time_zone');
        $minutes = (int) DB::selectOne('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), NOW()) AS m')->m;
        DB::statement('SET time_zone = ' . DB::getPdo()->quote($session));

        return (int) (round($minutes / 15) * 15);
    }
};
