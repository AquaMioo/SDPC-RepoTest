<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\PresentNotification;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Client\ApplicationReceived;
use App\Notifications\Client\ProjectInvitation;
use App\Notifications\Client\ProjectPublished;
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

    public function test_opening_a_notification_marks_it_read_and_carries_you_to_it(): void
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

        $notification = $student->notifications()->sole();

        $this->actingAs($student)
            ->from(route('notifications.index', ['current_team' => $student->currentTeam]))
            ->post(route('notifications.read', [
                'current_team' => $student->currentTeam,
                'notification' => $notification->id,
            ]), ['follow' => true])
            ->assertRedirect(route('messages.show', [
                'current_team' => $student->currentTeam->slug,
                'conversation' => $conversation->id,
            ]));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_a_notification_read_without_following_stays_on_the_list(): void
    {
        $client = User::factory()->client()->create();
        $client->notify(new ApplicationReceived($this->application()));

        $notification = $client->notifications()->sole();
        $list = route('notifications.index', ['current_team' => $client->currentTeam]);

        $this->actingAs($client)
            ->from($list)
            ->post(route('notifications.read', [
                'current_team' => $client->currentTeam,
                'notification' => $notification->id,
            ]))
            ->assertRedirect($list);

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

    public function test_a_row_carries_who_it_is_from_and_when_it_was_sent(): void
    {
        $client = User::factory()->client()->create();
        $sender = User::factory()->student()->create(['name' => 'Jan Joshua Mangahas']);

        $conversation = Conversation::create([
            'project_id' => Project::factory()->create()->id,
            'user_id' => $sender->id,
        ]);

        $client->notify(new NewMessage($conversation->messages()->create([
            'user_id' => $sender->id,
            'body' => 'Sent you the reviewer',
        ])));

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.0.from', 'Jan Joshua Mangahas')
                ->where('notifications.0.initials', 'JJ')
                ->whereNot('notifications.0.sentOn', null)
                ->whereNot('notifications.0.sentTime', null));
    }

    /**
     * Nobody triggers an approval, so there is no name in the payload to show.
     */
    public function test_an_event_no_person_triggered_is_from_the_system(): void
    {
        $client = User::factory()->client()->create();

        $client->notify(new ProjectPublished(Project::factory()->create()));

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.0.from', PresentNotification::SYSTEM_SENDER)
                ->where('notifications.0.initials', 'S'));
    }

    public function test_the_bell_menu_is_shared_with_every_screen(): void
    {
        $client = User::factory()->client()->create();
        $client->notify(new ApplicationReceived($this->application()));

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page->has('recentNotifications', 1));
    }

    /**
     * The menu shows five; the sixth is only what tells it there are more.
     */
    public function test_the_bell_menu_never_carries_more_than_six_rows(): void
    {
        $client = User::factory()->client()->create();
        $application = $this->application();

        foreach (range(1, 9) as $ignored) {
            $client->notify(new ApplicationReceived($application));
        }

        $this->actingAs($client)
            ->get(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->assertInertia(fn ($page) => $page->has('recentNotifications', 6));
    }

    public function test_the_ticked_rows_can_be_marked_read_together(): void
    {
        $client = User::factory()->client()->create();
        $application = $this->application();

        $client->notify(new ApplicationReceived($application));
        $client->notify(new ApplicationReceived($application));

        [$first, $second] = $client->notifications()->get()->all();

        $this->actingAs($client)
            ->from(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->post(route('notifications.read-selected', ['current_team' => $client->currentTeam]), [
                'ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at, 'A row nobody ticked must stay unread.');
    }

    public function test_the_ticked_rows_can_be_deleted(): void
    {
        $client = User::factory()->client()->create();
        $application = $this->application();

        $client->notify(new ApplicationReceived($application));
        $client->notify(new ApplicationReceived($application));

        [$first, $second] = $client->notifications()->get()->all();

        $this->actingAs($client)
            ->from(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->delete(route('notifications.destroy', ['current_team' => $client->currentTeam]), [
                'ids' => [$first->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $first->id]);
        $this->assertDatabaseHas('notifications', ['id' => $second->id]);
    }

    /**
     * Deleting is scoped through the account's own relation, so an id belonging
     * to somebody else matches nothing rather than being destroyed.
     */
    public function test_deleting_cannot_reach_a_row_belonging_to_somebody_else(): void
    {
        $mine = User::factory()->client()->create();
        $theirs = User::factory()->client()->create();

        $theirs->notify(new ApplicationReceived($this->application()));
        $row = $theirs->notifications()->sole();

        $this->actingAs($mine)
            ->from(route('notifications.index', ['current_team' => $mine->currentTeam]))
            ->delete(route('notifications.destroy', ['current_team' => $mine->currentTeam]), [
                'ids' => [$row->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', ['id' => $row->id]);
    }

    /**
     * Clearing takes the read rows and deliberately leaves the unread ones:
     * those are the ones nobody has looked at yet.
     */
    public function test_clearing_removes_what_was_read_and_keeps_what_was_not(): void
    {
        $client = User::factory()->client()->create();
        $application = $this->application();

        $client->notify(new ApplicationReceived($application));
        $client->notify(new ApplicationReceived($application));

        [$read, $unread] = $client->notifications()->get()->all();
        $read->markAsRead();

        $this->actingAs($client)
            ->from(route('notifications.index', ['current_team' => $client->currentTeam]))
            ->delete(route('notifications.clear', ['current_team' => $client->currentTeam]))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $read->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unread->id]);
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
