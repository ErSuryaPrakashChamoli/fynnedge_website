<?php

namespace App\Providers;

use App\Models\LoanProduct;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
