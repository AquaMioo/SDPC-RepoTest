<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTP API instead of SMTP.
 *
 * WHY THIS EXISTS. The host blocks outbound SMTP entirely — 587, 465 and
 * 2525 all time out from inside the container, which is standard practice on
 * shared hosting to stop it being used as a spam relay. Port 443 is open. So
 * no SMTP provider can work here, whatever the credentials, and mail has to
 * leave over HTTPS.
 *
 * Brevo rather than Resend because Resend verifies domains and this project
 * has none of its own; Brevo verifies a single sender address, so mail can go
 * out as the project's own Gmail without buying a domain first.
 *
 * Written against Laravel's Http client rather than pulling in an SDK, the
 * same choice config/sheerid.php documents for its provider: one fewer
 * dependency to install on a deploy host, and the payload below is the whole
 * of the API surface this application uses.
 */
class BrevoTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $key,
        private readonly string $endpoint,
        private readonly int $timeout,
    ) {
        parent::__construct();
    }

    /**
     * Hand the message to Brevo.
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = Http::asJson()
            ->withHeaders([
                'api-key' => $this->key,
                'accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->endpoint, $this->payload($email));

        /*
         * Throwing keeps the failover mailer honest: config/mail.php lists
         * [brevo, log], and Symfony only falls through to the next transport
         * when this one raises. Swallow the error and a dead provider would
         * look like a delivered message.
         */
        if ($response->failed()) {
            throw new TransportException(sprintf(
                'Brevo rejected the message (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }
    }

    /**
     * Build Brevo's transactional payload from the Symfony message.
     *
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;

        $payload = [
            'sender' => $from === null ? null : $this->address($from),
            'to' => $this->addresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        if (($html = $email->getHtmlBody()) !== null) {
            $payload['htmlContent'] = is_string($html) ? $html : stream_get_contents($html);
        }

        if (($text = $email->getTextBody()) !== null) {
            $payload['textContent'] = is_string($text) ? $text : stream_get_contents($text);
        }

        /* Brevo rejects a message with neither body, so never send one. */
        if (! isset($payload['htmlContent']) && ! isset($payload['textContent'])) {
            $payload['textContent'] = '';
        }

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc(), 'replyTo' => $email->getReplyTo()] as $field => $list) {
            if ($list !== []) {
                $payload[$field] = $field === 'replyTo'
                    ? $this->address($list[0])
                    : $this->addresses($list);
            }
        }

        $attachments = $this->attachments($email);

        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array<string, string>>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): array => $this->address($address), $addresses);
    }

    /**
     * @return array<string, string>
     */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $attachments[] = [
                'name' => $headers->getHeaderParameter('content-disposition', 'filename') ?? 'attachment',
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }

    /**
     * The name the mailer reports itself by, for logs and exceptions.
     */
    public function __toString(): string
    {
        return 'brevo';
    }
}
