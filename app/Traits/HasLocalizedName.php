<?php

namespace App\Traits;

trait HasLocalizedName
{
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
        $locale = app()->getLocale();
        $suffix = match ($locale) {
            'km', 'kh' => 'kh',
            'zh'       => 'zh',
            default    => 'en',
        };

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
