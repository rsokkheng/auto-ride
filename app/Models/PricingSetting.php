<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSetting extends Model
{
    protected $fillable = ['key', 'value', 'label', 'description'];

    /** Get a setting value by key, with optional default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /** Set a setting value by key. */
    public static function set(string $key, mixed $value, ?string $label = null): void
    {
        $existing = static::where('key', $key)->first();
        if ($existing) {
            $existing->update(['value' => (string) $value]);
        } else {
            static::create([
                'key'   => $key,
                'value' => (string) $value,
                'label' => $label ?? ucwords(str_replace('_', ' ', $key)),
            ]);
        }
    }
}
