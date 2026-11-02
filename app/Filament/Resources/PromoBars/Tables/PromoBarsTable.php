<?php

namespace App\Filament\Resources\PromoBars\Tables;

use App\Enums\PromoBarDevice;
use App\Enums\PublishStatus;
use App\Models\PromoBar;
use App\Support\PromoBars\PromoBars;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PromoBarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public'),
                TextColumn::make('name')
                    ->description(fn (PromoBar $record): string => $record->headline)
                    ->searchable(['name', 'headline'])
                    ->sortable(),
                TextColumn::make('placements')
                    ->label('Shown on')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PromoBars::label($state)),
                TextColumn::make('trigger')
                    ->label('Slides up')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('device')
                    ->label('Show on')
                    ->badge()
                    ->color(fn (PromoBarDevice $state): string => $state === PromoBarDevice::All ? 'gray' : 'info')
                    ->toggleable(isToggledHiddenByDefault: true),
                // Switches the bar on (Published) or off (Draft) from the list.
                // Not toggleable, so it always renders (see tables.md). Saved
                // without the form's validation, like a history restore; the
                // policy is checked because toggle columns skip it.
                ToggleColumn::make('active')
                    ->label('Active')
                    ->getStateUsing(fn (PromoBar $record): bool => $record->status === PublishStatus::Published)
                    ->updateStateUsing(function (PromoBar $record, mixed $state): bool {
                        $record->update(['status' => $state ? PublishStatus::Published : PublishStatus::Draft]);

                        return (bool) $state;
                    })
                    ->disabled(fn (PromoBar $record): bool => ! Auth::user()?->can('update', $record)),
                TextColumn::make('expires_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('No end date')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')->options(PublishStatus::class),
                SelectFilter::make('placements')
                    ->label('Page')
                    ->options(PromoBars::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->forPlacements([$data['value']])
                        : $query),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // A quick start for the next seasonal variant. The copy starts
                // as a draft so it never goes live alongside the original.
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes(['public_id'])
                    ->beforeReplicaSaved(function (PromoBar $replica): void {
                        $replica->name = "{$replica->name} (copy)";
                        $replica->status = PublishStatus::Draft;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
