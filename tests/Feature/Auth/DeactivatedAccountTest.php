<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Appeal;
use App\Models\Conversation;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A deactivated account signs in to Settings and its appeal, and nowhere else.
 *
 * Everything in the header — dashboard, board, project management,
 * agreements, teams, messages, notifications, profile — sends it back to
 * settings; settings, security, the appeal and signing out stay open.
 */
class DeactivatedAccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function closedStudentPages(): array
    {
        return [
            'dashboard' => ['dashboard', 'team'],
            'get client' => ['student.board.index', 'team'],
            'project management' => ['project-management', 'team'],
            'agreements' => ['agreements.index', 'team'],
            'messages' => ['messages.index', 'team'],
            'notifications' => ['notifications.index', 'team'],
            'own profile' => ['student.profile.edit', 'team'],
            'teams' => ['teams.index', 'none'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function closedClientPages(): array
    {
        return [
            'overview' => ['client.dashboard'],
            'recruit' => ['recruit.index'],
            'postings' => ['projects.index'],
            'business profile' => ['client-profile.edit'],
            'messages' => ['messages.index'],
        ];
    }

    #[DataProvider('closedStudentPages')]
    public function test_a_deactivated_student_is_sent_back_to_settings(string $route, string $scope): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $url = $scope === 'team'
            ? route($route, ['current_team' => $student->currentTeam])
            : route($route);

        $this->actingAs($student)
            ->get($url)
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', 'Your account is deactivated. Only Settings and your appeal are available.');
    }

    #[DataProvider('closedClientPages')]
    public function test_a_deactivated_client_is_sent_back_to_settings(string $route): void
    {
        $client = User::factory()->client()->deactivated()->create();

        $this->actingAs($client)
            ->get(route($route, ['current_team' => $client->currentTeam]))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_an_action_is_not_carried_out(): void
    {
        $reporter = User::factory()->student()->deactivated()->create();
        $reported = User::factory()->client()->create();

        $this->actingAs($reporter)
            ->post(route('reports.store'), [
                'reported_user_id' => $reported->id,
                'reason' => 'spam',
                'details' => 'Posting the same thing everywhere.',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(0, Issue::count());
    }

    public function test_a_json_request_is_refused_outright(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->getJson(route('messages.index', ['current_team' => $student->currentTeam]))
            ->assertForbidden();
    }

    public function test_a_conversation_can_not_be_listened_to(): void
    {
        $student = User::factory()->student()->deactivated()->create();
        $conversation = Conversation::factory()->create();

        $this->actingAs($student)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversations.'.$conversation->id,
            ])
            ->assertForbidden();
    }

    /**
     * The account's own channel carries the "someone tried to sign in" alert,
     * which is about the account itself rather than anything it lost.
     */
    public function test_its_own_notification_channel_stays_open(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$student->id,
            ])
            ->assertOk();
    }

    public function test_settings_stays_open_and_offers_the_appeal(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('auth.status', UserStatus::Deactivated->value)
                ->where('accountStatus.mayAppeal', true),
            );

        $this->actingAs($student)->get('/settings')->assertRedirect('/settings/profile');
    }

    public function test_the_appeal_can_be_sent_from_settings(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->post(route('profile.appeal.store'), [
                'body' => 'I was deactivated over a report I was never given a chance to answer.',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $appeal = Appeal::sole();

        $this->assertSame($student->id, $appeal->user_id);
        $this->assertSame(UserStatus::Deactivated, $appeal->account_status);
    }

    public function test_security_settings_stay_open(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        // Security asks for the password first; that screen is open too.
        $this->actingAs($student)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($student)
            ->get(route('password.confirm'))
            ->assertOk();

        $this->actingAs($student)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk();
    }

    public function test_public_pages_and_signing_out_stay_open(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)->get(route('home'))->assertOk();
        $this->actingAs($student)->get(route('legal', 'terms-of-service'))->assertOk();
        $this->actingAs($student)->get(route('session.heartbeat'))->assertSuccessful();

        $this->actingAs($student)->post(route('logout'));

        $this->assertGuest();
    }

    public function test_the_guest_pages_send_it_to_settings(): void
    {
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->get(route('register'))
            ->assertRedirect(route('profile.edit', absolute: false));
    }

    public function test_a_monitored_account_keeps_the_rest_of_the_platform(): void
    {
        $student = User::factory()->student()->create(['status' => UserStatus::Monitored]);

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk();
    }

    public function test_an_approved_account_is_unaffected(): void
    {
        $student = User::factory()->student()->approved()->create();

        $this->actingAs($student)
            ->get(route('messages.index', ['current_team' => $student->currentTeam]))
            ->assertOk();
    }

    public function test_restoring_the_account_opens_everything_again(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->deactivated()->create();

        $this->actingAs($student)
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status.update', $student), [
                'status' => UserStatus::Approved->value,
            ]);

        $this->actingAs($student->fresh())
            ->get(route('student.board.index', ['current_team' => $student->currentTeam]))
            ->assertOk();
    }
}
