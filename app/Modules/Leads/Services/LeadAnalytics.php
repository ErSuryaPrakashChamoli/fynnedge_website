<?php

namespace App\Modules\Leads\Services;

use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use App\Support\UrlPath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind Leads & Enquiries → Lead Analytics, computed live from the
 * lead tables (via LeadInbox), mobile_otp_challenges and page-view events.
 *
 * Honesty rules, enforced here rather than in the view:
 *  - A conversion rate is only produced when page views were actually
 *    recorded for that page in the period; otherwise it is null ("—").
 *  - A lead with no recorded page is grouped under "Not recorded", never
 *    guessed. Credit score checks and OTP verifications record no page.
 *  - Campaign (UTM) figures exist only for sources that store UTM tags
 *    (online/loan applications and page views); enquiries do not.
 *  - OTP verifications are reported as their own funnel, not added to the
 *    lead total (they mostly precede an enquiry and would double count).
 */
final class LeadAnalytics
{
    public const NOT_RECORDED = 'Not recorded';

    /**
     * @param  array<int, LeadSource>  $sources
     * @return array<string, mixed>
     */
    public static function report(CarbonImmutable $from, CarbonImmutable $to, array $sources, ?int $loanProductId = null, ?string $campaign = null, string $granularity = 'day'): array
    {
        $leadSources = array_values(array_filter($sources, fn (LeadSource $source): bool => $source !== LeadSource::OtpVerification));
        $leads = self::leads($from, $to, $leadSources, $loanProductId, $campaign);
        $previousTo = $from->subSecond();
        $previous = self::leads($previousTo->subSeconds((int) $from->diffInSeconds($to)), $previousTo, $leadSources, $loanProductId, $campaign);
        $views = self::pageViews($from, $to, $loanProductId, $campaign);
        $trackingSince = self::trackingSince();

        return [
            'kpis' => self::kpis($leads, $previous, $views),
            'otp' => in_array(LeadSource::OtpVerification, $sources, true) ? self::otpFunnel($from, $to) : null,
            'pages' => self::pages($leads, $views),
            'forms' => self::groupBy($leads, fn (LeadInboxEntry $lead): string => $lead->source === LeadSource::Enquiry ? ($lead->detailLabel() ?? 'Enquiry') : $lead->source->getLabel()),
            'products' => self::groupBy($leads, fn (LeadInboxEntry $lead): string => $lead->loanProduct?->name ?? 'General / no product'),
            'campaigns' => self::campaigns($leads, $views),
            'trend' => self::trend($leads, $from, $to, $granularity),
            'insights' => self::insights($leads, $views, $trackingSince),
            'tracking_since' => $trackingSince?->timezone('Asia/Kolkata')->format('d M Y'),
            'views_total' => (int) $views->sum('views'),
        ];
    }

    /**
     * @param  array<int, LeadSource>  $sources
     * @return Collection<int, LeadInboxEntry>
     */
    public static function leads(CarbonImmutable $from, CarbonImmutable $to, array $sources, ?int $loanProductId, ?string $campaign): Collection
    {
        if ($sources === []) {
            return collect();
        }

        return LeadInbox::query($sources)
            ->with('loanProduct')
            ->whereBetween('received_at', [$from->utc(), $to->utc()])
            ->when($loanProductId, fn (Builder $query, int $id) => $query->where('loan_product_id', $id))
            ->when(filled($campaign), fn (Builder $query) => $query->where('utm_campaign', $campaign))
            ->get();
    }

    /**
     * The page a lead is credited to. For an application the landing URL is the
     * /apply step itself, so the page that linked to it (an internal referrer)
     * is the one that earned it.
     */
    public static function pageOf(LeadInboxEntry $lead): string
    {
        $page = $lead->source_page;

        if ($page !== null && str_ends_with((string) parse_url($page, PHP_URL_PATH), '/apply') && $lead->referrer && self::isInternal($lead->referrer)) {
            $page = $lead->referrer;
        }

        return $page === null ? self::NOT_RECORDED : UrlPath::normalize($page);
    }

    /**
     * Page views per path in the window.
     *
     * @return Collection<int, object{path: string, views: int}>
     */
    public static function pageViews(CarbonImmutable $from, CarbonImmutable $to, ?int $loanProductId = null, ?string $campaign = null): Collection
    {
        $path = self::json('properties', 'path');

        return AnalyticsEvent::query()
            ->where('event_key', AnalyticsEventKey::PageView)
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->when($loanProductId, fn (Builder $query, int $id) => $query->where('loan_product_id', $id))
            ->when(filled($campaign), fn (Builder $query) => $query->whereRaw(self::json('properties', 'utm_campaign').' = ?', [$campaign]))
            ->toBase()
            ->selectRaw("{$path} as path, COUNT(*) as views")
            ->groupByRaw($path)
            ->get()
            ->map(fn (object $row): object => (object) ['path' => (string) $row->path, 'views' => (int) $row->views]);
    }

    public static function trackingSince(): ?CarbonImmutable
    {
        $first = AnalyticsEvent::query()->where('event_key', AnalyticsEventKey::PageView)->min('created_at');

        return $first ? CarbonImmutable::parse($first) : null;
    }

    /**
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @param  Collection<int, LeadInboxEntry>  $previous
     * @param  Collection<int, object>  $views
     * @return array<int, array{label: string, value: string, previous: ?string, change: ?float, hint: string}>
     */
    private static function kpis(Collection $leads, Collection $previous, Collection $views): array
    {
        $verified = $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->verified_at !== null)->count();
        $delivered = $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->syncStatus()->isDelivered())->count();
        $repeats = $leads->filter(fn (LeadInboxEntry $lead): bool => $lead->source === LeadSource::Enquiry && $lead->repeat_count > 1)->count();
        $viewCount = (int) $views->sum('views');

        return [
            ['label' => 'Leads', 'value' => number_format($leads->count()), 'previous' => number_format($previous->count()), 'change' => self::change($leads->count(), $previous->count()), 'hint' => 'Enquiries, credit score checks and applications with a mobile number.'],
            ['label' => 'Mobile verified', 'value' => self::percent($verified, $leads->count()) ?? '—', 'previous' => null, 'change' => null, 'hint' => "{$verified} of {$leads->count()} leads verified their number by OTP."],
            ['label' => 'Repeat enquiries', 'value' => number_format($repeats), 'previous' => null, 'change' => null, 'hint' => 'Enquiries where the same number asked again about the same product (merged, not duplicated).'],
            ['label' => 'Credit score checks', 'value' => number_format($leads->where('source', LeadSource::CreditScoreCheck)->count()), 'previous' => number_format($previous->where('source', LeadSource::CreditScoreCheck)->count()), 'change' => self::change($leads->where('source', LeadSource::CreditScoreCheck)->count(), $previous->where('source', LeadSource::CreditScoreCheck)->count()), 'hint' => 'Free score checks on the public bureau pages.'],
            ['label' => 'Delivered to FynnOn LMS', 'value' => self::percent($delivered, $leads->count()) ?? '—', 'previous' => null, 'change' => null, 'hint' => "{$delivered} of {$leads->count()} leads confirmed by the LMS."],
            ['label' => 'Page views', 'value' => $viewCount > 0 ? number_format($viewCount) : 'Not tracked yet', 'previous' => null, 'change' => null, 'hint' => 'Counted on the website since page-view tracking was switched on; bots excluded.'],
        ];
    }

    /**
     * @return array{requested: int, verified: int, failed: int, rate: ?string, by_purpose: array<int, array{label: string, requested: int, verified: int, rate: ?string}>}
     */
    private static function otpFunnel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = MobileOtpChallenge::query()
            ->whereBetween('created_at', [$from->utc(), $to->utc()])
            ->where(fn (Builder $query) => $query->whereNull('purpose')->orWhere('purpose', '!=', MobileOtpPurpose::CustomerLogin->value))
            ->toBase()
            ->selectRaw('purpose, status, COUNT(*) as aggregate')
            ->groupBy('purpose', 'status')
            ->get();

        $verifiedStatus = MobileOtpStatus::Verified->value;
        $failedStatuses = [MobileOtpStatus::Exhausted->value, MobileOtpStatus::Failed->value, MobileOtpStatus::Expired->value];
        $requested = (int) $rows->sum('aggregate');
        $verified = (int) $rows->where('status', $verifiedStatus)->sum('aggregate');

        return [
            'requested' => $requested,
            'verified' => $verified,
            'failed' => (int) $rows->whereIn('status', $failedStatuses)->sum('aggregate'),
            'rate' => self::percent($verified, $requested),
            'by_purpose' => $rows->groupBy('purpose')->map(fn (Collection $group, string $purpose): array => [
                'label' => MobileOtpPurpose::tryFrom($purpose)?->getLabel() ?? self::NOT_RECORDED,
                'requested' => (int) $group->sum('aggregate'),
                'verified' => (int) $group->where('status', $verifiedStatus)->sum('aggregate'),
                'rate' => self::percent((int) $group->where('status', $verifiedStatus)->sum('aggregate'), (int) $group->sum('aggregate')),
            ])->sortByDesc('requested')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @param  Collection<int, object>  $views
     * @return array<int, array{page: string, views: ?int, leads: int, verified: int, verified_rate: ?string, delivered: int, failed: int, conversion: ?string}>
     */
    private static function pages(Collection $leads, Collection $views): array
    {
        $viewsByPath = $views->pluck('views', 'path');
        $byPage = $leads->groupBy(fn (LeadInboxEntry $lead): string => self::pageOf($lead));

        $paths = $byPage->keys()->merge($viewsByPath->keys())->unique();

        return $paths->map(function (string $path) use ($byPage, $viewsByPath): array {
            $pageLeads = $byPage->get($path, collect());
            $views = $viewsByPath->has($path) ? (int) $viewsByPath->get($path) : null;
            $verified = $pageLeads->filter(fn (LeadInboxEntry $lead): bool => $lead->verified_at !== null)->count();

            return [
                'page' => $path,
                'views' => $views,
                'leads' => $pageLeads->count(),
                'verified' => $verified,
                'verified_rate' => self::percent($verified, $pageLeads->count()),
                'delivered' => $pageLeads->filter(fn (LeadInboxEntry $lead): bool => $lead->syncStatus()->isDelivered())->count(),
                'failed' => $pageLeads->filter(fn (LeadInboxEntry $lead): bool => $lead->syncStatus() === FynnOnSyncStatus::Abandoned)->count(),
                'conversion' => $views ? self::percent($pageLeads->count(), $views) : null,
            ];
        })
            ->sortBy([['leads', 'desc'], ['views', 'desc']])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @param  callable(LeadInboxEntry): string  $key
     * @return array<int, array{label: string, leads: int, verified: int, share: string}>
     */
    private static function groupBy(Collection $leads, callable $key): array
    {
        $total = max(1, $leads->count());

        return $leads->groupBy($key)
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'leads' => $group->count(),
                'verified' => $group->filter(fn (LeadInboxEntry $lead): bool => $lead->verified_at !== null)->count(),
                'share' => (string) self::percent($group->count(), $total),
            ])
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @param  Collection<int, object>  $views
     * @return array<int, array{campaign: string, source: ?string, leads: int, verified: int}>
     */
    private static function campaigns(Collection $leads, Collection $views): array
    {
        return $leads
            ->filter(fn (LeadInboxEntry $lead): bool => filled($lead->utm_campaign) || filled($lead->utm_source))
            ->groupBy(fn (LeadInboxEntry $lead): string => ($lead->utm_campaign ?: '(no campaign name)').'|'.($lead->utm_source ?? ''))
            ->map(function (Collection $group, string $key): array {
                [$campaign, $source] = explode('|', $key, 2);

                return [
                    'campaign' => $campaign,
                    'source' => $source !== '' ? $source : null,
                    'leads' => $group->count(),
                    'verified' => $group->filter(fn (LeadInboxEntry $lead): bool => $lead->verified_at !== null)->count(),
                ];
            })
            ->sortByDesc('leads')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @return array<int, array{label: string, total: int, by_source: array<string, int>}>
     */
    private static function trend(Collection $leads, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $format = match ($granularity) {
            'week' => 'o-\WW',
            'month' => 'Y-m',
            default => 'Y-m-d',
        };
        $label = match ($granularity) {
            'week' => fn (CarbonImmutable $date): string => 'Week of '.$date->startOfWeek()->format('d M'),
            'month' => fn (CarbonImmutable $date): string => $date->format('M Y'),
            default => fn (CarbonImmutable $date): string => $date->format('d M'),
        };

        $buckets = [];
        $cursor = $from->timezone('Asia/Kolkata');
        $end = $to->timezone('Asia/Kolkata');

        while ($cursor <= $end && count($buckets) < 400) {
            $buckets[$cursor->format($format)] ??= ['label' => $label($cursor), 'total' => 0, 'by_source' => []];
            $cursor = $cursor->addDay();
        }

        foreach ($leads as $lead) {
            $key = CarbonImmutable::parse($lead->received_at)->timezone('Asia/Kolkata')->format($format);

            if (isset($buckets[$key])) {
                $buckets[$key]['total']++;
                $buckets[$key]['by_source'][$lead->source->value] = ($buckets[$key]['by_source'][$lead->source->value] ?? 0) + 1;
            }
        }

        return array_values($buckets);
    }

    /**
     * Plain statements of fact for the period; each is only made when the data
     * behind it exists.
     *
     * @param  Collection<int, LeadInboxEntry>  $leads
     * @param  Collection<int, object>  $views
     * @return array<int, array{tone: string, text: string}>
     */
    private static function insights(Collection $leads, Collection $views, ?CarbonImmutable $trackingSince): array
    {
        $insights = [];
        $pages = collect(self::pages($leads, $views));
        $recorded = $pages->reject(fn (array $page): bool => $page['page'] === self::NOT_RECORDED);

        if (($top = $recorded->sortByDesc('leads')->first()) && $top['leads'] > 0) {
            $insights[] = ['tone' => 'success', 'text' => "Top lead page: {$top['page']} with {$top['leads']} lead(s)".($top['conversion'] ? " ({$top['conversion']} of its views)." : '.')];
        }

        if ($trackingSince === null) {
            $insights[] = ['tone' => 'info', 'text' => 'Page views are not tracked yet, so no conversion rates can be shown. They start counting automatically as visitors open pages.'];
        } else {
            $noLeads = $recorded->filter(fn (array $page): bool => ($page['views'] ?? 0) >= 50 && $page['leads'] === 0)->sortByDesc('views')->take(3);

            foreach ($noLeads as $page) {
                $insights[] = ['tone' => 'warning', 'text' => "{$page['page']} had {$page['views']} views but no leads — a candidate for a clearer call-to-action or enquiry form."];
            }

            $best = $recorded->filter(fn (array $page): bool => ($page['views'] ?? 0) >= 50 && $page['leads'] >= 3)->sortByDesc(fn (array $page): float => $page['leads'] / max(1, $page['views']))->first();

            if ($best) {
                $insights[] = ['tone' => 'success', 'text' => "Best converting page: {$best['page']} ({$best['conversion']} of {$best['views']} views became leads) — worth promoting further."];
            }
        }

        foreach ($recorded->filter(fn (array $page): bool => $page['leads'] >= 5 && ($page['verified'] / $page['leads']) < 0.5)->take(3) as $page) {
            $insights[] = ['tone' => 'warning', 'text' => "{$page['page']}: only {$page['verified_rate']} of its {$page['leads']} leads verified their mobile number."];
        }

        if (($failed = $pages->sum('failed')) > 0) {
            $insights[] = ['tone' => 'danger', 'text' => "{$failed} lead(s) in this period could not be delivered to FynnOn LMS — resend them from FynnOn LMS Sync."];
        }

        $unrecorded = $pages->firstWhere('page', self::NOT_RECORDED);

        if ($unrecorded && $unrecorded['leads'] > 0) {
            $insights[] = ['tone' => 'info', 'text' => "{$unrecorded['leads']} lead(s) have no recorded page (credit score checks and older records do not store one); they are listed as \"".self::NOT_RECORDED.'".'];
        }

        if (($campaign = collect(self::campaigns($leads, $views))->first()) !== null) {
            $insights[] = ['tone' => 'success', 'text' => "Top campaign: {$campaign['campaign']}".($campaign['source'] ? " ({$campaign['source']})" : '')." with {$campaign['leads']} lead(s)."];
        }

        return $insights;
    }

    /**
     * Rows for the page performance CSV.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @return array<int, array<int, mixed>>
     */
    public static function pageRows(array $pages): array
    {
        return array_map(fn (array $page): array => [
            $page['page'], $page['views'] ?? '', $page['leads'], $page['verified'], $page['verified_rate'] ?? '', $page['delivered'], $page['failed'], $page['conversion'] ?? '',
        ], $pages);
    }

    /**
     * @return array<int, string>
     */
    public static function productOptions(): array
    {
        return LoanProduct::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public static function campaignOptions(): array
    {
        return DB::table('journey_sessions')->whereNotNull('utm_campaign')->distinct()->orderBy('utm_campaign')->limit(200)->pluck('utm_campaign')->all();
    }

    private static function json(string $column, string $key): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract({$column}, '$.{$key}')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$key}'))";
    }

    private static function isInternal(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === '' || $host === $appHost || $host === 'www.'.$appHost || 'www.'.$host === $appHost;
    }

    private static function percent(int $part, int $whole): ?string
    {
        return $whole > 0 ? round($part / $whole * 100, 1).'%' : null;
    }

    private static function change(int $current, int $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }
}
