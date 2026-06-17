<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class StreakService
{
    private const MILESTONES = [3, 7, 14, 30, 60, 100];

    public function recordTrip(User $user): void
    {
        $today     = Carbon::today()->toDateString();
        $lastDate  = $user->last_trip_date?->toDateString();

        if ($lastDate === $today) {
            return; // Already recorded today
        }

        $yesterday = Carbon::yesterday()->toDateString();

        $newStreak = ($lastDate === $yesterday)
            ? $user->current_streak + 1
            : 1;

        $user->update([
            'current_streak' => $newStreak,
            'longest_streak' => max($user->longest_streak, $newStreak),
            'last_trip_date' => $today,
        ]);
    }

    public function summary(User $user): array
    {
        $current    = $user->current_streak ?? 0;
        $longest    = $user->longest_streak ?? 0;
        $next       = collect(self::MILESTONES)->first(fn ($m) => $m > $current);
        $milestones = array_map(fn ($m) => [
            'days'      => $m,
            'reached'   => $longest >= $m,
            'is_next'   => $m === $next,
        ], self::MILESTONES);

        return [
            'current_streak' => $current,
            'longest_streak' => $longest,
            'last_trip_date' => $user->last_trip_date?->toDateString(),
            'next_milestone' => $next,
            'milestones'     => $milestones,
        ];
    }
}
