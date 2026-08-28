<?php

namespace Tests\Feature\Mail;

use App\Enums\OneTimePasswordPurpose;
use App\Notifications\Auth\EmailOneTimePassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * The registration code leaves over HTTPS, because the deploy host blocks
 * outbound SMTP on every port. These pin the payload Brevo is handed and the
 * failure behaviour the failover mailer depends on.
 */
class BrevoTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'brevo',
            'mail.from.address' => 'sender@sdpc.test',
            'mail.from.name' => 'SDPC',
            'services.brevo.key' => 'test-key',
            'services.brevo.endpoint' => 'https://api.brevo.com/v3/smtp/email',
            'services.brevo.timeout' => 10,
        ]);
    }

    public function test_the_registration_code_is_posted_to_brevos_api(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201),
        ]);

        Notification::route('mail', 'student@example.test')
            ->notify(new EmailOneTimePassword('285020', OneTimePasswordPurpose::Registration));

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-key')
                && $body['sender']['email'] === 'sender@sdpc.test'
                && $body['to'][0]['email'] === 'student@example.test'
                && $body['subject'] === 'Confirm your email address'
                /* The code itself has to survive the trip. */
                && str_contains($body['htmlContent'], '285020');
        });
    }

    public function test_a_rejected_message_raises_so_the_failover_mailer_can_catch_it(): void
    {
        Http::fake([
            'api.brevo.com/*' => Http::response(['message' => 'sender not verified'], 400),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('sender not verified');

        Notification::route('mail', 'student@example.test')
            ->notify(new EmailOneTimePassword('285020', OneTimePasswordPurpose::Registration));
    }

    public function test_registration_still_completes_when_brevo_is_down(): void
    {
        config(['mail.default' => 'failover']);

        Http::fake([
            'api.brevo.com/*' => Http::response(['message' => 'upstream is down'], 503),
        ]);

        /*
         * The whole point of [brevo, log]: the person is sitting on the verify
         * screen, so a provider outage must not become a 500 for them. The
         * code goes to the log instead and somebody can read it out.
         */
        Notification::route('mail', 'student@example.test')
            ->notify(new EmailOneTimePassword('285020', OneTimePasswordPurpose::Registration));

        $this->assertTrue(true, 'The send fell through to the log rather than throwing.');
    }
}
