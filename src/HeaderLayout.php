<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes;

use OpenColibri\Starter\Core\App;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

use function Castor\fs;

/**
 * Header layout options shared by every theme: where the category menu goes and whether the header shows a search.
 *
 * Menu placements:
 *  - "inline": in the logo row, between the logo and the account links;
 *  - "below": on a row of its own under the logo row, inside the page container, under a hairline;
 *  - "bar": in a full-width band under the logo row.
 *
 * Search placements: "none", "inline" (in the logo row), "below" (on a row of its own under the logo row) or "icon" (a
 * magnifier in the logo row that opens the search in a dropdown).
 *
 * The options are hook configuration: the "themes_header_layout" hookable adds header-menu--<menu> and
 * header-search--<search> classes to the page wrapper (sylius_shop.base#body_classes), styled once for every theme by
 * _header-layout.scss, and the "themes_header_search" hookable renders the search slot. With the theme-bundle target,
 * the hooks are prefixed by the theme, so each theme keeps its own options.
 *
 * Every theme installer calls install(), every theme remover calls remove(), like ColorScheme.
 */
final class HeaderLayout
{
    public const MENU_PLACEMENTS = ['inline', 'below', 'bar'];

    public const SEARCH_PLACEMENTS = ['none', 'inline', 'below', 'icon'];

    public const HOOKS_FILE = 'config/sylius/twig_hooks/shop/base/header_layout.php';

    private const DEFAULT = ['menu' => 'inline', 'search' => 'none'];

    /** The layout each theme was designed with, when it is not the default one. */
    private const THEME_DEFAULTS = [
        'culture' => ['menu' => 'below'],
        'depot' => ['menu' => 'below'],
        'drive' => ['menu' => 'bar', 'search' => 'inline'],
        'torque' => ['menu' => 'below', 'search' => 'inline'],
    ];

    /** Themes whose header only supports some menu placements. */
    private const THEME_MENU_PLACEMENTS = [
        // The aisles bar is a navbar of its own, with a title and a promotion link.
        'drive' => ['bar'],
    ];

    /** @var array{menu?: string, search?: string} */
    private static array $options = [];

    /**
     * The options of the next install(), from the "castor sylius:theme:setup" options (null: the theme default).
     */
    public static function configure(?string $menu, ?string $search): void
    {
        self::$options = array_filter(['menu' => $menu, 'search' => $search], static fn(?string $value): bool => null !== $value);
    }

    /**
     * @return array{menu?: string, search?: string}
     */
    public static function options(): array
    {
        return self::$options;
    }

    /**
     * @return list<string> the errors of the options for this theme
     */
    public static function validate(string $theme, ?string $menu, ?string $search): array
    {
        $errors = [];
        $menuPlacements = self::menuPlacements($theme);

        if (null !== $menu && !\in_array($menu, $menuPlacements, true)) {
            $errors[] = \sprintf('The "%s" theme does not support the "%s" menu placement: use %s.', $theme, $menu, self::list($menuPlacements));
        }

        if (null !== $search && !\in_array($search, self::SEARCH_PLACEMENTS, true)) {
            $errors[] = \sprintf('Unknown search placement "%s": use %s.', $search, self::list(self::SEARCH_PLACEMENTS));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    public static function menuPlacements(string $theme): array
    {
        return self::THEME_MENU_PLACEMENTS[$theme] ?? self::MENU_PLACEMENTS;
    }

    /**
     * @param array{menu?: string, search?: string} $options
     *
     * @return array{menu: string, search: string}
     */
    public static function resolve(string $theme, array $options = []): array
    {
        return array_replace(self::DEFAULT, self::THEME_DEFAULTS[$theme] ?? [], $options);
    }

    /** Outside resources/, which only holds themes (one directory each). */
    public static function resourcesDir(): string
    {
        return \dirname(__DIR__) . '/header_layout';
    }

    public static function install(App $app, string $theme): void
    {
        $themeDir = \dirname(__DIR__) . '/resources/' . $theme;

        foreach ((new Finder())->files()->in(self::resourcesDir()) as $file) {
            // The theme replaces this file (its search form): the theme installer copies it, without overwriting a
            // newer file, so it must not be written here first.
            if (is_file($themeDir . '/' . $file->getRelativePathname())) {
                continue;
            }

            fs()->copy($file->getPathname(), $app->directory() . '/' . $file->getRelativePathname(), true);
        }

        self::dump($app, $theme, self::resolve($theme, self::$options));
    }

    /**
     * Changes the options of the installed theme, keeping the ones not given.
     *
     * @param array{menu?: string, search?: string} $options
     */
    public static function update(string $hooksFile, array $options): void
    {
        $current = self::read($hooksFile);

        if (null === $current) {
            throw new \RuntimeException(\sprintf('No header layout found in "%s": reinstall the theme.', $hooksFile));
        }

        (new Filesystem())->dumpFile($hooksFile, self::hooksFile($current['theme'], array_replace($current['options'], $options), self::prefixOf($hooksFile)));
    }

    public static function remove(App $app): void
    {
        foreach ((new Finder())->files()->in(self::resourcesDir()) as $file) {
            fs()->remove($app->directory() . '/' . $file->getRelativePathname());
        }

        fs()->remove($app->directory() . '/' . self::HOOKS_FILE);
    }

    /**
     * @param array{menu: string, search: string} $options
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function hooks(string $theme, array $options): array
    {
        return [
            'sylius_shop.base#body_classes' => [
                'themes_header_layout' => [
                    'template' => 'shop/shared/layout/header_layout/body_classes.html.twig',
                    'configuration' => [
                        'theme' => $theme,
                        'menu' => $options['menu'],
                        'search' => $options['search'],
                    ],
                    'priority' => -100,
                ],
            ],
            // After the category menu (250), before the account links (200).
            'sylius_shop.base.header.content' => [
                'themes_header_search' => [
                    'template' => 'shop/shared/layout/header_layout/search.html.twig',
                    'configuration' => [
                        'placement' => $options['search'],
                    ],
                    'priority' => 240,
                ] + ('none' === $options['search'] ? ['enabled' => false] : []),
            ],
        ];
    }

    /**
     * @param array{menu: string, search: string} $options
     */
    public static function hooksFile(string $theme, array $options, string $prefix = ''): string
    {
        $hooks = [];

        foreach (self::hooks($theme, $options) as $hookName => $hookables) {
            $hooks[$prefix . $hookName] = $hookables;
        }

        $config = ThemeBundle::export(['sylius_twig_hooks' => ['hooks' => $hooks]]);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Symfony\\Component\\DependencyInjection\\Loader\\Configurator;

            // Generated by opencolibri/starter-themes: the header layout of the "{$theme}" theme. Change it with
            // "castor sylius:theme:header", not by hand.
            return App::config({$config});

            PHP;
    }

    /**
     * @return array{theme: string, options: array{menu: string, search: string}}|null
     */
    public static function read(string $hooksFile): ?array
    {
        if (!is_file($hooksFile)) {
            return null;
        }

        foreach (ThemeBundle::readHooks($hooksFile) as $hookName => $hookables) {
            if (str_ends_with($hookName, 'sylius_shop.base#body_classes') && isset($hookables['themes_header_layout']['configuration'])) {
                $configuration = $hookables['themes_header_layout']['configuration'];

                return [
                    'theme' => $configuration['theme'],
                    'options' => ['menu' => $configuration['menu'], 'search' => $configuration['search']],
                ];
            }
        }

        return null;
    }

    /**
     * @param array{menu: string, search: string} $options
     */
    private static function dump(App $app, string $theme, array $options): void
    {
        fs()->dumpFile($app->directory() . '/' . self::HOOKS_FILE, self::hooksFile($theme, $options));
    }

    private static function prefixOf(string $hooksFile): string
    {
        foreach (ThemeBundle::readHooks($hooksFile) as $hookName => $hookables) {
            $position = strpos($hookName, 'sylius_shop.base#body_classes');

            if (false !== $position) {
                return substr($hookName, 0, $position);
            }
        }

        return '';
    }

    /**
     * @param list<string> $values
     */
    private static function list(array $values): string
    {
        return implode(', ', array_map(static fn(string $value): string => '"' . $value . '"', $values));
    }
}
