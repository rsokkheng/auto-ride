<?php

namespace App\Traits;

use App\Support\Localized;

/**
 * en / kh / zh columns for user-facing text.
 *
 * A model lists its translatable base columns in `protected array $localized`
 * (e.g. ['title', 'description']); each has `{base}_en`, `{base}_kh` and
 * `{base}_zh` siblings. Reading `$model->title` — or serializing the model —
 * returns the value for the request locale, falling back to `_en`, then to the
 * base column. On save the base column and `_en` are kept in sync, so clients
 * that only send `title` keep working and `title_en` never goes stale.
 *
 * Models without a `name` column (name_en/name_kh/name_zh only) still get a
 * virtual localized `name` via getNameAttribute().
 */
trait HasLocalizedName
{
    public static function bootHasLocalizedName(): void
    {
        static::saving(function (self $model) {
            foreach ($model->localizedColumns() as $base) {
                $en     = "{$base}_en";
                $baseV  = $model->attributes[$base] ?? null;
                $enV    = $model->attributes[$en] ?? null;

                if (filled($enV) && (blank($baseV) || ($model->isDirty($en) && ! $model->isDirty($base)))) {
                    $model->attributes[$base] = $enV;
                } elseif ($model->isDirty($base) && ! $model->isDirty($en)) {
                    $model->attributes[$en] = $baseV;
                }
            }
        });
    }

    public function initializeHasLocalizedName(): void
    {
        $casts = $this->getCasts();
        foreach ($this->localizedColumns() as $base) {
            if ($this->fillable && in_array($base, $this->fillable, true)) {
                $this->mergeFillable(["{$base}_en", "{$base}_kh", "{$base}_zh"]);
            }
            if (isset($casts[$base])) {
                foreach (['en', 'kh', 'zh'] as $suffix) {
                    $this->casts["{$base}_{$suffix}"] ??= $casts[$base];
                }
            }
        }
    }

    /** @return string[] base columns that have _en / _kh / _zh variants */
    public function localizedColumns(): array
    {
        return property_exists($this, 'localized') ? $this->localized : [];
    }

    public function getAttribute($key)
    {
        if (is_string($key) && in_array($key, $this->localizedColumns(), true)) {
            return $this->translated($key);
        }

        return parent::getAttribute($key);
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ($this->localizedColumns() as $base) {
            if (array_key_exists($base, $attributes)) {
                $attributes[$base] = $this->translated($base);
            }
        }

        return $attributes;
    }

    /** Value of a localized column in the given locale (default: request locale). */
    public function translated(string $base, ?string $locale = null): mixed
    {
        $suffix = Localized::suffix($locale);

        foreach (array_unique(["{$base}_{$suffix}", "{$base}_en"]) as $column) {
            if (array_key_exists($column, $this->attributes)) {
                $value = parent::getAttribute($column);
                if (filled($value)) {
                    return $value;
                }
            }
        }

        return parent::getAttribute($base);
    }

    /** ['en' => …, 'kh' => …, 'zh' => …] for a localized column. */
    public function translations(string $base): array
    {
        return [
            'en' => $this->translated($base, 'en'),
            'kh' => $this->translated($base, 'kh'),
            'zh' => $this->translated($base, 'zh'),
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->resolveLocalizedName($this->localizedNameColumn());
    }

    protected function localizedNameColumn(): string
    {
        return 'name';
    }

    protected function resolveLocalizedName(string $baseColumn): ?string
    {
        $suffix = Localized::suffix();

        $candidates = array_unique([
            "{$baseColumn}_{$suffix}",
            "{$baseColumn}_en",
            $baseColumn,
        ]);

        foreach ($candidates as $column) {
            if (! empty($this->attributes[$column] ?? null)) {
                return $this->attributes[$column];
            }
        }

        return null;
    }
}
