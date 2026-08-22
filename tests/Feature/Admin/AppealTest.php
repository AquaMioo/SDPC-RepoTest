<?php

namespace Tests\Feature\Admin;

use App\Enums\AppealStatus;
use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserStatus;
use App\Models\Appeal;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Answering a decision taken about your account.
 *
 * Two doors, because the two states this covers are not equally free. A
 * monitored account can still sign in, so it appeals from Account Information.
 * A deactivated one cannot, so it appeals from a guest page and proves who it
 * is with an emailed code — not a password, since an account created through
 * Google never had one.
 */
class AppealTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------------------
     | Account Information → Review Appeal
     * ------------------------------------------------------------------ */

    public function test_a_monitored_account_sees_the_appeal_card_on_its_settings(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Monitored]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accountStatus.mayAppeal', true)
                ->where('accountStatus.restricted', true)
                ->where('appeal', null),
            );
    }

    public function test_an_approved_account_is_not_offered_an_appeal(): void
    {
        $user = User::factory()->approved()->create();

        // Nothing stands against it, so there would be nothing to answer.
        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accountStatus.mayAppeal', false),
            );
    }

    public function test_a_monitored_account_can_file_an_appeal(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Monitored]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.appeal.store'), [
                'body' => 'The report was filed by a client whose brief changed after we agreed the scope.',
            ])
            ->assertSessionHasNoErrors();

        $appeal = Appeal::firstOrFail();

        $this->assertSame($user->id, $appeal->user_id);
        $this->assertSame(AppealStatus::Pending, $appeal->status);
        // Snapshotted, so a granted appeal still says what it was answering.
        $this->assertSame(UserStatus::Monitored, $appeal->account_status);
    }

    public function test_an_approved_account_cannot_file_an_appeal(): void
    {
        $user = User::factory()->approved()->create();

        $this->actingAs($user)
            ->post(route('profile.appeal.store'), [
                'body' => 'Appealing a decision that was never taken against me at all.',
            ])
            ->assertForbidden();

        $this->assertSame(0, Appeal::count());
    }

    public function test_a_second_appeal_is_refused_while_the_first_is_unread(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Monitored]);

        $payload = [
            'body' => 'The same plea filed twice in a row by the same account, unchanged.',
        ];

        $this->actingAs($user)->from(route('profile.edit'))->post(route('profile.appeal.store'), $payload);
        $this->actingAs($user)->from(route('profile.edit'))->post(route('profile.appeal.store'), $payload);

        $this->assertSame(1, Appeal::count());
    }

    public function test_a_new_appeal_is_allowed_once_the_first_is_decided(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Monitored]);

        Appeal::factory()->decided()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.appeal.store'), [
                'body' => 'New evidence has come to light since the first appeal was closed.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Appeal::count());
    }

    public function test_a_too_short_appeal_is_rejected(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Monitored]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.appeal.store'), ['body' => 'unfair'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Appeal::count());
    }

    /* ---------------------------------------------------------------------
     | The guest page, for accounts that cannot sign in
     * ------------------------------------------------------------------ */

    public function test_the_appeal_page_is_open_to_guests(): void
    {
        $this->get(route('appeal'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/appeal')
                ->where('email', null),
            );
    }

    public function test_a_deactivated_account_is_sent_a_code(): void
    {
        Notification::fake();

        $user = User::factory()->deactivated()->create();

        $this->post(route('appeal.code'), ['email' => $user->email])
            ->assertRedirect(route('appeal'));

        Notification::assertSentOnDemand(EmailOneTimePassword::class);

        $this->assertSame(
            OneTimePasswordPurpose::Appeal,
            OneTimePassword::firstOrFail()->purpose,
        );
    }

    /**
     * The page must not be a way to find out who is registered — or who has
     * been deactivated. Every answer it gives is the same.
     */
    public function test_an_address_with_nothing_to_appeal_gets_the_same_answer_and_no_code(): void
    {
        Notification::fake();

        $approved = User::factory()->approved()->create();

        $this->post(route('appeal.code'), ['email' => $approved->email])
            ->assertRedirect(route('appeal'));

        $this->post(route('appeal.code'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('appeal'));

        Notification::assertNothingSent();
        $this->assertSame(0, OneTimePassword::count());
    }

    public function test_a_deactivated_account_can_appeal_with_its_code(): void
    {
        Notification::fake();

        $user = User::factory()->deactivated()->create();

        $this->post(route('appeal.code'), ['email' => $user->email]);

        $this->post(route('appeal.submit'), [
            'code' => $this->codeSentTo($user->email),
            'body' => 'I was deactivated over a report I was never given a chance to answer.',
        ])->assertRedirect(route('login'));

        $appeal = Appeal::firstOrFail();

        $this->assertSame($user->id, $appeal->user_id);
        $this->assertSame(UserStatus::Deactivated, $appeal->account_status);

        // Appealing does not restore anything on its own.
        $this->assertSame(UserStatus::Deactivated, $user->fresh()->status);
    }

    public function test_a_wrong_code_files_nothing(): void
    {
        Notification::fake();

        $user = User::factory()->deactivated()->create();

        $this->post(route('appeal.code'), ['email' => $user->email]);

        $this->from(route('appeal'))
            ->post(route('appeal.submit'), [
                'code' => '000000',
                'body' => 'Trying to appeal without the code that was sent to the address.',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(0, Appeal::count());
    }

    public function test_appealing_without_asking_for_a_code_goes_back_to_the_start(): void
    {
        $this->post(route('appeal.submit'), [
            'code' => '000000',
            'body' => 'Skipping straight to the second step without proving anything.',
        ])->assertRedirect(route('appeal'));

        $this->assertSame(0, Appeal::count());
    }

    /**
     * A signed-in account has no business on the guest page — settings is
     * where its appeal lives.
     */
    public function test_a_signed_in_account_is_redirected_away_from_the_guest_page(): void
    {
        $this->actingAs(User::factory()->approved()->create())
            ->get(route('appeal'))
            ->assertRedirect();
    }

    /* ---------------------------------------------------------------------
     | The administrator's monitoring screen
     * ------------------------------------------------------------------ */

    public function test_the_monitoring_screen_lists_held_accounts_with_and_without_appeals(): void
    {
        $admin = User::factory()->admin()->create();

        $silent = User::factory()->create(['status' => UserStatus::Monitored, 'name' => 'Silent Sam']);
        $appellant = User::factory()->create(['status' => UserStatus::Monitored, 'name' => 'Ada Lovelace']);

        Appeal::factory()->create(['user_id' => $appellant->id]);

        // Approved accounts are not on this screen at all.
        User::factory()->approved()->create();

        $this->actingAs($admin)
            ->get(route('admin.monitoring'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/monitoring')
                ->has('accounts', 2)
                // Waiting on a decision sorts first.
                ->where('accounts.0.name', $appellant->name)
                ->where('accounts.1.name', $silent->name)
                ->where('accounts.1.appeal', null),
            );
    }

    public function test_granting_an_appeal_restores_the_account(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->deactivated()->create();
        $appeal = Appeal::factory()->fromDeactivated()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->from(route('admin.monitoring'))
            ->patch(route('admin.appeals.update', $appeal), ['decision' => 'grant'])
            ->assertSessionHasNoErrors();

        $appeal->refresh();

        $this->assertSame(AppealStatus::Granted, $appeal->status);
        $this->assertSame($admin->id, $appeal->reviewed_by);
        $this->assertNotNull($appeal->reviewed_at);
        $this->assertSame(UserStatus::Approved, $user->fresh()->status);
    }

    public function test_denying_an_appeal_leaves_the_decision_standing(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['status' => UserStatus::Monitored]);
        $appeal = Appeal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->from(route('admin.monitoring'))
            ->patch(route('admin.appeals.update', $appeal), [
                'decision' => 'deny',
                'note' => 'Three separate clients reported the same behaviour.',
            ])
            ->assertSessionHasNoErrors();

        $appeal->refresh();

        $this->assertSame(AppealStatus::Denied, $appeal->status);
        $this->assertSame('Three separate clients reported the same behaviour.', $appeal->decision_note);
        $this->assertSame(UserStatus::Monitored, $user->fresh()->status);
    }

    public function test_denying_an_appeal_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $appeal = Appeal::factory()->create();

        // Somebody is being told no, and is shown this.
        $this->actingAs($admin)
            ->from(route('admin.monitoring'))
            ->patch(route('admin.appeals.update', $appeal), ['decision' => 'deny'])
            ->assertSessionHasErrors('note');

        $this->assertSame(AppealStatus::Pending, $appeal->fresh()->status);
    }

    public function test_an_unknown_decision_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $appeal = Appeal::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.monitoring'))
            ->patch(route('admin.appeals.update', $appeal), ['decision' => 'ignore'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(AppealStatus::Pending, $appeal->fresh()->status);
    }

    public function test_non_admins_cannot_read_or_decide_appeals(): void
    {
        $appeal = Appeal::factory()->create();

        foreach ([User::factory()->student(), User::factory()->client()] as $factory) {
            $user = $factory->approved()->create();

            $this->actingAs($user)->get(route('admin.monitoring'))->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.appeals.update', $appeal), ['decision' => 'grant'])
                ->assertForbidden();
        }

        $this->assertSame(AppealStatus::Pending, $appeal->fresh()->status);
    }

    public function test_guests_cannot_reach_the_monitoring_screen(): void
    {
        $this->get(route('admin.monitoring'))->assertRedirect(route('admin.login'));
    }

    public function test_deleting_the_account_removes_its_appeals(): void
    {
        $appeal = Appeal::factory()->create();
        $user = $appeal->user;

        $user->forceDelete();

        $this->assertDatabaseMissing('appeals', ['id' => $appeal->id]);
    }

    /**
     * Read the code that was actually mailed. The table stores a hash.
     */
    private function codeSentTo(string $email): string
    {
        $code = null;

        Notification::assertSentOnDemand(
            EmailOneTimePassword::class,
            function (EmailOneTimePassword $notification, array $channels, AnonymousNotifiable $notifiable) use (&$code, $email): bool {
                if ($notifiable->routes['mail'] !== mb_strtolower($email)) {
                    return false;
                }

                $code = $notification->code;

                return true;
            },
        );

        $this->assertNotNull($code, "No appeal code was mailed to {$email}.");

        return $code;
    }
}
