<?php

namespace App\Filament\Resources\EligibilityRuleSets\Tables;

use App\Modules\Eligibility\Actions\PublishEligibilityRuleSet;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EligibilityRuleSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->searchable(),
                TextColumn::make('lenderProduct.loanProduct.name')->label('Product')->searchable(),
                TextColumn::make('version')->sortable(),
                TextColumn::make('rules_count')->counts('rules')->label('Rules')->alignCenter(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EligibilityRuleSetStatus $state) => match ($state) {
                        EligibilityRuleSetStatus::Active => 'success',
                        EligibilityRuleSetStatus::Draft => 'warning',
                        EligibilityRuleSetStatus::Archived => 'gray',
                    }),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_until')->date(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(EligibilityRuleSetStatus::class),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (EligibilityRuleSet $record) => $record->status === EligibilityRuleSetStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('This makes it the active rule set for this lender product and archives whichever version was active before.')
                    ->action(function (EligibilityRuleSet $record) {
                        $errors = app(PublishEligibilityRuleSet::class)->handle($record);

                        if ($errors !== []) {
                            Notification::make()
                                ->title('Could not publish')
                                ->body(implode("\n", $errors))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Rule set published')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
