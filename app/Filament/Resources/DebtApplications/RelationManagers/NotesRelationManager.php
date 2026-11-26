<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Modules\DebtConsolidation\Models\DebtNote;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Communication log, internal remarks and follow-ups. Notes are never edited
 * or deleted — a correction is a new note.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = 'Communication';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(ViewDebtApplication::noteSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('j M Y, g:i a', 'Asia/Kolkata'),
                TextColumn::make('type')->badge()->color('gray'),
                TextColumn::make('direction')->placeholder('—'),
                TextColumn::make('subject')->placeholder('—')->description(fn (DebtNote $record): string => str($record->body)->limit(140)->toString())->wrap(),
                TextColumn::make('author.name')->label('By')->placeholder('System'),
                TextColumn::make('follow_up_at')->label('Follow up')->dateTime('j M, g:i a', 'Asia/Kolkata')->placeholder('—')
                    ->color(fn (DebtNote $record): ?string => $record->follow_up_at && ! $record->completed_at && $record->follow_up_at->isPast() ? 'danger' : null),
                IconColumn::make('completed_at')->label('Done')->boolean()->state(fn (DebtNote $record): bool => $record->completed_at !== null),
                IconColumn::make('visible_to_customer')->label('Customer sees')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Log communication')->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()]),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Mark done')
                    ->icon('heroicon-o-check')
                    ->visible(fn (DebtNote $record): bool => $record->follow_up_at !== null && $record->completed_at === null && (bool) auth()->user()?->can('update', $record))
                    ->action(function (DebtNote $record): void {
                        abort_unless((bool) auth()->user()?->can('update', $record), 403);
                        $record->update(['completed_at' => now()]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
