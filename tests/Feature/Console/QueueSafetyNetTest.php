<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The scheduler drains the queue when its worker has stopped.
 *
 * Queued notifications went silent twice on the self-hosted PC because the
 * worker service exited and was never restarted. See routes/console.php.
 */
class QueueSafetyNetTest extends TestCase
{
    public function test_the_queue_is_drained_every_minute_in_production(): void
    {
        $event = $this->drain();

        $this->assertSame('* * * * *', $event->expression);
        $this->assertSame(['production'], $event->environments);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_drain_stops_on_its_own(): void
    {
        $command = $this->drain()->command;

        /* An empty queue ends it at once, and a busy one cannot hold it past the next run. */
        $this->assertStringContainsString('--stop-when-empty', $command);
        $this->assertStringContainsString('--max-time=50', $command);
    }

    public function test_it_does_not_run_outside_production(): void
    {
        $this->assertFalse($this->drain()->runsInEnvironment('testing'));
        $this->assertFalse($this->drain()->runsInEnvironment('local'));
    }

    private function drain(): Event
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'queue:work'));

        $this->assertNotNull($event, 'The queue drain is not scheduled.');

        return $event;
    }
}
