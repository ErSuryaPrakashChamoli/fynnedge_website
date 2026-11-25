<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\LeadInbox;
use App\Filament\Schemas\IndiaDateRangeFilter;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\Leads\Services\LeadInbox as LeadInboxQuery;
use App\Support\Admin\AdminLinks;
use Filament\Widgets\Widget;

/**
 * The newest leads from every source the user can see — the first rows of
 * the Lead Inbox, on the dashboard as an activity feed.
 */
class LatestLeadsWidget extends Widget
{
    protected static ?int $sort = -3;

    protected string $view = 'filament.widgets.latest-leads';

    public static function canView(): bool
    {
        return LeadSource::accessible() !== [];
    }

    /**
     * @return array<int, array{name: ?string, initials: string, phone: ?string, product: string, source: string, sourceColor: string, lms: string, lmsColor: string, ago: string, at: string, url: ?string}>
     */
    public function leads(): array
    {
        $sources = array_values(array_filter(LeadSource::accessible(), fn (LeadSource $source): bool => $source !== LeadSource::OtpVerification));

        if ($sources === []) {
            return [];
        }

        return LeadInboxQuery::query($sources)
            ->with('loanProduct')
            ->orderByDesc('received_at')
            ->limit(6)
            ->get()
            ->map(fn (LeadInboxEntry $lead): array => [
                'name' => $lead->name,
                'initials' => (string) str($lead->name ?: '?')->squish()->explode(' ')->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode(''),
                'phone' => $lead->phone,
                'product' => $lead->loanProduct?->name ?? 'General',
                'source' => $lead->source->getLabel(),
                'sourceColor' => $lead->source->getColor(),
                'lms' => $lead->syncStatus()->getLabel(),
                'lmsColor' => $lead->syncStatus()->getColor(),
                'ago' => $lead->received_at->diffForHumans(short: true),
                'at' => $lead->received_at->timezone(IndiaDateRangeFilter::TIMEZONE)->format('d M Y, h:i A'),
                'url' => $lead->recordUrl(),
            ])
            ->all();
    }

    public function inboxUrl(): ?string
    {
        return AdminLinks::to(LeadInbox::class);
    }
}
