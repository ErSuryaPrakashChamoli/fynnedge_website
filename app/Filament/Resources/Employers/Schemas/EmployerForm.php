<?php

namespace App\Filament\Resources\Employers\Schemas;

use App\Modules\Eligibility\Models\EmployerCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EmployerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),

                Repeater::make('ratings')
                    ->relationship('ratings')
                    ->label('Category by lender')
                    ->helperText('Each lender defines and updates its own employer categories — set what this employer is rated as per lender.')
                    ->schema([
                        Select::make('lender_id')
                            ->relationship('lender', 'name')
                            ->required()
                            ->live()
                            ->searchable(),
                        Select::make('employer_category_id')
                            ->label('Category')
                            ->options(fn ($get) => EmployerCategory::query()
                                ->where('lender_id', $get('lender_id'))
                                ->pluck('label', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Add lender rating')
                    ->columnSpanFull(),
            ]);
    }
}
