<?php

declare(strict_types=1);

namespace OpenColibri\Starter\Themes\Tests\Unit;

use OpenColibri\Starter\Core\App;
use OpenColibri\Starter\Themes\HeaderLayout;
use OpenColibri\Starter\Themes\ThemeBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(HeaderLayout::class)]
final class HeaderLayoutTest extends TestCase
{
    private ?string $file = null;

    protected function tearDown(): void
    {
        if (null !== $this->file && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testThemesKeepTheLayoutTheyWereDesignedWith(): void
    {
        static::assertSame(['menu' => 'inline', 'search' => 'none'], HeaderLayout::resolve('canvas'));
        static::assertSame(['menu' => 'below', 'search' => 'none'], HeaderLayout::resolve('depot'));
        static::assertSame(['menu' => 'below', 'search' => 'inline'], HeaderLayout::resolve('torque'));
        static::assertSame(['menu' => 'bar', 'search' => 'inline'], HeaderLayout::resolve('drive'));
    }

    public function testOptionsOverrideTheThemeLayout(): void
    {
        static::assertSame(['menu' => 'inline', 'search' => 'inline'], HeaderLayout::resolve('torque', ['menu' => 'inline']));
        static::assertSame(['menu' => 'bar', 'search' => 'below'], HeaderLayout::resolve('canvas', ['menu' => 'bar', 'search' => 'below']));
    }

    public function testValidate(): void
    {
        static::assertSame([], HeaderLayout::validate('canvas', 'below', 'inline'));
        static::assertSame([], HeaderLayout::validate('canvas', null, null));
        static::assertCount(1, HeaderLayout::validate('canvas', 'sidebar', null));
        static::assertCount(1, HeaderLayout::validate('canvas', null, 'sidebar'));
        static::assertCount(1, HeaderLayout::validate('drive', 'inline', null));
        static::assertSame([], HeaderLayout::validate('drive', 'bar', 'below'));
        static::assertSame([], HeaderLayout::validate('canvas', 'inline', 'icon'));
    }

    public function testTheSearchIsDisabledWithoutSearch(): void
    {
        $hooks = HeaderLayout::hooks('canvas', ['menu' => 'inline', 'search' => 'none']);

        static::assertFalse($hooks['sylius_shop.base.header.content']['themes_header_search']['enabled']);
        static::assertSame(
            ['theme' => 'canvas', 'menu' => 'inline', 'search' => 'none'],
            $hooks['sylius_shop.base#body_classes']['themes_header_layout']['configuration'],
        );

        $hooks = HeaderLayout::hooks('canvas', ['menu' => 'inline', 'search' => 'below']);

        static::assertArrayNotHasKey('enabled', $hooks['sylius_shop.base.header.content']['themes_header_search']);
    }

    public function testUpdateKeepsTheThemeThePrefixAndTheOptionsNotGiven(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'header_layout') . '.php';
        file_put_contents($this->file, HeaderLayout::hooksFile('cellar', HeaderLayout::resolve('cellar'), 'cellar:'));

        HeaderLayout::update($this->file, ['search' => 'inline']);

        static::assertSame(
            ['theme' => 'cellar', 'options' => ['menu' => 'inline', 'search' => 'inline']],
            HeaderLayout::read($this->file),
        );
        static::assertStringContainsString("'cellar:sylius_shop.base#body_classes'", (string) file_get_contents($this->file));
    }

    public function testItFindsTheHooksFileOfATheme(): void
    {
        $app = new App('app', '/project/app');

        static::assertSame('/project/app/config/sylius/twig_hooks/shop/base/header_layout.php', HeaderLayout::hooksFileOf($app));
        static::assertSame('/project/app/config/sylius/twig_hooks/themes/cellar/shop/base/header_layout.php', HeaderLayout::hooksFileOf($app, 'cellar'));
    }

    public function testItDescribesTheLayoutAndThePlacementsOfATheme(): void
    {
        static::assertSame(
            ['menu' => 'bar', 'search' => 'icon', 'menus' => ['bar'], 'searches' => HeaderLayout::SEARCH_PLACEMENTS],
            HeaderLayout::describe('drive', ['menu' => 'bar', 'search' => 'icon']),
        );
    }

    public function testItListsTheLayoutOfEveryInstalledTheme(): void
    {
        $directory = sys_get_temp_dir() . '/header_layout_' . bin2hex(random_bytes(4));
        $app = new App('app', $directory);
        $filesystem = new Filesystem();

        try {
            // Over the app (flat target).
            $filesystem->dumpFile(HeaderLayout::hooksFileOf($app), HeaderLayout::hooksFile('canvas', ['menu' => 'below', 'search' => 'none']));
            // With the theme-bundle target: drive with a layout, cellar installed before the header layout existed.
            foreach (['drive', 'cellar'] as $theme) {
                $filesystem->dumpFile($directory . '/' . ThemeBundle::THEMES_DIR . '/' . $theme . '/composer.json', '{}');
            }
            $filesystem->dumpFile(HeaderLayout::hooksFileOf($app, 'drive'), HeaderLayout::hooksFile('drive', HeaderLayout::resolve('drive'), ThemeBundle::hookPrefix('drive')));

            $layouts = HeaderLayout::all($app);

            static::assertSame(['canvas', 'drive'], array_keys($layouts));
            static::assertSame('flat', $layouts['canvas']['target']);
            static::assertSame(['menu' => 'bar', 'search' => 'inline', 'menus' => ['bar'], 'searches' => HeaderLayout::SEARCH_PLACEMENTS, 'target' => 'theme-bundle'], $layouts['drive']);
        } finally {
            $filesystem->remove($directory);
        }
    }
}
