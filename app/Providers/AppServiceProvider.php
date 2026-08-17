<?php

namespace App\Providers;

use App\Contracts\VerifiesStudentCredentials;
use App\Services\Credentials\AutomatedCredentialVerifier;
use App\Services\Recommendation\ComputedRecommendationService;
use App\Services\Recommendation\RecommendationService;
use App\Services\Recommendation\StoredRecommendationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
