<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\OneTimePasswordPurpose;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\TeamInvitation;
use App\Services\Verification\OneTimePasswordService;
use App\Support\PendingGoogleRegistration;
use App\Support\PendingRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\RegisterResponse;

/**
 * Client and student registration, behind an emailed code.
 *
 * This replaces Fortify's registration feature, which is switched off in
 * config/fortify.php so these route names are free. The reason for owning it
 * is the order of events: Fortify creates the account and signs the person in
 * on the same request, and there is no seam in that where an address can be
 * proved first.
 *
 * So nothing is created until the code comes back. The validated form waits in
 * the session (App\Support\PendingRegistration) and the User row is written by
 * the same CreateNewUser action Fortify would have called — one request later,
 * to an address somebody has demonstrably opened.
 *
 * The admin portal is untouched by all of this. Administrator accounts are
 * created by the developers and sign in through routes/admin.php, which never
 * reaches this controller.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly OneTimePasswordService $passwords,
        private readonly CreateNewUser $createUser,
    ) {}

    /**
     * Show the sign up form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canLoginWithGoogle' => (bool) config('services.google.enabled'),
            'googleSetupHint' => $this->shouldHintAtGoogleSetup(),
            'roles' => UserRole::selfAssignable(),
            'teamInvitation' => $this->teamInvitation($request),
            // Set once someone has come back from Google without an account
            // yet: the form prefills from it and drops the password fields.
            'googleProfile' => PendingGoogleRegistration::get(),
        ]);
    }

    /**
     * Take the sign up and send a code to the address on it.
     *
     * A Google identity skips the code outright — Google has already proved
     * the address, and asking somebody to check the inbox we were just handed
     * proof of is theatre.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        if (PendingGoogleRegistration::exists()) {
            return $this->completeRegistration($request->validated());
        }

        $payload = $request->validated();

        /*
         * validated() drops password_confirmation, since it is not a rule key
         * of its own. CreateNewUser validates the payload again on the far
         * side of the code — it is the Fortify contract and does not assume it
         * was called from here — and its `confirmed` rule would fail without
         * it, so it rides along.
         */
        $payload['password_confirmation'] = $request->input('password_confirmation');

        $email = mb_strtolower(trim($payload['email']));

        /*
         * A different address than last time means the earlier code is now
         * pointed at nobody, so it is dropped rather than left standing.
         */
        $previous = PendingRegistration::email();

        if ($previous !== null && $previous !== $email) {
            $this->passwords->forget($previous, OneTimePasswordPurpose::Registration);
        }

        PendingRegistration::put($payload, $email);

        /*
         * A false return means one went out moments ago and is still valid, so
         * there is nothing to do but show the screen it is typed into.
         */
        $this->passwords->send($email, OneTimePasswordPurpose::Registration);

        return to_route('register.verify');
    }

    /**
     * Show the screen the code is typed into.
     */
    public function verify(): Response|RedirectResponse
    {
        $pending = PendingRegistration::get();

        if ($pending === null) {
            return to_route('register');
        }

        return Inertia::render('auth/verify-otp', [
            'email' => $pending['email'],
            'codeLength' => (int) config('otp.length'),
            'expiresAfter' => (int) config('otp.expires_after'),
            'secondsUntilResend' => $this->passwords->secondsUntilResend(
                $pending['email'],
                OneTimePasswordPurpose::Registration,
            ),
        ]);
    }

    /**
     * Check the code and, if it holds, create the account.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $pending = PendingRegistration::get();

        if ($pending === null) {
            return to_route('register');
        }

        $request->validate(['code' => ['required', 'string']]);

        $result = $this->passwords->check(
            $pending['email'],
            OneTimePasswordPurpose::Registration,
            (string) $request->input('code'),
        );

        if (! $result->isValid()) {
            throw ValidationException::withMessages(['code' => [$result->message()]]);
        }

        return $this->completeRegistration($pending['payload']);
    }

    /**
     * Send another code, unless one went out moments ago.
     */
    public function resend(): RedirectResponse
    {
        $email = PendingRegistration::email();

        if ($email === null) {
            return to_route('register');
        }

        $sent = $this->passwords->send($email, OneTimePasswordPurpose::Registration);

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('A new code is on its way.')]
            : ['type' => 'error', 'message' => __('A code was just sent. Give it a moment before asking for another.')],
        );

        return back();
    }

    /**
     * Abandon the sign up and go back to the form.
     *
     * Used by the "wrong address?" link. The code is dropped with it, so it
     * cannot be typed into a later attempt against the same address.
     */
    public function cancel(): RedirectResponse
    {
        $email = PendingRegistration::email();

        if ($email !== null) {
            $this->passwords->forget($email, OneTimePasswordPurpose::Registration);
        }

        PendingRegistration::forget();

        return to_route('register');
    }

    /**
     * Create the account, sign them in, and send them where they belong.
     *
     * The response is Fortify's own, so the team-scoped redirect and the
     * administrator's separate landing page keep working exactly as they did.
     *
     * @param  array<string, mixed>  $payload
     */
    private function completeRegistration(array $payload): RedirectResponse
    {
        $user = $this->createUser->create($payload);

        PendingRegistration::forget();

        event(new Registered($user));

        Auth::login($user);

        request()->session()->regenerate();

        $response = app(RegisterResponse::class)->toResponse(request());

        return $response instanceof RedirectResponse
            ? $response
            : redirect()->intended((string) config('fortify.home'));
    }

    /**
     * Get the pending team invitation context for the sign up screen.
     *
     * @return array{code: string, teamName: string}|null
     */
    private function teamInvitation(Request $request): ?array
    {
        $invitationCode = $request->query('invitation');

        if (! is_string($invitationCode)) {
            return null;
        }

        $invitation = TeamInvitation::query()
            ->with('team')
            ->where('code', $invitationCode)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            return null;
        }

        return [
            'code' => $invitation->code,
            'teamName' => $invitation->team->name,
        ];
    }

    /**
     * Determine if the sign up screen should explain why Google is missing.
     *
     * Only ever true outside production: end users should simply not see the
     * button, while a developer gets told what is unset.
     */
    private function shouldHintAtGoogleSetup(): bool
    {
        return ! config('services.google.enabled') && ! app()->isProduction();
    }
}
