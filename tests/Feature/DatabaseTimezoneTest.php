<?php

namespace Tests\Feature;

use Illuminate\Database\Connectors\MySqlConnector;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The app and MySQL agree on the clock: both UTC.
 *
 * TIMESTAMP columns are converted through the MySQL session's time zone. The
 * self-hosted server runs MySQL on Philippine time, and without pinning the
 * session every value written in UTC on Railway read back eight hours in the
 * future after the move — last_seen_at among them, which kept accounts
 * locked as "in use" with nobody signed in.
 */
class DatabaseTimezoneTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function connections(): array
    {
        return [
            'mysql' => ['mysql'],
            'mariadb' => ['mariadb'],
        ];
    }

    #[DataProvider('connections')]
    public function test_the_connection_talks_to_the_server_in_utc(string $connection): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', config("database.connections.{$connection}.timezone"));
    }

    public function test_the_session_time_zone_is_set_on_connect(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains("time_zone='+00:00'"));

        $connector = new class extends MySqlConnector
        {
            /**
             * @param  array<string, mixed>  $config
             */
            public function configure(PDO $connection, array $config): void
            {
                $this->configureConnection($connection, $config);
            }
        };

        $connector->configure($pdo, [
            ...config('database.connections.mysql'),
            'modes' => [],
        ]);
    }
}
