<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may speak for the visitor through X-Forwarded-* headers.
 *
 * Railway's edge is the only way in there, so everything in front is trusted.
 * The self-hosted server trusts only cloudflared on the same PC — anybody
 * else sending those headers is forging them, and must neither pick their
 * own IP address (the login throttles key on it) nor claim HTTPS.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client', fn (Request $request): array => [
            'ip' => $request->ip(),
            'secure' => $request->secure(),
        ]);
    }

    protected function tearDown(): void
    {
        // Trusted proxies are static on the Symfony request; do not leak them.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        parent::tearDown();
    }

    public function test_every_proxy_is_trusted_by_default_as_railway_needs(): void
    {
        $this->assertSame('*', config('trustedproxy.proxies'));

        $this->forwardedFrom('203.0.113.9')->assertExactJson([
            'ip' => '198.51.100.1',
            'secure' => true,
        ]);
    }

    public function test_a_direct_visitor_can_not_forge_their_address_or_https(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $this->forwardedFrom('203.0.113.9')->assertExactJson([
            'ip' => '203.0.113.9',
            'secure' => false,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function trustedLists(): array
    {
        return [
            'one address' => ['127.0.0.1'],
            'a list with spaces' => ['10.0.0.1, 127.0.0.1'],
            'a range' => ['127.0.0.0/8'],
        ];
    }

    #[DataProvider('trustedLists')]
    public function test_a_listed_proxy_is_believed(string $proxies): void
    {
        config(['trustedproxy.proxies' => $proxies]);

        // cloudflared runs on the same PC, so it connects from 127.0.0.1.
        $this->forwardedFrom('127.0.0.1')->assertExactJson([
            'ip' => '198.51.100.1',
            'secure' => true,
        ]);
    }

    /**
     * Request the probe route from the given address, claiming to forward a
     * visitor at 198.51.100.1 who arrived over HTTPS.
     */
    private function forwardedFrom(string $remoteAddress): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders([
                'X-Forwarded-For' => '198.51.100.1',
                'X-Forwarded-Proto' => 'https',
            ])
            ->getJson('/_test/client')
            ->assertOk();
    }
}
