<?php

namespace App\Filament\Resources\DebtApplications\Tables;

use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\SuitabilityBand;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DebtApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->copyable()->weight('bold'),
                TextColumn::make('name')
                    ->label('Customer / business')
                    ->state(fn (DebtApplication $record): string => $record->displayName())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query) => $query
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('mobile', $search)
                        ->orWhereHas('financialProfile', fn (Builder $query) => $query->where('business_name', 'like', "%{$search}%")))),
                TextColumn::make('applicant_type')->label('Type')->badge()->color('gray'),
                TextColumn::make('status')->badge(),
                TextColumn::make('latestSnapshot.total_outstanding')->label('Total debt')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('latestSnapshot.suitability_band')->label('Suitability')->badge()->placeholder('Not assessed'),
                TextColumn::make('assignee.name')->label('Advisor')->placeholder('Unassigned'),
                TextColumn::make('follow_up_at')->label('Follow-up')->dateTime('j M, g:i a', 'Asia/Kolkata')->placeholder('—')->sortable()
                    ->color(fn (DebtApplication $record): ?string => $record->follow_up_at?->isPast() ? 'danger' : null),
                TextColumn::make('submitted_at')->label('Submitted')->since()->placeholder('Enquiry only')->sortable(),
                TextColumn::make('created_at')->label('Received')->dateTime('j M Y', 'Asia/Kolkata')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(DebtApplicationStatus::class)->multiple(),
                SelectFilter::make('applicant_type')->label('Type')->options(ApplicantType::class),
                SelectFilter::make('assigned_to')->label('Advisor')->relationship('assignee', 'name'),
                Filter::make('mine')->label('Assigned to me')->query(fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())),
                Filter::make('follow_up_due')->label('Follow-up due')->query(fn (Builder $query): Builder => $query->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now()->endOfDay())),
                SelectFilter::make('suitability')->label('Suitability')->options(SuitabilityBand::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value']) ? $query->whereHas('latestSnapshot', fn (Builder $query) => $query->where('suitability_band', $data['value'])) : $query),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (DebtApplication $record): string => route('filament.admin.resources.debt-applications.view', $record));
    }
}
