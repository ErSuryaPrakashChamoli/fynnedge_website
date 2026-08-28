<?php

namespace App\Providers;

use App\Models\LoanProduct;
use App\Modules\CreditBureau\Contracts\CreditBureauProvider;
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
    }
}
