<?php

namespace App\Services\Verification;

use App\Contracts\StudentVerifier;
use App\Enums\VerificationProvider;
use App\Enums\VerificationStatus;
use App\Models\StudentVerification;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SheerID, spoken to with Laravel's own HTTP client and nothing else.
 *
 * Only reachable when config('sheerid.enabled') is true and the programme id
 * and token are both set; otherwise AppServiceProvider binds
 * NullStudentVerifier instead. Nobody on the platform is gated on the answer —
 * see the StudentVerifier contract for why that is deliberate.
 *
 * Every failure here is swallowed into a log line and a null. A verification
 * service being down must not stop a student using the platform, because the
 * platform never needed the service in the first place.
 */
class SheerIdStudentVerifier implements StudentVerifier
{
    /**
     * SheerID's own step names, mapped onto the statuses we keep.
     *
     * The provider reports progress through a flow rather than a verdict, so
     * anything that is neither a success nor a refusal is still in flight.
     */
    private const SUCCESS_STEPS = ['success', 'approved'];

    private const FAILURE_STEPS = ['error', 'rejected', 'docReviewRejected'];

    /**
     * Determine if the verifier is configured well enough to be offered.
     */
    public function isAvailable(): bool
    {
        return (bool) config('sheerid.enabled')
            && filled(config('sheerid.program_id'))
            && filled(config('sheerid.access_token'));
    }

    /**
     * Open a verification for the given student.
     */
    public function start(User $student): ?StudentVerification
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $payload = $this->post('/verification', [
            'programId' => config('sheerid.program_id'),
        ]);

        $externalId = $payload['verificationId'] ?? null;

        if (! is_string($externalId)) {
            return null;
        }

        return StudentVerification::updateOrCreate(
            [
                'user_id' => $student->id,
                'provider' => VerificationProvider::SheerId,
            ],
            [
                'status' => $this->statusFor($payload),
                'external_id' => $externalId,
                'redirect_url' => $this->hostedUrlFor($externalId),
                'verified_at' => null,
                'failure_reason' => null,
                'payload' => $payload,
            ],
        );
    }

    /**
     * Bring a verification up to date with the provider's current answer.
     */
    public function refresh(StudentVerification $verification): StudentVerification
    {
        if (! $this->isAvailable() || blank($verification->external_id)) {
            return $verification;
        }

        $payload = $this->get('/verification/'.$verification->external_id);

        if ($payload === []) {
            return $verification;
        }

        $status = $this->statusFor($payload);

        $verification->update([
            'status' => $status,
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
            'failure_reason' => $status === VerificationStatus::Rejected
                ? (is_string($payload['errorIds'][0] ?? null) ? $payload['errorIds'][0] : 'Verification was not accepted.')
                : null,
            'payload' => $payload,
        ]);

        return $verification->refresh();
    }

    /**
     * Read the provider's flow step as one of our statuses.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function statusFor(array $payload): VerificationStatus
    {
        $step = is_string($payload['currentStep'] ?? null) ? $payload['currentStep'] : '';

        return match (true) {
            in_array($step, self::SUCCESS_STEPS, true) => VerificationStatus::Verified,
            in_array($step, self::FAILURE_STEPS, true) => VerificationStatus::Rejected,
            default => VerificationStatus::Pending,
        };
    }

    /**
     * Build the hosted page the student finishes the check on.
     */
    protected function hostedUrlFor(string $externalId): string
    {
        return rtrim((string) config('sheerid.hosted_url'), '/')
            .'/'.config('sheerid.program_id')
            .'/?verificationId='.$externalId;
    }

    /**
     * Send a POST and return the decoded body, or an empty array on any fault.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function post(string $path, array $data): array
    {
        try {
            $response = $this->request()->post($this->url($path), $data);
        } catch (ConnectionException $exception) {
            return $this->logFailure($path, $exception->getMessage());
        }

        return $response->successful()
            ? (array) $response->json()
            : $this->logFailure($path, 'HTTP '.$response->status());
    }

    /**
     * Send a GET and return the decoded body, or an empty array on any fault.
     *
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        try {
            $response = $this->request()->get($this->url($path));
        } catch (ConnectionException $exception) {
            return $this->logFailure($path, $exception->getMessage());
        }

        return $response->successful()
            ? (array) $response->json()
            : $this->logFailure($path, 'HTTP '.$response->status());
    }

    /**
     * Get a pending request carrying the programme's token.
     */
    protected function request(): PendingRequest
    {
        return Http::withToken((string) config('sheerid.access_token'))
            ->acceptJson()
            ->timeout((int) config('sheerid.timeout', 10));
    }

    /**
     * Build a full endpoint URL.
     */
    protected function url(string $path): string
    {
        return rtrim((string) config('sheerid.base_url'), '/').$path;
    }

    /**
     * Record why a call did not produce an answer, and carry on without one.
     *
     * @return array<string, mixed>
     */
    protected function logFailure(string $path, string $reason): array
    {
        Log::warning('SheerID verification call failed.', [
            'path' => $path,
            'reason' => $reason,
        ]);

        return [];
    }
}
