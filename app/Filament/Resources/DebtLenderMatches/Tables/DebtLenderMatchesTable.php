<?php

namespace App\Filament\Resources\DebtLenderMatches\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Enums\AdvisorReviewStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Models\DebtLenderMatch;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DebtLenderMatchesTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtLenderMatch $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=matching') : null,
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->description(fn (DebtLenderMatch $record): ?string => $record->lenderProduct?->loanProduct?->name),
                TextColumn::make('match_status')->label('Match')->badge(),
                TextColumn::make('reasons')->label('Why')->wrap()
                    ->state(fn (DebtLenderMatch $record) => new HtmlString(collect($record->reasons ?? [])->map(fn (array $reason): string => ($reason['passed'] ? '✓ ' : '✗ ').e($reason['message']))
                        ->merge(collect($record->missing_information ?? [])->map(fn (string $missing): string => '? Missing: '.e($missing)))->implode('<br>')))->html(),
                TextColumn::make('indicative_loan_amount')->label('Indicative loan')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextColumn::make('indicative_rate_min')->label('Rate')->formatStateUsing(fn ($state, DebtLenderMatch $record): string => DebtPresenter::percent($state).($record->indicative_rate_max ? ' – '.DebtPresenter::percent($record->indicative_rate_max) : ''))->placeholder('—'),
                TextColumn::make('eligibility_status')->label('Lender rules')->badge()->placeholder('No rule set'),
                TextColumn::make('advisor_status')->label('Advisor')->badge()->description(fn (DebtLenderMatch $record): ?string => $record->advisor_note),
                TextColumn::make('evaluated_at')->label('Checked')->since(),
            ])))
            ->filters([
                SelectFilter::make('match_status')->options(LenderMatchStatus::class),
                SelectFilter::make('advisor_status')->options(AdvisorReviewStatus::class),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (DebtLenderMatch $record): bool => (bool) auth()->user()?->can('update', $record))
                    ->fillForm(fn (DebtLenderMatch $record): array => ['advisor_status' => $record->advisor_status, 'advisor_note' => $record->advisor_note])
                    ->schema([
                        Select::make('advisor_status')->options(AdvisorReviewStatus::class)->required(),
                        Textarea::make('advisor_note')->label('Reasoning')->required()->maxLength(1000),
                    ])
                    ->action(fn (DebtLenderMatch $record, array $data) => $record->update([...$data, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])),
            ])
            ->defaultSort('match_status');
    }
}
