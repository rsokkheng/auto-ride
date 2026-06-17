<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class AccessibilityController extends ApiController
{
    private const DEFAULTS = [
        'font_size'         => 'medium',  // small | medium | large | x-large
        'high_contrast'     => false,
        'reduce_motion'     => false,
        'screen_reader'     => false,
        'voice_prompts'     => false,
        'large_tap_targets' => false,
        'haptic_feedback'   => true,
        'dyslexia_font'     => false,
        'language'          => 'en',
    ];

    /** GET /v1/accessibility */
    public function show(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $settings = array_merge(self::DEFAULTS, $user->accessibility_settings ?? []);

        return $this->success($settings);
    }

    /** PUT /v1/accessibility */
    public function update(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'font_size'         => 'nullable|in:small,medium,large,x-large',
            'high_contrast'     => 'nullable|boolean',
            'reduce_motion'     => 'nullable|boolean',
            'screen_reader'     => 'nullable|boolean',
            'voice_prompts'     => 'nullable|boolean',
            'large_tap_targets' => 'nullable|boolean',
            'haptic_feedback'   => 'nullable|boolean',
            'dyslexia_font'     => 'nullable|boolean',
            'language'          => 'nullable|string|max:10',
        ]);

        $existing = $user->accessibility_settings ?? [];
        $merged   = array_merge($existing, array_filter($data, fn ($v) => $v !== null));

        $user->update(['accessibility_settings' => $merged]);

        return $this->success(array_merge(self::DEFAULTS, $merged), 'Accessibility settings updated.');
    }
}
