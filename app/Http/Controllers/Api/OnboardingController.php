<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class OnboardingController extends ApiController
{
    private const STEPS = [
        'welcome',
        'profile_setup',
        'first_ride',
        'wallet_intro',
        'safety_features',
        'loyalty_intro',
    ];

    /** GET /v1/onboarding — get current onboarding state */
    public function show(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $completed = $user->onboarding_steps ?? [];

        $steps = array_map(fn ($step) => [
            'key'       => $step,
            'completed' => in_array($step, $completed, true),
        ], self::STEPS);

        return $this->success([
            'completed_at' => $user->onboarding_completed_at?->toISOString(),
            'is_complete'  => $user->onboarding_completed_at !== null,
            'progress'     => count($completed) . '/' . count(self::STEPS),
            'steps'        => $steps,
        ]);
    }

    /** POST /v1/onboarding/step — mark a step as done. Body: { step } */
    public function completeStep(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate(['step' => 'required|in:' . implode(',', self::STEPS)]);

        $completed = $user->onboarding_steps ?? [];

        if (! in_array($data['step'], $completed, true)) {
            $completed[] = $data['step'];
            $user->update(['onboarding_steps' => $completed]);
        }

        $allDone = count(array_intersect(self::STEPS, $completed)) === count(self::STEPS);

        if ($allDone && ! $user->onboarding_completed_at) {
            $user->update(['onboarding_completed_at' => now()]);
        }

        return $this->success([
            'step'         => $data['step'],
            'is_complete'  => $allDone,
            'completed_at' => $user->fresh()->onboarding_completed_at?->toISOString(),
        ]);
    }

    /** POST /v1/onboarding/skip — skip all remaining onboarding */
    public function skip(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $user->update([
            'onboarding_steps'        => self::STEPS,
            'onboarding_completed_at' => now(),
        ]);

        return $this->success(null, 'Onboarding skipped.');
    }
}
