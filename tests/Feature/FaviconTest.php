<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser tab shows the SDPC mark, not the Laravel logo the starter kit
 * shipped with (favicon.svg). Browsers prefer an SVG icon when one is linked,
 * so the SVG they get is SDPC's icon.svg and Laravel's must not come back.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shell_links_the_sdpc_icons_only(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('href="/icon.svg?v=5" type="image/svg+xml"', escape: false);
        $response->assertSee('href="/favicon.ico?v=5"', escape: false);
        $response->assertSee('href="/apple-touch-icon.png?v=5"', escape: false);
        $response->assertDontSee('favicon.svg', escape: false);
    }

    public function test_every_icon_the_shell_links_is_served(): void
    {
        $html = $this->get(route('login'))->getContent();

        preg_match_all('/<link rel="(?:icon|apple-touch-icon)" href="([^"?]+)/', $html, $matches);

        $this->assertCount(3, $matches[1], 'The shell should link the SVG, the ICO and the home-screen icon.');

        foreach ($matches[1] as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }
    }

    public function test_the_laravel_svg_icon_is_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('favicon.svg'));
    }

    public function test_the_svg_icon_is_the_sdpc_mark_on_a_white_tile(): void
    {
        $svg = file_get_contents(public_path('icon.svg'));

        $this->assertStringContainsString('<title>SDPC</title>', $svg);

        /*
         * The team tried the mark with no tile and wanted the white one back
         * (2026-09-19). The tile is also what keeps the dark green readable
         * on a dark tab bar.
         */
        $this->assertMatchesRegularExpression('/<rect [^>]*fill="#fff"/', $svg);
    }

    public function test_the_favicon_carries_every_tab_size(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));

        /** @var array{reserved: int, type: int, count: int} $header */
        $header = unpack('vreserved/vtype/vcount', $ico);

        $this->assertSame(0, $header['reserved']);
        $this->assertSame(1, $header['type'], 'favicon.ico is not an icon file.');

        $sizes = [];

        for ($entry = 0; $entry < $header['count']; $entry++) {
            $sizes[] = ord($ico[6 + $entry * 16]);
        }

        $this->assertSame([16, 20, 24, 32, 40, 48, 64], $sizes);
    }
}
