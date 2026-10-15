<?php

namespace App\Filament\Widgets;

use App\Enums\LenderStatus;
use App\Enums\RateStatus;
use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RateBook;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * The state of the rate catalogue at a glance, on the dashboard and above
 * Catalog → Interest Rates: what is live, what needs evidence, what is about
 * to lapse, what is waiting for a publisher, and which loan types currently
 * make no rate claim at all because no lender has a verified rate.
 */
class RateHealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Interest rates';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('viewAny', LenderProductRate::class);
    }

    protected function getStats(): array
    {
        $activeOffers = LenderProduct::query()
            ->where('status', LenderStatus::Active)
            ->whereHas('lender', fn ($lender) => $lender->where('status', LenderStatus::Active))
            ->count();

        $offersWithLiveRate = RateBook::instance()->live()->pluck('lenderProductId')->unique()->count();

        $pending = LenderProductRate::query()->where('status', RateStatus::PendingApproval)->count();
        $needsVerification = LenderProductRate::query()->needsVerification()->count();
        $expiringSoon = LenderProductRate::query()->expiringSoon()->count();
        $expired = LenderProductRate::query()->expired()->count();

        $productsWithoutRate = LoanProduct::query()
            ->published()
            ->orderedForDisplay()
            ->get()
            ->reject(fn (LoanProduct $product) => RateBook::headlineFor($product) !== null)
            ->values();

        $listUrl = LenderProductRateResource::getUrl('index');

        return [
            Stat::make('Offers with a live verified rate', "{$offersWithLiveRate} / {$activeOffers}")
                ->description('Active lender offers; the rest show "Check current rates"')
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->color($offersWithLiveRate === $activeOffers ? 'success' : 'warning')
                ->url("{$listUrl}?tab=live"),
            Stat::make('Awaiting approval', $pending)
                ->description('Submitted by editors, not yet live')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url("{$listUrl}?tab=pending"),
            Stat::make('Need verification', $needsVerification)
                ->description('Unverified, or last checked over '.LenderProductRate::REVERIFY_AFTER_DAYS.' days ago')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($needsVerification > 0 ? 'danger' : 'success')
                ->url("{$listUrl}?tab=attention"),
            Stat::make('Expiring soon / expired', "{$expiringSoon} / {$expired}")
                ->description('Validity or offer ends within '.LenderProductRate::EXPIRING_SOON_DAYS.' days / already lapsed')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($expired > 0 ? 'danger' : ($expiringSoon > 0 ? 'warning' : 'success'))
                ->url("{$listUrl}?tab=expired"),
            Stat::make('Loan types with no verified rate', $productsWithoutRate->count())
                ->description($productsWithoutRate->isEmpty() ? 'Every published loan type has a "from X%" headline' : $productsWithoutRate->pluck('name')->implode(', '))
                ->descriptionIcon(Heroicon::OutlinedNoSymbol)
                ->color($productsWithoutRate->isEmpty() ? 'success' : 'danger')
                ->url(match ($productsWithoutRate->count()) {
                    // Nothing missing: show what is live. One loan type: its rates.
                    0 => "{$listUrl}?tab=live",
                    1 => LenderProductRateResource::getUrl('index', [
                        'tab' => 'all',
                        'filters' => ['loan_product' => ['value' => $productsWithoutRate->first()->id]],
                    ]),
                    default => "{$listUrl}?tab=attention",
                }),
        ];
    }
}
