<?php

namespace App\Services\Credentials;

use App\Contracts\VerifiesStudentCredentials;
use App\Enums\CredentialStatus;
use App\Models\School;
use App\Models\StudentCredential;
use App\Support\Credentials\VerificationResult;
use Illuminate\Support\Facades\Storage;

/**
 * The default verifier.
 *
 * IMPORTANT: this cannot tell a genuine enrollment document from a convincing
 * forgery — no service is wired up that could. What it does do is refuse the
 * submissions that are mechanically wrong (unreadable file, unknown school,
 * a document already used by another account) and hand everything that
 * survives to an administrator, who makes the real decision on the admin
 * User page. Point the VerifiesStudentCredentials binding at a real provider
 * to upgrade this without touching anything else.
 */
class AutomatedCredentialVerifier implements VerifiesStudentCredentials
{
    /**
     * Documents larger than this are refused outright.
     */
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function verify(StudentCredential $credential): VerificationResult
    {
        $checks = [];

        $checks[] = $exists = $this->check(
            'file_stored',
            Storage::disk($credential->disk)->exists($credential->path),
            'The uploaded document was found in storage.',
            'The uploaded document could not be read back.',
        );

        $checks[] = $mime = $this->check(
            'file_type',
            in_array($credential->mime_type, self::ALLOWED_MIME_TYPES, strict: true),
            'The document is an accepted image or PDF.',
            'Only JPG, PNG, WEBP or PDF documents are accepted.',
        );

        $checks[] = $size = $this->check(
            'file_size',
            $credential->size > 0 && $credential->size <= self::MAX_BYTES,
            'The document is within the accepted size.',
            'The document is empty or larger than 8 MB.',
        );

        $checks[] = $school = $this->check(
            'known_school',
            in_array($credential->school, School::names(), strict: true),
            'The selected school is recognised.',
            'The selected school is not on the recognised list.',
        );

        $checks[] = $unique = $this->check(
            'not_reused',
            ! $this->isReused($credential),
            'This document has not been submitted by another account.',
            'This exact document has already been submitted by another account.',
        );

        foreach ([$exists, $mime, $size, $school, $unique] as $result) {
            if (! $result['passed']) {
                return VerificationResult::failed($checks, $result['message']);
            }
        }

        return VerificationResult::passed($checks);
    }

    /**
     * Determine if the identical file was already submitted by someone else.
     */
    private function isReused(StudentCredential $credential): bool
    {
        return StudentCredential::query()
            ->where('checksum', $credential->checksum)
            ->whereKeyNot($credential->getKey())
            ->where('user_id', '!=', $credential->user_id)
            ->whereIn('status', [
                CredentialStatus::Pending->value,
                CredentialStatus::NeedsReview->value,
                CredentialStatus::Verified->value,
            ])
            ->exists();
    }

    /**
     * @return array{check: string, passed: bool, message: string}
     */
    private function check(string $name, bool $passed, string $whenPassed, string $whenFailed): array
    {
        return [
            'check' => $name,
            'passed' => $passed,
            'message' => $passed ? $whenPassed : $whenFailed,
        ];
    }
}
