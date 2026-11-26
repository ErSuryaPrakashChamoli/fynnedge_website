<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Enums\VerificationStatus;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CollateralsRelationManager extends RelationManager
{
    protected static string $relationship = 'collaterals';

    protected static ?string $title = 'Collateral';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('collateral_type')->options(CollateralType::class)->required(),
            TextInput::make('description')->maxLength(255),
            TextInput::make('owner_name')->maxLength(150),
            Select::make('ownership')->options(['self' => 'Self', 'joint' => 'Joint', 'family' => 'Family', 'business' => 'Business']),
            TextInput::make('estimated_value')->numeric()->prefix('₹')->required()->minValue(1),
            TextInput::make('existing_charge_amount')->label('Loans already secured on it')->numeric()->prefix('₹')->default(0),
            TextInput::make('city')->maxLength(100),
            TextInput::make('pincode')->maxLength(6),
            Select::make('verification_status')->options(VerificationStatus::class)->default(VerificationStatus::Unverified->value),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('collateral_type')->label('Type')->badge(),
                TextColumn::make('description')->placeholder('—'),
                TextColumn::make('estimated_value')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->alignEnd(),
                TextColumn::make('existing_charge_amount')->label('Already secured')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->alignEnd(),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('verification_status')->label('Verified')->badge(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }
}
