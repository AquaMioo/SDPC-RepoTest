<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Support\DisposableEmailDomains;
use App\Support\PendingRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Temporary / disposable addresses are refused at sign up and when an account
 * changes its email, on the server (2026-10-02).
 */
class DisposableEmailTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Temporary or disposable email addresses are not allowed. Please use a permanent email address.';

    public function test_the_shipped_list_knows_the_common_services_and_spares_permanent_ones(): void
    {
        $domains = app(DisposableEmailDomains::class);

        foreach (['someone@mailinator.com', 'someone@yopmail.com', 'someone@guerrillamail.com', 'someone@10minutemail.com'] as $email) {
            $this->assertTrue($domains->isDisposable($email), $email);
        }

        foreach (['someone@gmail.com', 'someone@yahoo.com', 'someone@outlook.com', 'someone@zenith-solutions.ph'] as $email) {
            $this->assertFalse($domains->isDisposable($email), $email);
        }
    }

    public function test_case_spaces_and_subdomains_do_not_get_past_it(): void
    {
        $domains = app(DisposableEmailDomains::class);

        $this->assertTrue($domains->isDisposable('  Someone@MAILINATOR.COM  '));
        $this->assertTrue($domains->isDisposable('someone@inbox.mailinator.com'));
        $this->assertFalse($domains->isDisposable('not-an-email'));
    }

    public function test_a_client_cannot_sign_up_with_a_disposable_address(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->client(' Ana@MAILINATOR.com '))
            ->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertSame(0, User::query()->count());
        Notification::assertNothingSent();
    }

    public function test_a_permanent_address_still_signs_up_and_is_normalised(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->client('  Ana@Example.COM '))
            ->assertSessionHasNoErrors();

        /* Nothing is created until the code comes back; the address it went to is normalised. */
        $this->assertSame('ana@example.com', mb_strtolower(PendingRegistration::get()['email'] ?? ''));
        $this->assertSame('Ana@example.com', PendingRegistration::get()['payload']['email'] ?? null);
    }

    public function test_an_invalid_address_is_still_refused(): void
    {
        $this->post(route('register.store'), $this->client('not-an-email'))
            ->assertSessionHasErrors('email');
    }

    public function test_an_account_cannot_change_its_email_to_a_disposable_one(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'me@yopmail.com'])
            ->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertNotSame('me@yopmail.com', $user->fresh()->email);
    }

    public function test_an_existing_account_on_a_disposable_domain_keeps_working(): void
    {
        /* Accounts are never removed for this; they can still save their profile. */
        $user = User::factory()->client()->create(['email' => 'old@mailinator.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'old@mailinator.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_the_rule_passes_permanent_addresses_and_refuses_disposable_ones(): void
    {
        $rule = new NotDisposableEmail;

        $this->assertTrue(Validator::make(['email' => 'a@gmail.com'], ['email' => [$rule]])->passes());
        $this->assertTrue(Validator::make(['email' => 'a@mailinator.com'], ['email' => [$rule]])->fails());
    }

    /**
     * @return array<string, mixed>
     */
    private function client(string $email): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => $email,
            'password' => 'a-Strong-password-2026',
            'password_confirmation' => 'a-Strong-password-2026',
            'role' => UserRole::Client->value,
            'business_name' => 'Zenith Solutions Group',
            'terms' => '1',
        ];
    }
}
