<?php

namespace App\Filament\Resources\JourneyDefinitions\RelationManagers;

use App\Modules\Journey\Enums\FieldType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Step')
                    ->columns(3)
                    ->components([
                        TextInput::make('key')
                            ->required()
                            ->helperText('Stable identifier, e.g. "employment-details".'),
                        TextInput::make('title')
                            ->required(),
                        TextInput::make('order')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Textarea::make('description')
                            ->helperText('Shown to the customer under the step title.')
                            ->columnSpanFull(),
                    ]),

                Repeater::make('fields')
                    ->relationship('fields')
                    ->orderColumn('order')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->addActionLabel('Add field')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->helperText('Matches this field to journey_responses, e.g. "monthly_income".'),
                        TextInput::make('label')
                            ->required(),
                        Select::make('type')
                            ->options(FieldType::class)
                            ->required()
                            ->live(),
                        TextInput::make('help_text'),
                        TextInput::make('order')
                            ->numeric()
                            ->default(0),
                        TagsInput::make('validation_rules')
                            ->helperText('Laravel validation rule strings, e.g. required, numeric.')
                            ->columnSpanFull(),

                        Repeater::make('options')
                            ->visible(fn ($get) => in_array($get('type'), [FieldType::Select->value, FieldType::Radio->value]))
                            ->schema([
                                TextInput::make('value')->required(),
                                TextInput::make('label')->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add option')
                            ->columnSpanFull(),

                        Section::make('Only show this field when…')
                            ->columns(3)
                            ->components([
                                TextInput::make('conditional_on.field')
                                    ->label('Field key'),
                                Select::make('conditional_on.operator')
                                    ->options(['=' => 'equals', '!=' => 'not equals', 'in' => 'is one of'])
                                    ->default('='),
                                TextInput::make('conditional_on.value')
                                    ->label('Value'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('order')->sortable(),
                TextColumn::make('key'),
                TextColumn::make('title'),
                TextColumn::make('fields_count')->counts('fields')->label('Fields')->alignCenter(),
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
