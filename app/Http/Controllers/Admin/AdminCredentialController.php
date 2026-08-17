<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CredentialStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewCredentialRequest;
use App\Models\StudentCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCredentialController extends Controller
{
    /**
     * Show every credential submission, waiting ones first.
     */
    public function index(): Response
    {
        $credentials = StudentCredential::query()
            ->with(['user:id,name,email,role,status', 'user.studentVerifications'])
            ->latest('id')
            ->get()
            ->sortBy(fn (StudentCredential $credential): int => match ($credential->status) {
                CredentialStatus::NeedsReview => 0,
                CredentialStatus::Pending => 1,
                default => 2,
            })
            ->values()
            ->map(fn (StudentCredential $credential): array => [
                'id' => $credential->id,
                'student' => [
                    'id' => $credential->user?->id,
                    'name' => $credential->user?->name,
                    'email' => $credential->user?->email,
                    'accountStatus' => $credential->user?->status->value,
                ],
                'school' => $credential->school,
                'fileName' => $credential->original_name,
                'fileSize' => $this->humanSize($credential->size),
                'status' => $credential->status->value,
                'statusLabel' => $credential->status->label(),
                'reason' => $credential->reason,
                'checks' => $credential->checks ?? [],
                'submittedAt' => $credential->created_at?->diffForHumans(),
                'reviewedAt' => $credential->reviewed_at?->diffForHumans(),
                'awaitingDecision' => $credential->status === CredentialStatus::NeedsReview,
                /*
                 * Supporting evidence, and nothing more. The decision on this
                 * page is still the administrator's: a SheerID pass does not
                 * approve an account and its absence does not refuse one.
                 */
                'thirdPartyVerification' => $this->thirdPartyEvidence($credential),
            ])
            ->all();

        return Inertia::render('admin/credentials', [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Summarise any third-party check held against the submitting student.
     *
     * Read-only on this screen. It is one more thing a reviewer can weigh, in
     * the same way the automated file checks are — not an answer.
     *
     * @return array<string, string|null>|null
     */
    protected function thirdPartyEvidence(StudentCredential $credential): ?array
    {
        $verification = $credential->user?->studentVerifications
            ->firstWhere('provider', VerificationProvider::SheerId);

        if ($verification === null) {
            return null;
        }

        return [
            'provider' => $verification->provider->label(),
            'statusLabel' => $verification->status->label(),
            'verifiedAt' => $verification->verified_at?->toFormattedDateString(),
        ];
    }

    /**
     * Stream the stored document to the reviewing administrator.
     *
     * The file lives on a private disk and is never linked directly. It is only
     * ever readable through this route, which sits behind auth + role:admin.
     */
    public function document(StudentCredential $credential): StreamedResponse
    {
        $disk = Storage::disk($credential->disk);

        abort_unless($disk->exists($credential->path), HttpResponse::HTTP_NOT_FOUND);

        return $disk->download($credential->path, $credential->original_name);
    }

    /**
     * Record the administrator's decision.
     */
    public function update(ReviewCredentialRequest $request, StudentCredential $credential): RedirectResponse
    {
        if ($credential->status->isFinal()) {
            return back()->withErrors([
                'decision' => __('This submission has already been settled.'),
            ]);
        }

        $decision = $request->decision();

        $credential->forceFill([
            'status' => $decision,
            'reason' => $request->validated('reason'),
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ])->save();

        // A verified credential is what approves the student's account. A
        // rejection leaves the account untouched so they can submit again.
        if ($decision === CredentialStatus::Verified && $credential->user !== null) {
            $credential->user->forceFill(['status' => UserStatus::Approved])->save();
        }

        Inertia::flash('toast', [
            'type' => $decision === CredentialStatus::Verified ? 'success' : 'error',
            'message' => $decision === CredentialStatus::Verified
                ? __('Credential verified and the account approved.')
                : __('Credential rejected. The student can submit a new document.'),
        ]);

        return back();
    }

    /**
     * Render a byte count in a form a person can read.
     */
    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
