<?php

namespace App\Filament\Resources\EligibilityRuleSets\RelationManagers;

use App\Modules\Eligibility\Enums\RuleLogic;
use App\Modules\Eligibility\Enums\RuleOperator;
use App\Modules\Eligibility\Enums\RulePriority;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule')
                    ->columns(4)
                    ->components([
                        TextInput::make('label')
                            ->required()
                            ->columnSpan(2),
                        Select::make('priority')
                            ->options(RulePriority::class)
                            ->default(RulePriority::Mandatory)
                            ->required(),
                        Select::make('logic')
                            ->label('Combine conditions with')
                            ->options(RuleLogic::class)
                            ->default(RuleLogic::And)
                            ->required(),
                        TextInput::make('customer_message')
                            ->label('Customer-facing message')
                            ->helperText('Shown to the applicant explaining this specific check, pass or fail.')
                            ->columnSpanFull(),
                        TextInput::make('order')
                            ->numeric()
                            ->default(0),
                    ]),

                Repeater::make('conditions')
                    ->relationship('conditions')
                    ->schema([
                        TextInput::make('attribute')
                            ->required()
                            ->helperText('age, total_monthly_income, city, employer_category, foir, credit_score — or any journey field key.'),
                        Select::make('operator')
                            ->options(RuleOperator::class)
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->required()
                            ->afterStateHydrated(function ($component, $state) {
                                $component->state(is_array($state) ? implode(', ', $state) : (string) ($state ?? ''));
                            })
                            ->dehydrateStateUsing(function (?string $state, callable $get) {
                                $multi = in_array($get('operator'), ['in', 'not_in', 'between'], true);
                                $parts = array_values(array_filter(
                                    array_map('trim', explode(',', (string) $state)),
                                    fn ($part) => $part !== '',
                                ));
                                $cast = fn ($value) => is_numeric($value) ? (float) $value : $value;

                                return $multi ? array_map($cast, $parts) : $cast($parts[0] ?? $state);
                            })
                            ->helperText(fn ($get) => in_array($get('operator'), ['in', 'not_in', 'between'], true)
                                ? 'Comma-separated, e.g. Delhi, Mumbai'
                                : 'Single value, e.g. 50000'),
                        TextInput::make('order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(4)
                    ->addActionLabel('Add condition')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('order')->sortable(),
                TextColumn::make('label'),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (RulePriority $state) => match ($state) {
                        RulePriority::Mandatory => 'danger',
                        RulePriority::Preferred => 'success',
                        RulePriority::Warning => 'warning',
                    }),
                TextColumn::make('logic')->badge(),
                TextColumn::make('conditions_count')->counts('conditions')->label('Conditions')->alignCenter(),
            ])
            ->defaultSort('order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->reorderable('order');
    }
}
