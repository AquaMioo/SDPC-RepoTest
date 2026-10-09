<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\GcashNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Settings → GCash account: registered once by a client or student for the
 * Project Extension Addendum, stored encrypted and only ever shown masked.
 */
class GcashAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_registers_a_number_typed_any_common_way(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->put(route('gcash.update'), ['gcash_number' => '+63 917-123-4297'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('09171234297', $student->refresh()->gcash_number);
        $this->assertSame('09******297', $student->maskedGcashNumber());

        /* Encrypted at rest. */
        $this->assertStringNotContainsString('09171234297', (string) DB::table('users')->where('id', $student->id)->value('gcash_number'));
    }

    public function test_settings_shows_it_masked_and_never_sends_the_number(): void
    {
        $client = User::factory()->client()->create();
        $client->forceFill(['gcash_number' => '09171234297'])->save();

        $response = $this->actingAs($client)->get(route('profile.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('gcash.masked', '09******297')
            ->missing('auth.user.gcash_number'));

        $this->assertStringNotContainsString('09171234297', $response->getContent());
    }

    public function test_a_number_that_is_not_a_gcash_mobile_number_is_refused(): void
    {
        $student = User::factory()->student()->create();

        foreach (['0917123', '08171234567', '091712345678', 'my gcash'] as $wrong) {
            $this->actingAs($student)
                ->put(route('gcash.update'), ['gcash_number' => $wrong])
                ->assertSessionHasErrors('gcash_number');
        }

        $this->assertNull($student->refresh()->gcash_number);
    }

    public function test_the_number_can_be_removed(): void
    {
        $student = User::factory()->student()->create();
        $student->forceFill(['gcash_number' => '09171234297'])->save();

        $this->actingAs($student)->delete(route('gcash.destroy'))->assertRedirect();

        $this->assertNull($student->refresh()->gcash_number);
        $this->assertFalse($student->hasGcashAccount());
    }

    public function test_only_clients_and_students_register_one(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gcash.update'), ['gcash_number' => '09171234297'])
            ->assertForbidden();
    }

    public function test_masking_keeps_the_first_two_and_last_three_digits(): void
    {
        $this->assertSame('09******297', GcashNumber::mask('09171234297'));
        $this->assertNull(GcashNumber::mask(null));
        $this->assertSame('09171234297', GcashNumber::normalize('0917 123 4297'));
        $this->assertSame('09171234297', GcashNumber::normalize('639171234297'));
    }
}
