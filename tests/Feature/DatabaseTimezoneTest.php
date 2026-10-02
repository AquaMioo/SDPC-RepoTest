<?php

namespace Tests\Feature;

use Illuminate\Database\Connectors\MySqlConnector;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The app and MySQL agree on the clock: both Singapore Time (GMT+8).
 *
 * TIMESTAMP columns are converted through the MySQL session's time zone, so
 * the session has to match the app. When they disagreed (a UTC app on a
 * server left at local time) every value read back eight hours out —
 * last_seen_at among them, which kept accounts locked as "in use" with
 * nobody signed in.
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
    public function test_the_connection_talks_to_the_server_in_singapore_time(string $connection): void
    {
        $this->assertSame('Asia/Singapore', config('app.timezone'));
        $this->assertSame('+08:00', config("database.connections.{$connection}.timezone"));
    }

    public function test_the_application_clock_runs_on_singapore_time(): void
    {
        $this->travelTo('2026-10-03 23:30:00');

        $this->assertSame('Asia/Singapore', now()->getTimezone()->getName());
        $this->assertSame(8 * 3600, now()->getOffset());
        $this->assertSame('2026-10-03', today()->toDateString());
        $this->assertSame('2026-10-03T15:30:00+00:00', now()->utc()->toIso8601String());
    }

    public function test_the_session_time_zone_is_set_on_connect(): void
    {
        $pdo = $this->createMock(PDO::class);

        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains("time_zone='+08:00'"));

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
