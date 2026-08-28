<?php

namespace Tests\Feature\Notifications;

use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Client\ApplicationReceived;
use App\Notifications\Client\ProjectInvitation;
use App\Notifications\Messaging\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The notification centre.
 *
 * Every route is pinned with an explicit current_team: UserFactory switches the
 * team it creates on the user it creates, so an incidental user made by another
 * factory overwrites the URL default. See .ai/rules/feature.md.
 */
class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_centre_lists_what_was_sent_to_this_account(): void
    {
        $client = User::factory()->client()->create();

        $client->notify(new ApplicationReceived($this->application()));

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifications/index')
                ->has('notifications', 1)
                ->where('unreadCount', 1));
    }

    public function test_it_never_shows_a_notification_sent_to_somebody_else(): void
    {
        $mine = User::factory()->client()->create();
        $theirs = User::factory()->client()->create();

        $theirs->notify(new ApplicationReceived($this->application()));

        $this->actingAs($mine)
            ->get(route('notifications.index', ['current_team' => $mine->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications', 0));
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $client = User::factory()->client()->create();
        $client->notify(new ApplicationReceived($this->application()));

        $notification = $client->notifications()->sole();

        $this->actingAs($client)
            ->from(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->post(route('notifications.read', [
                'current_team' => $client->currentTeam,
                'notification' => $notification->id,
            ]))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_read_a_row_belonging_to_somebody_else_is_a_404(): void
    {
        $mine = User::factory()->client()->create();
        $theirs = User::factory()->client()->create();

        $theirs->notify(new ApplicationReceived($this->application()));
        $notification = $theirs->notifications()->sole();

        $this->actingAs($mine)
            ->post(route('notifications.read', [
                'current_team' => $mine->currentTeam,
                'notification' => $notification->id,
            ]))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_everything_can_be_marked_read_at_once(): void
    {
        $client = User::factory()->client()->create();

        $client->notify(new ApplicationReceived($this->application()));
        $client->notify(new ApplicationReceived($this->application()));

        $this->actingAs($client)
            ->from(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->post(route('notifications.read-all', ['current_team' => $client->currentTeam]))
            ->assertRedirect();

        $this->assertSame(0, $client->fresh()->unreadNotifications()->count());
    }

    public function test_an_invitation_onto_a_posting_reads_as_one_and_leads_to_the_workflow(): void
    {
        $student = User::factory()->student()->create();

        $application = Application::factory()->create([
            'project_id' => Project::factory()->create(['title' => 'Inventory System'])->id,
            'user_id' => $student->id,
        ]);

        $student->notify(new ProjectInvitation($application));

        $this->actingAs($student)
            ->get(route('notifications.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.url', route('student.workflow', [
                    'current_team' => $student->currentTeam->slug,
                ]))
                ->where('notifications.0.title', fn (string $title): bool => str_contains($title, 'Inventory System')));
    }

    public function test_a_message_notification_leads_back_to_its_thread(): void
    {
        $student = User::factory()->student()->create();
        $conversation = Conversation::create([
            'project_id' => Project::factory()->create()->id,
            'user_id' => $student->id,
        ]);

        $student->notify(new NewMessage($conversation->messages()->create([
            'user_id' => User::factory()->client()->create()->id,
            'body' => 'Thursday works for us',
        ])));

        $this->actingAs($student)
            ->get(route('notifications.index', ['current_team' => $student->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.body', 'Thursday works for us')
                ->where('notifications.0.url', route('messages.show', [
                    'current_team' => $student->currentTeam->slug,
                    'conversation' => $conversation->id,
                ])));
    }

    public function test_a_payload_the_code_no_longer_recognises_still_renders(): void
    {
        $client = User::factory()->client()->create();
        $client->notify(new ApplicationReceived($this->application()));

        /*
         * Rows outlive the code that wrote them. A type that has since been
         * renamed must degrade to a plain line, not take the screen down.
         */
        $client->notifications()->sole()->update([
            'data' => ['type' => 'something.retired.two.releases.ago'],
        ]);

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 1)
                ->where('notifications.0.url', null));
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $client = User::factory()->client()->create();

        $this->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertRedirect(route('login'));
    }

    public function test_the_unread_count_is_shared_with_every_screen(): void
    {
        $client = User::factory()->client()->create();
        $client->notify(new ApplicationReceived($this->application()));

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
    }

    /**
     * The screen draws the hundred newest rows, but the figure beside them
     * counts every unread one.
     *
     * Counting only the drawn slice made the page disagree with the bell in
     * the header, which has always counted the lot — and "mark all read"
     * cleared more than the number next to it said it would.
     */
    public function test_the_unread_figure_counts_past_the_hundred_rows_on_screen(): void
    {
        $client = User::factory()->client()->create();
        $application = $this->application();

        foreach (range(1, 105) as $ignored) {
            $client->notify(new ApplicationReceived($application));
        }

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('notifications', 100)
                ->where('unreadCount', 105)
                // The bell and the page have to say the same thing.
                ->where('unreadNotifications', 105));
    }

    /**
     * An application to hang a notification on.
     *
     * Not faked: these tests are about the rows the database channel writes,
     * and Notification::fake() would swallow the very thing being asserted.
     * The suite runs on the array mailer and the sync queue, so notifying is
     * immediate and sends nothing anywhere.
     */
    protected function application(): Application
    {
        return Application::factory()
            ->for(Project::factory()->create())
            ->create();
    }
}
