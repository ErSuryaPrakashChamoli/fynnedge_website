<?php

namespace App\Filament\Widgets;

use App\Enums\LenderStatus;
use App\Enums\RateStatus;
use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RateBook;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * The state of the rate catalogue at a glance, on the dashboard and above
 * Catalog → Interest Rates: what is live (a progress ring), what needs
 * evidence, what is about to lapse, what is waiting for a publisher, and
 * which loan types currently make no rate claim at all because no lender
 * has a verified rate (status rows).
 */
class RateHealthOverview extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.rate-health';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('viewAny', LenderProductRate::class);
    }

    /**
     * @return array{live: int, offers: int, percent: int, liveUrl: string, rows: array<int, array{label: string, value: string, description: string, tone: string, url: string}>}
     */
    public function health(): array
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
            'live' => $offersWithLiveRate,
            'offers' => $activeOffers,
            'percent' => $activeOffers === 0 ? 0 : (int) min(100, round($offersWithLiveRate / $activeOffers * 100)),
            'liveUrl' => "{$listUrl}?tab=live",
            'rows' => [
                [
                    'label' => 'Awaiting approval',
                    'value' => (string) $pending,
                    'description' => 'Submitted by editors, not yet live',
                    'tone' => $pending > 0 ? 'warning' : 'gray',
                    'url' => "{$listUrl}?tab=pending",
                ],
                [
                    'label' => 'Need verification',
                    'value' => (string) $needsVerification,
                    'description' => 'Unverified, or last checked over '.LenderProductRate::REVERIFY_AFTER_DAYS.' days ago',
                    'tone' => $needsVerification > 0 ? 'danger' : 'success',
                    'url' => "{$listUrl}?tab=attention",
                ],
                [
                    'label' => 'Expiring soon / expired',
                    'value' => "{$expiringSoon} / {$expired}",
                    'description' => 'Validity or offer ends within '.LenderProductRate::EXPIRING_SOON_DAYS.' days / already lapsed',
                    'tone' => $expired > 0 ? 'danger' : ($expiringSoon > 0 ? 'warning' : 'success'),
                    'url' => "{$listUrl}?tab=expired",
                ],
                [
                    'label' => 'Loan types with no verified rate',
                    'value' => (string) $productsWithoutRate->count(),
                    'description' => $productsWithoutRate->isEmpty() ? 'Every published loan type has a "from X%" headline' : $productsWithoutRate->pluck('name')->implode(', '),
                    'tone' => $productsWithoutRate->isEmpty() ? 'success' : 'danger',
                    'url' => match ($productsWithoutRate->count()) {
                        // Nothing missing: show what is live. One loan type: its rates.
                        0 => "{$listUrl}?tab=live",
                        1 => LenderProductRateResource::getUrl('index', [
                            'tab' => 'all',
                            'filters' => ['loan_product' => ['value' => $productsWithoutRate->first()->id]],
                        ]),
                        default => "{$listUrl}?tab=attention",
                    },
                ],
            ],
        ];
    }
}
