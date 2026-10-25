<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * One message in en / kh / zh — used for push notifications so each user gets
 * it in their own language and the in-app list can show any of the three.
 */
final class LocalizedText implements Stringable
{
    /** @param array{en: string, kh: string, zh: string} $values */
    private function __construct(public readonly array $values) {}

    /**
     * Translate a lang key (lang/{en,km,zh}/...) into all three languages.
     * Replacement values may themselves be LocalizedText (e.g. a product title).
     */
    public static function trans(string $key, array $replace = []): self
    {
        $values = [];
        foreach (Localized::LOCALES as $suffix => $locale) {
            $r = array_map(fn ($v) => $v instanceof self ? $v->in($suffix) : (string) $v, $replace);
            $values[$suffix] = (string) __($key, $r, $locale);
        }

        return new self($values);
    }

    /** Same text in every language (user-typed content, proper nouns). */
    public static function plain(string $text): self
    {
        return new self(['en' => $text, 'kh' => $text, 'zh' => $text]);
    }

    /** A model's localized column (HasLocalizedName) in all three languages. */
    public static function fromModel(Model $model, string $column): self
    {
        $t = $model->translations($column);

        return new self(array_map(fn ($v) => (string) $v, $t));
    }

    public static function wrap(string|self $text): self
    {
        return $text instanceof self ? $text : self::plain($text);
    }

    public function in(?string $locale = null): string
    {
        return $this->values[Localized::suffix($locale)];
    }

    /** Text in the user's saved language (falls back to the request locale). */
    public function for(?User $user): string
    {
        return $this->in($user?->preferredLocale());
    }

    public function __toString(): string
    {
        return $this->in();
    }
}
