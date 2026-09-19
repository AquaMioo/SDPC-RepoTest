<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The browser tab shows the SDPC mark, not the Laravel logo the starter kit
 * shipped with (favicon.svg). Browsers prefer an SVG icon when one is linked,
 * so that file must not come back.
 */
class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shell_links_the_sdpc_icons_only(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('href="/favicon.ico?v=2"', escape: false);
        $response->assertSee('href="/apple-touch-icon.png?v=2"', escape: false);
        $response->assertDontSee('favicon.svg', escape: false);
    }

    public function test_every_icon_the_shell_links_is_served(): void
    {
        $html = $this->get(route('login'))->getContent();

        preg_match_all('/<link rel="(?:icon|apple-touch-icon)" href="([^"?]+)/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The shell links no icons.');

        foreach ($matches[1] as $path) {
            $this->assertFileExists(public_path(ltrim($path, '/')));
        }
    }

    public function test_the_laravel_svg_icon_is_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('favicon.svg'));
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
