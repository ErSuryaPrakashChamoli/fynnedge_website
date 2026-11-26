<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Debt Consolidation → Enquiries: cases that are still a short enquiry (no
 * detailed assessment submitted yet) — the call-back list. Every one is also
 * in Leads & Enquiries (same lead, one record).
 */
class DebtConsolidationEnquiries extends Page implements HasTable
{
    use BelongsToAdminModule, InteractsWithTable;

    protected string $view = 'filament.debt-consolidation.table-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoneArrowDownLeft;

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?string $title = 'Debt Consolidation Enquiries';

    protected static ?string $slug = 'debt-consolidation-enquiries';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:DebtApplication');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DebtApplication::query()->whereNull('submitted_at')->where('status', '!=', DebtApplicationStatus::Draft))
            ->columns([
                TextColumn::make('reference')->searchable()->weight('bold'),
                TextColumn::make('full_name')->label('Name')->searchable(),
                TextColumn::make('mobile')->formatStateUsing(fn (string $state): string => '+91 '.$state)->searchable(),
                TextColumn::make('applicant_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('declared_total_outstanding')->label('Total debt (declared)')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('declared_monthly_emi')->label('EMIs (declared)')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('assignee.name')->label('Advisor')->placeholder('Unassigned'),
                TextColumn::make('enquiry_submitted_at')->label('Received')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->sortable(),
            ])
            ->defaultSort('enquiry_submitted_at', 'desc')
            ->recordActions([Action::make('open')->label('Open case')->url(fn (DebtApplication $record): string => DebtApplicationResource::getUrl('view', ['record' => $record]))])
            ->recordUrl(fn (DebtApplication $record): string => DebtApplicationResource::getUrl('view', ['record' => $record]));
    }
}
