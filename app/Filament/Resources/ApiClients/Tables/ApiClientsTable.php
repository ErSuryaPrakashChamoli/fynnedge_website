<?php

namespace App\Filament\Resources\ApiClients\Tables;

use App\Filament\Resources\ApiClients\ApiClientActions;
use App\Models\ApiClient;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ApiClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key_prefix')
                    ->label('Key')
                    ->state(fn (ApiClient $record): string => $record->maskedKey())
                    ->fontFamily('mono'),
                TextColumn::make('user.roles.name')
                    ->label('Role')
                    ->badge()
                    ->placeholder('No role'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (ApiClient $record): string => $record->isActive() ? 'Active' : 'Revoked')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                TextColumn::make('allowed_ips')
                    ->label('Allowed IPs')
                    ->badge()
                    ->placeholder('Any'),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('revoked_at')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Revoked')
                    ->queries(
                        true: fn ($query) => $query->whereNull('revoked_at'),
                        false: fn ($query) => $query->whereNotNull('revoked_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ApiClientActions::generateKey(),
                    ApiClientActions::revoke(),
                ]),
            ]);
    }
}
