<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes\Tests\Unit;

use OpenColibri\Starter\Themes\ThemePreview;
use PHPUnit\Framework\TestCase;

final class ThemePreviewTest extends TestCase
{
    public function testEveryThemeHasALightAndADarkThumbnail(): void
    {
        $themes = array_map(basename(...), glob(\dirname(ThemePreview::dir()) . '/resources/*', \GLOB_ONLYDIR) ?: []);

        static::assertNotEmpty($themes);

        foreach ($themes as $theme) {
            foreach (ThemePreview::SCHEMES as $scheme) {
                static::assertNotNull(ThemePreview::path($theme, $scheme), \sprintf('The "%s" theme has no %s thumbnail: run "node scripts/capture-theme-screenshots.mjs %s".', $theme, $scheme, $theme));
            }
        }
    }

    public function testItHasNoThumbnailForAnUnknownThemeOrScheme(): void
    {
        static::assertNull(ThemePreview::path('default'));
        static::assertNull(ThemePreview::path('../canvas'));
        static::assertNull(ThemePreview::path('canvas', 'sepia'));
    }
}
