<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of the passenger cancellation policy. A tier applies from
 * `from_minute` minutes after its stage started until the next tier of the
 * same stage begins. Stages start at: request created / driver accepted /
 * driver arrived.
 */
class CancellationPolicyTier extends Model
{
    const STAGE_BEFORE_ACCEPT = 'before_accept';
    const STAGE_AFTER_ACCEPT  = 'after_accept';
    const STAGE_AFTER_ARRIVAL = 'after_arrival';

    const STAGES = [
        self::STAGE_BEFORE_ACCEPT,
        self::STAGE_AFTER_ACCEPT,
        self::STAGE_AFTER_ARRIVAL,
    ];

    protected $fillable = ['stage', 'from_minute', 'fee_khr'];

    protected $casts = [
        'from_minute' => 'integer',
        'fee_khr'     => 'integer',
    ];

    /**
     * Full policy in display order. A stage with no configured tiers is
     * returned as a single free tier so clients always see all three stages.
     *
     * @return array<int, array{stage:string, from_minute:int, to_minute:?int, fee_khr:int}>
     */
    public static function policy(): array
    {
        $byStage = static::orderBy('from_minute')->get()->groupBy('stage');
        $out     = [];

        foreach (self::STAGES as $stage) {
            $tiers = $byStage->get($stage, collect())->values();
            if ($tiers->isEmpty() || $tiers->first()->from_minute > 0) {
                $tiers->prepend(new static(['stage' => $stage, 'from_minute' => 0, 'fee_khr' => 0]));
            }
            foreach ($tiers as $i => $tier) {
                $out[] = [
                    'stage'       => $stage,
                    'from_minute' => $tier->from_minute,
                    'to_minute'   => $tiers->get($i + 1)?->from_minute,
                    'fee_khr'     => $tier->fee_khr,
                ];
            }
        }

        return $out;
    }

    /** Fee owed if the passenger cancels this ride right now. */
    public static function feeFor(Ride $ride): int
    {
        [$stage, $since] = match ($ride->status) {
            Ride::STATUS_DRIVER_ARRIVED => [self::STAGE_AFTER_ARRIVAL, $ride->driver_arrived_at],
            Ride::STATUS_ACCEPTED       => [self::STAGE_AFTER_ACCEPT, $ride->accepted_at],
            default                     => [self::STAGE_BEFORE_ACCEPT, $ride->created_at],
        };

        $elapsed = $since instanceof Carbon ? (int) $since->diffInMinutes(now()) : 0;

        return (int) (static::where('stage', $stage)
            ->where('from_minute', '<=', $elapsed)
            ->orderByDesc('from_minute')
            ->value('fee_khr') ?? 0);
    }
}
