<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes;

/**
 * Thumbnails of the themes, for theme pickers: the first screen of the homepage of each theme, in each color scheme
 * (previews/<theme>-<scheme>.webp, 480x300). They are made by scripts/capture-theme-screenshots.mjs with the
 * documentation screenshots.
 */
final class ThemePreview
{
    public const SCHEMES = ['light', 'dark'];

    public static function dir(): string
    {
        return \dirname(__DIR__) . '/previews';
    }

    /**
     * @return string|null the thumbnail file, or null when the theme has none
     */
    public static function path(string $theme, string $scheme = 'light'): ?string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $theme) || !\in_array($scheme, self::SCHEMES, true)) {
            return null;
        }

        $path = self::dir() . '/' . $theme . '-' . $scheme . '.webp';

        return is_file($path) ? $path : null;
    }
}
