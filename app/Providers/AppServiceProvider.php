<?php

namespace App\Providers;

use App\Models\LoanProduct;
use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            CreditBureauProvider::class,
            fn () => $this->app->make(config('services.credit_bureau.provider')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(
            ['components.site.header', 'components.site.footer'],
            fn ($view) => $view->with('loanProducts', LoanProduct::query()->published()->orderBy('name')->get()),
        );

        // Generous enough for a real applicant working through a multi-step journey with
        // back navigation, tight enough to blunt scripted spam against the eligibility
        // and application flow. Uncapped under the test runner so a large suite hitting
        // these routes from the same client IP within one process doesn't self-throttle.
        RateLimiter::for('public-forms', fn (Request $request) => app()->runningUnitTests()
            ? Limit::none()
            : Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('contact-form', fn (Request $request) => app()->runningUnitTests()
            ? Limit::none()
            : Limit::perMinute(5)->by($request->ip()));
    }
}
