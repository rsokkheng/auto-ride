<?php

namespace App\Support;

/**
 * Helpers for the three content languages: English (en), Khmer (kh) and
 * Chinese (zh). Column suffixes use en/kh/zh; Laravel's Khmer locale is "km".
 */
final class Localized
{
    public const SUFFIXES = ['en', 'kh', 'zh'];

    /** Column suffix → Laravel locale (lang/ folder name). */
    public const LOCALES = ['en' => 'en', 'kh' => 'km', 'zh' => 'zh'];

    /** Any locale code (en, km, kh, zh, null = request locale) → column suffix. */
    public static function suffix(?string $locale = null): string
    {
        return match (strtolower((string) ($locale ?? app()->getLocale()))) {
            'km', 'kh' => 'kh',
            'zh', 'zh-cn', 'zh_cn', 'cn' => 'zh',
            default    => 'en',
        };
    }

    /**
     * Validation rules for localized fields. English may arrive as `{base}_en`
     * or the legacy `{base}`; Khmer and Chinese are optional.
     *
     *   Localized::rules(['title' => 'string|max:255'], required: ['title'])
     *
     * @param  array<string,string>  $fields    base column => rules (without required/nullable)
     * @param  string[]              $required  base columns that need an English value
     * @param  bool                  $partial   PATCH-style: only validate fields that are sent
     */
    public static function rules(array $fields, array $required = [], bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';
        $rules  = [];

        foreach ($fields as $base => $rule) {
            $isRequired = in_array($base, $required, true);

            $rules[$base]        = $prefix . ($isRequired ? "required_without:{$base}_en|" : '') . 'nullable|' . $rule;
            $rules["{$base}_en"] = $prefix . ($isRequired ? "required_without:{$base}|" : '') . 'nullable|' . $rule;
            $rules["{$base}_kh"] = $prefix . 'nullable|' . $rule;
            $rules["{$base}_zh"] = $prefix . 'nullable|' . $rule;
        }

        return $rules;
    }
}
