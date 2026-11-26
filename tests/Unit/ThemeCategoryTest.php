<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes\Tests\Unit;

use OpenColibri\Starter\Themes\ThemeCategory;
use OpenColibri\Starter\Themes\ThemePreview;
use PHPUnit\Framework\TestCase;

final class ThemeCategoryTest extends TestCase
{
    public function testEveryThemeHasOneCategory(): void
    {
        $themes = array_map(basename(...), glob(\dirname(ThemePreview::dir()) . '/resources/*', \GLOB_ONLYDIR) ?: []);

        static::assertNotEmpty($themes);

        foreach ($themes as $theme) {
            static::assertNotNull(ThemeCategory::of($theme), \sprintf('The "%s" theme has no category: add it to %s, in the order of docs/themes/index.md.', $theme, ThemeCategory::class));
        }

        $categorized = array_merge(...array_map(static fn(ThemeCategory $category): array => $category->themes(), ThemeCategory::cases()));
        static::assertSame(array_unique($categorized), $categorized, 'A theme is in two categories.');
        static::assertEqualsCanonicalizing($themes, $categorized);
    }

    public function testItSortsTheThemesByCategoryThenTheOthers(): void
    {
        static::assertSame(['canvas', 'tech', 'drive', 'arena', 'custom'], ThemeCategory::sort(['custom', 'arena', 'drive', 'tech', 'canvas']));
        static::assertNull(ThemeCategory::of('custom'));
        static::assertSame(ThemeCategory::Food, ThemeCategory::of('cellar'));
    }
}
