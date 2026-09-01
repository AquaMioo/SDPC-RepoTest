<?php

namespace App\Providers;

use App\Contracts\StudentVerifier;
use App\Contracts\VerifiesStudentCredentials;
use App\Listeners\ClearLastSeen;
use App\Mail\Transport\BrevoTransport;
use App\Services\Credentials\AutomatedCredentialVerifier;
use App\Services\Recommendation\ComputedRecommendationService;
use App\Services\Recommendation\GeminiRecommendationService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\StoredRecommendationService;
use App\Services\Verification\NullStudentVerifier;
use App\Services\Verification\SchoolEmailVerifier;
use App\Services\Verification\SheerIdStudentVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * Matching runs through this one binding, so both modules stay unaware
         * of how a score was arrived at. See config/recommendations.php.
         */
        $this->app->bind(RecommendationService::class, fn () => match (config('recommendations.driver')) {
            'stored' => $this->app->make(StoredRecommendationService::class),
            'gemini' => $this->app->make(GeminiRecommendationService::class),
            default => $this->app->make(ComputedRecommendationService::class),
        });

        // Swap this binding to route credential checks at a real verification
        // provider. Everything else — controller, job, UI — stays as is.
        $this->app->bind(VerifiesStudentCredentials::class, AutomatedCredentialVerifier::class);

        /*
         * How a student proves they are a student.
         *
         * School email first: it is the only one of the three that both works
         * and can be switched on without a vendor agreement. SheerID stays
         * reachable for whenever that account arrives, and Null is the
         * shipped default.
         *
         * AVAILABILITY IS THE SWITCH. Whichever of these is bound,
         * User::hasPassedStudentVerification() returns true for EVERYONE while
         * it reports itself unavailable — so an unconfigured install gates
         * nothing, exactly as the platform behaves today, and turning one on
         * closes the door for every student who has not verified yet.
         * See config/verification.php.
         */
        $this->app->bind(StudentVerifier::class, function () {
            $schoolEmail = $this->app->make(SchoolEmailVerifier::class);

            if ($schoolEmail->isAvailable()) {
                return $schoolEmail;
            }

            return config('sheerid.enabled')
                ? $this->app->make(SheerIdStudentVerifier::class)
                : $this->app->make(NullStudentVerifier::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerBrevoTransport();
        $this->registerPresenceListeners();
    }

    /**
     * Teach the mail manager the `brevo` transport.
     *
     * The deploy host blocks outbound SMTP, so mail leaves over HTTPS or
     * not at all. Registered here rather than pulled in as a package —
     * App\Mail\Transport\BrevoTransport is a hundred lines against
     * Laravel's own Http client.
     */

    /**
     * Signing out clears the presence stamp.
     *
     * Registered explicitly rather than left to event discovery, so the one
     * thing standing between a signed-out account and a green dot is visible
     * from the provider rather than implied by a file's location.
     */
    protected function registerPresenceListeners(): void
    {
        Event::listen(Logout::class, ClearLastSeen::class);
    }

    protected function registerBrevoTransport(): void
    {
        Mail::extend('brevo', function (array $config): BrevoTransport {
            return new BrevoTransport(
                (string) ($config['key'] ?? config('services.brevo.key')),
                (string) config('services.brevo.endpoint'),
                (int) config('services.brevo.timeout'),
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Every generated URL goes out as https in production, so a link in an
        // email or a redirect can never drop someone onto plain http where the
        // session cookie would travel in the clear. Left alone locally, where
        // there is no certificate.
        URL::forceHttps(app()->isProduction());

        // A model that quietly ignores an unknown attribute hides a typo until
        // it becomes a bug. Fail loudly outside production instead.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
