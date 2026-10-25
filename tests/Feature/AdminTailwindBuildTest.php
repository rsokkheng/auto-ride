<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * public/build is committed (production deploys with a plain git pull and no
 * Node), so a `tw:` class added to an admin view without re-running
 * `npm run build` would silently do nothing in production. This catches that.
 */
class AdminTailwindBuildTest extends TestCase
{
    public function test_committed_build_contains_every_tw_class_used_in_admin_views(): void
    {
        $manifest = public_path('build/manifest.json');
        $this->assertFileExists($manifest, 'public/build is missing — run `npm run build` and commit it.');

        $entry = json_decode(File::get($manifest), true)['resources/css/admin.css']['file'] ?? null;
        $this->assertNotNull($entry, 'resources/css/admin.css is not in the Vite manifest — run `npm run build`.');
        $css = File::get(public_path('build/' . $entry));

        $classes = collect(File::allFiles(resource_path('views/admin')))
            ->flatMap(function ($file) {
                preg_match_all('/class="([^"]*)"/', $file->getContents(), $m);
                return collect($m[1])->flatMap(fn ($attr) => preg_split('/\s+/', $attr));
            })
            ->filter(fn ($c) => str_starts_with($c, 'tw:') && ! str_contains($c, '{'))
            ->unique();

        $missing = $classes->reject(fn ($c) => str_contains($css, '.' . addcslashes($c, ':[].%/#()')));

        $this->assertTrue($missing->isEmpty(),
            "Tailwind classes missing from the committed build — run `npm run build` and commit public/build:\n  "
            . $missing->implode("\n  "));
    }
}
