<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes\Tasks;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Core\Component\ComponentChoices;
use OpenColibri\Starter\Core\Util\Assets;
use OpenColibri\Starter\Core\Util\Docker;
use OpenColibri\Starter\Core\Util\Symfony;
use OpenColibri\Starter\Themes\HeaderLayout;
use OpenColibri\Starter\Themes\ThemeBundle;
use OpenColibri\Starter\Themes\Themes;

use function Castor\io;

final class ThemeTasks
{
    public function __construct(
        private readonly string $name,
        private readonly string $directory,
    ) {}

    public function __invoke(): iterable
    {
        $app = new App($this->name, $this->directory);

        yield [
            'task' => new AsTask('setup', 'sylius:theme', 'Setup themes', ['setup-theme']),
            'function' => static function (
                #[AsArgument]
                ?string $theme = null,
                #[AsOption(description: 'Where to install the theme: "flat" (over the app, for every channel) or "theme-bundle" (next to the other themes, picked by each channel)')]
                string $target = 'flat',
                #[AsOption(description: 'Where the category menu goes: "inline" (in the logo row), "below" (on a row of its own) or "bar" (in a full-width band). Default: the theme layout')]
                ?string $menu = null,
                #[AsOption(description: 'Where the header search goes: "none", "inline" (in the logo row), "below" (on a row of its own) or "icon" (a magnifier that opens it in a dropdown). Default: the theme layout')]
                ?string $search = null,
            ) use ($app): void {
                if (!\in_array($target, ['flat', ThemeBundle::TARGET], true)) {
                    io()->error(\sprintf('Unknown target "%s": use "flat" or "%s".', $target, ThemeBundle::TARGET));

                    return;
                }

                $themeInstallers = Themes::installers();
                $availableThemes = array_keys($themeInstallers);
                $availableThemes[] = 'default';
                sort($availableThemes);

                $installers = array_map(
                    static fn(callable $installer): callable => static fn() => $installer($app),
                    $themeInstallers,
                );

                $installers['default'] = static function () use ($app): void {
                    Assets::build($app);
                    // The removed theme files (hooks, hook renderer...) are still in the compiled container.
                    Symfony::cacheClear($app);
                };

                $choices = ComponentChoices::build($themeInstallers);
                $choices['default'] = 'default';
                ksort($choices);

                $removers = array_map(
                    static fn(callable $remover): callable => static fn() => $remover($app),
                    Themes::removers(),
                );

                $removers['default'] = static function (): void {};

                if (null === $theme) {
                    $theme = io()->choice(
                        'Which theme would you like to use?',
                        $choices,
                    );
                }

                if (!isset($installers[$theme])) {
                    io()->error(\sprintf('Unknown theme installer "%s", skipping.', $theme));

                    return;
                }

                if ('default' !== $theme) {
                    $errors = HeaderLayout::validate($theme, $menu, $search);

                    if ([] !== $errors) {
                        io()->error($errors);

                        return;
                    }

                    HeaderLayout::configure($menu, $search);
                } elseif (null !== $menu || null !== $search) {
                    io()->warning('The default theme has no header layout options: --menu and --search are ignored.');
                }

                if (ThemeBundle::TARGET === $target) {
                    if (!ThemeBundle::supports($theme)) {
                        io()->error(\sprintf('The "%s" theme cannot be installed with the theme-bundle target.', $theme));

                        return;
                    }

                    // The hooks and templates of a flat theme would apply to every channel.
                    foreach ($availableThemes as $themeToRemove) {
                        ($removers[$themeToRemove] ?? static function (): void {})();
                    }

                    ThemeBundle::install($app, $theme);
                    Assets::install($app);
                    Assets::build($app);
                    Symfony::cacheClear($app);

                    io()->success(\sprintf(
                        '"%s" theme has been installed next to the other themes. Pick it for a channel with "castor sylius:theme:channel <channel> %s".',
                        $theme,
                        $theme,
                    ));

                    return;
                }

                // A flat theme applies to every channel: drop the themes picked by channels.
                foreach (ThemeBundle::installed($app) as $installedTheme) {
                    ThemeBundle::remove($app, $installedTheme);
                }

                ThemeBundle::teardown($app);

                $themesToRemove = array_diff($availableThemes, $theme ? [$theme] : []);

                foreach ($themesToRemove as $themeToRemove) {
                    if (!isset($removers[$themeToRemove])) {
                        io()->warning(\sprintf('Unknown theme remover "%s", skipping.', $themeToRemove));

                        continue;
                    }
                    $removers[$themeToRemove]();
                }

                $installers[$theme]();

                io()->success(\sprintf('"%s" theme has been installed successfully.', $theme));
            },
        ];

        yield [
            'task' => new AsTask('channel', 'sylius:theme', 'Pick the theme of a channel, among the themes installed with the theme-bundle target'),
            'function' => static function (
                #[AsArgument(description: 'Channel code')]
                string $channel,
                #[AsArgument(description: 'Theme, or "default" for the app look')]
                string $theme = 'default',
            ) use ($app): int {
                if (!preg_match('/^[A-Za-z0-9_.-]+$/', $channel)) {
                    io()->getErrorStyle()->error(\sprintf('Invalid channel code "%s".', $channel));

                    return 1;
                }

                $installedThemes = ThemeBundle::installed($app);

                if ('default' !== $theme && !\in_array($theme, $installedThemes, true)) {
                    io()->getErrorStyle()->error(\sprintf(
                        'The "%s" theme is not installed with the theme-bundle target. Installed themes: %s.',
                        $theme,
                        [] === $installedThemes ? 'none' : implode(', ', $installedThemes),
                    ));

                    return 1;
                }

                $themeName = 'default' === $theme ? 'NULL' : \sprintf("'%s'", ThemeBundle::themeName($theme));

                Docker::run($app, \sprintf(
                    'bin/console dbal:run-sql "UPDATE sylius_channel SET theme_name = %s WHERE code = \'%s\'"',
                    $themeName,
                    $channel,
                ));

                io()->success(\sprintf('The "%s" channel now uses the "%s" theme.', $channel, $theme));

                return 0;
            },
        ];

        yield [
            'task' => new AsTask('header', 'sylius:theme', 'Show or change where the header shows the category menu and the search'),
            'function' => static function (
                #[AsOption(description: 'Where the category menu goes: "inline" (in the logo row), "below" (on a row of its own) or "bar" (in a full-width band)')]
                ?string $menu = null,
                #[AsOption(description: 'Where the header search goes: "none", "inline" (in the logo row), "below" (on a row of its own) or "icon" (a magnifier that opens it in a dropdown)')]
                ?string $search = null,
                #[AsOption(description: 'The theme to show or change, among the themes installed with the theme-bundle target (default: the theme installed over the app)')]
                ?string $theme = null,
                #[AsOption(description: 'Show the layout of every installed theme')]
                bool $all = false,
                #[AsOption(description: 'Output format: "text" or "json" ({"themes": {"<theme>": {"menu", "search", "menus", "searches"}}})')]
                string $format = 'text',
            ) use ($app): int {
                $errors = io()->getErrorStyle();

                if (!\in_array($format, ['text', 'json'], true)) {
                    $errors->error(\sprintf('Unknown format "%s": use "text" or "json".', $format));

                    return 1;
                }

                // Every layout, or the layout of one theme.
                $print = static function (array $layouts) use ($format): void {
                    if ('json' === $format) {
                        io()->writeln(json_encode(['themes' => (object) $layouts], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

                        return;
                    }

                    foreach ($layouts as $name => $layout) {
                        io()->text(\sprintf('The "%s" theme shows the menu "%s" and the search "%s".', $name, $layout['menu'], $layout['search']));
                    }
                };

                if ($all) {
                    if (null !== $menu || null !== $search || null !== $theme) {
                        $errors->error('--all only shows the layouts: change one theme at a time, with --theme.');

                        return 1;
                    }

                    $layouts = HeaderLayout::all($app);

                    if ([] === $layouts) {
                        $errors->error('No installed theme has a header layout: install one with "castor sylius:theme:setup <theme>".');

                        return 1;
                    }

                    $print($layouts);

                    return 0;
                }

                $hooksFile = HeaderLayout::hooksFileOf($app, $theme);
                $current = HeaderLayout::read($hooksFile);

                if (null === $current) {
                    $errors->error(null === $theme
                        ? 'No theme is installed over the app: install one with "castor sylius:theme:setup <theme>", or pick a theme-bundle theme with --theme.'
                        : \sprintf('The "%s" theme is not installed with the theme-bundle target, or was installed before the header layout existed: reinstall it.', $theme));

                    return 1;
                }

                if (null === $menu && null === $search) {
                    $print([$current['theme'] => HeaderLayout::describe($current['theme'], $current['options'])]);

                    if ('text' === $format) {
                        io()->text('Change them with --menu and --search.');
                    }

                    return 0;
                }

                $validation = HeaderLayout::validate($current['theme'], $menu, $search);

                if ([] !== $validation) {
                    $errors->error($validation);

                    return 1;
                }

                HeaderLayout::update($hooksFile, array_filter(['menu' => $menu, 'search' => $search], static fn(?string $value): bool => null !== $value));
                // The layout is hook configuration, styled for every placement: no asset build needed.
                Symfony::cacheClear($app);

                $updated = HeaderLayout::read($hooksFile) ?? $current;

                if ('json' === $format) {
                    $print([$current['theme'] => HeaderLayout::describe($current['theme'], $updated['options'])]);

                    return 0;
                }

                io()->success(\sprintf(
                    'The "%s" theme now shows the menu "%s" and the search "%s".',
                    $current['theme'],
                    $updated['options']['menu'],
                    $updated['options']['search'],
                ));

                return 0;
            },
        ];
    }
}
