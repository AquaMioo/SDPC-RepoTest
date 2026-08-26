<?php

namespace App\Providers;

use App\Contracts\StudentVerifier;
use App\Contracts\VerifiesStudentCredentials;
use App\Mail\Transport\BrevoTransport;
use App\Services\Credentials\AutomatedCredentialVerifier;
use App\Services\Recommendation\ComputedRecommendationService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\StoredRecommendationService;
use App\Services\Verification\NullStudentVerifier;
use App\Services\Verification\SheerIdStudentVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
            default => $this->app->make(ComputedRecommendationService::class),
        });

        // Swap this binding to route credential checks at a real verification
        // provider. Everything else — controller, job, UI — stays as is.
        $this->app->bind(VerifiesStudentCredentials::class, AutomatedCredentialVerifier::class);

        /*
         * The optional third-party enrolment check. Null unless SheerID is
         * both switched on and actually configured, which it is not by
         * default — and nothing on the platform is gated on it either way.
         * See config/sheerid.php.
         */
        $this->app->bind(StudentVerifier::class, fn () => config('sheerid.enabled')
            ? $this->app->make(SheerIdStudentVerifier::class)
            : $this->app->make(NullStudentVerifier::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerBrevoTransport();
    }

    /**
     * Teach the mail manager the `brevo` transport.
     *
     * The deploy host blocks outbound SMTP, so mail leaves over HTTPS or
     * not at all. Registered here rather than pulled in as a package —
     * App\Mail\Transport\BrevoTransport is a hundred lines against
     * Laravel's own Http client.
     */
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
