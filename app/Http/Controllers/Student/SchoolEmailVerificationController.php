<?php

namespace App\Http\Controllers\Student;

use App\Enums\OneTimePasswordResult;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\StudentVerification;
use App\Services\Verification\OneTimePasswordService;
use App\Services\Verification\SchoolEmailVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Proving a student is a student, with a code mailed to their school address.
 *
 * Two steps, both here: ask for the address and mail a code, then read the
 * code back. The address is never written anywhere until the code returns
 * correct — the same shape as registration, and for the same reason. Somebody
 * who types a classmate's school address must not end up with it recorded
 * against their account.
 *
 * The domain is checked against the schools table, exactly, before a single
 * email goes out. That check is the entire strength of this route: it is what
 * makes the address one an institution issued rather than one that merely
 * looks academic.
 */
class SchoolEmailVerificationController extends Controller
{
    public function __construct(
        private readonly SchoolEmailVerifier $verifier,
        private readonly OneTimePasswordService $passwords,
    ) {}

    /**
     * Mail a code to the school address the student gave.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureAvailable();

        $email = mb_strtolower(trim((string) $request->input('email')));

        $request->merge(['email' => $email]);
        $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $school = $this->schoolFor($email);

        /*
         * Somebody else's proved address cannot be claimed twice. Without
         * this, two students could both verify off one school account — and
         * the second would silently take the first's evidence with them.
         */
        $this->ensureAddressIsFree($email, $request->user()->id);

        /*
         * A resend floor lives in the service. Surfacing it as a validation
         * error rather than a silent no-op means the button says why it did
         * nothing instead of looking broken.
         */
        $wait = $this->passwords->secondsUntilResend($email, $this->verifier->purpose());

        if ($wait > 0) {
            throw ValidationException::withMessages([
                'email' => "Wait {$wait} more seconds before asking for another code.",
            ]);
        }

        $this->passwords->send($email, $this->verifier->purpose());

        /*
         * The address rides in the session, not in a form field on the next
         * screen. A hidden input would let the code and the address be
         * submitted as an unrelated pair — prove one address, record another.
         */
        $request->session()->put('school_email.pending', [
            'email' => $email,
            'school_id' => $school->id,
        ]);

        return back()->with('success', "We sent a code to {$email}. It expires in "
            .config('otp.expires_after').' minutes.');
    }

    /**
     * Read the code back and record the verification.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureAvailable();

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $pending = $request->session()->get('school_email.pending');

        if (! is_array($pending) || ! isset($pending['email'], $pending['school_id'])) {
            throw ValidationException::withMessages([
                'code' => 'Ask for a code first.',
            ]);
        }

        $result = $this->passwords->check(
            (string) $pending['email'],
            $this->verifier->purpose(),
            (string) $request->string('code'),
        );

        if ($result !== OneTimePasswordResult::Valid) {
            throw ValidationException::withMessages(['code' => $result->message()]);
        }

        $school = School::findOrFail($pending['school_id']);

        /*
         * Re-checked on the way out, not only on the way in. The row could
         * have been claimed by somebody else in the minutes between asking
         * for the code and reading it back.
         */
        $this->ensureAddressIsFree((string) $pending['email'], $request->user()->id, 'code');

        $this->verifier->confirm($request->user(), (string) $pending['email'], $school);

        $request->session()->forget('school_email.pending');

        return back()->with('success', 'Your school email is verified.');
    }

    /**
     * Refuse the whole flow when this route is not switched on.
     *
     * A 404 rather than a redirect: while the verifier is unavailable this
     * feature does not exist, and the settings screen does not offer it
     * either. Same posture as the SheerID routes.
     */
    protected function ensureAvailable(): void
    {
        abort_unless($this->verifier->isAvailable(), HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * Resolve the school that issues addresses on this email's domain.
     */
    protected function schoolFor(string $email): School
    {
        $domain = mb_strtolower((string) mb_substr(mb_strrchr($email, '@') ?: '', 1));
        $school = School::forEmailDomain($domain);

        if ($school === null) {
            throw ValidationException::withMessages([
                'email' => 'That is not a school address we recognise. Use the address your school issued you, '
                    .'or upload your registration document instead.',
            ]);
        }

        return $school;
    }

    /**
     * Refuse an address already proved by somebody else.
     */
    protected function ensureAddressIsFree(string $email, int $userId, string $field = 'email'): void
    {
        $taken = StudentVerification::query()
            ->where('user_id', '!=', $userId)
            ->whereJsonContains('payload->email', $email)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                $field => 'That school address has already been used to verify another account.',
            ]);
        }
    }
}
