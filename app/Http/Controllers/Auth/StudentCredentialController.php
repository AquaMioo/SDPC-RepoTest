<?php

namespace App\Http\Controllers\Auth;

use App\Enums\CredentialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreStudentCredentialRequest;
use App\Jobs\VerifyStudentCredential;
use App\Models\StudentCredential;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StudentCredentialController extends Controller
{
    /**
     * The private disk credential documents are written to.
     */
    private const DISK = 'local';

    /**
     * Show the credential submission screen.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless((bool) $user?->requiresCredentialVerification(), HttpResponse::HTTP_FORBIDDEN);

        $credential = $this->latestSubmission($user);

        return Inertia::render('auth/credentials', [
            'schools' => array_values((array) config('schools.list', [])),
            'submission' => $credential === null ? null : [
                'school' => $credential->school,
                'fileName' => $credential->original_name,
                'status' => $credential->status->value,
                'statusLabel' => $credential->status->label(),
                'reason' => $credential->reason,
                'submittedAt' => $credential->created_at?->diffForHumans(),
                'canResubmit' => $credential->status->allowsResubmission(),
            ],
        ]);
    }

    /**
     * Store the uploaded document and queue the automated checks.
     */
    public function store(StoreStudentCredentialRequest $request): RedirectResponse
    {
        $user = $request->user();
        $existing = $this->latestSubmission($user);

        // A submission that is still being checked, or already settled, is not
        // replaced silently — only an explicit rejection reopens the form.
        if ($existing !== null && ! $existing->status->allowsResubmission()) {
            return back()->withErrors([
                'document' => __('Your last submission is still being processed.'),
            ]);
        }

        /** @var UploadedFile $document */
        $document = $request->file('document');

        $path = $document->store('student-credentials/'.$user->id, self::DISK);

        if ($path === false) {
            return back()->withErrors([
                'document' => __('The document could not be stored. Please try again.'),
            ]);
        }

        $credential = new StudentCredential;

        $credential->forceFill([
            'user_id' => $user->id,
            'school' => $request->validated('school'),
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $document->getClientOriginalName(),
            'mime_type' => $document->getClientMimeType(),
            'size' => $document->getSize() ?: 0,
            'checksum' => (string) hash_file('sha256', $document->getRealPath()),
            'status' => CredentialStatus::Pending,
        ])->save();

        VerifyStudentCredential::dispatch($credential);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Credentials submitted. We are checking them now.'),
        ]);

        return back();
    }

    /**
     * Read the student's most recent submission straight from the database.
     *
     * Deliberately a query rather than the relation accessor: the accessor
     * caches its result on the model instance, which would go stale between
     * the check and a later read within the same instance's lifetime.
     */
    private function latestSubmission(User $user): ?StudentCredential
    {
        return $user->studentCredentials()->latest('id')->first();
    }
}
