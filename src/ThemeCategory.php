<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes;

/**
 * The categories of the themes, for theme pickers: the sections of docs/themes/index.md, in the same order, each
 * listing its themes in the order of the documentation. Keep both in sync when adding a theme.
 */
enum ThemeCategory: string
{
    case General = 'general';
    case Gear = 'gear';
    case Food = 'food';
    case Home = 'home';
    case Culture = 'culture';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General purpose',
            self::Gear => 'Gear and supplies',
            self::Food => 'Food and drink',
            self::Home => 'Home, beauty and pets',
            self::Culture => 'Games, music and culture',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::General => 'Brand looks that suit any catalogue: pick one for its style.',
            self::Gear => 'Electronics, outdoor equipment, car parts and trade supplies, built around specs and references.',
            self::Food => 'Grocery, wine, coffee and tea shops, with their own typography and homepage.',
            self::Home => 'Warm, lifestyle-led stores for the home, the bathroom shelf and the pets.',
            self::Culture => 'Video games, records, books and entertainment, with striking heroes.',
        };
    }

    /**
     * @return list<string> the themes of the category, in the order of the documentation
     */
    public function themes(): array
    {
        return match ($this) {
            self::General => ['lagoon', 'aurora', 'canvas', 'blush', 'volt', 'prompt'],
            self::Gear => ['depot', 'torque', 'tech', 'summit'],
            self::Food => ['drive', 'cellar', 'roastery', 'teahouse'],
            self::Home => ['hearth', 'bloom', 'paws'],
            self::Culture => ['culture', 'groove', 'arena', 'retro_gaming'],
        };
    }

    /**
     * @return self|null the category of the theme, or null for a theme of no category (a custom one)
     */
    public static function of(string $theme): ?self
    {
        foreach (self::cases() as $category) {
            if (\in_array($theme, $category->themes(), true)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @param list<string> $themes
     *
     * @return list<string> the themes by category, in the order of the documentation, then those of no category
     */
    public static function sort(array $themes): array
    {
        $sorted = [];

        foreach (self::cases() as $category) {
            foreach ($category->themes() as $theme) {
                if (\in_array($theme, $themes, true)) {
                    $sorted[] = $theme;
                }
            }
        }

        return [...$sorted, ...array_values(array_diff($themes, $sorted))];
    }
}
