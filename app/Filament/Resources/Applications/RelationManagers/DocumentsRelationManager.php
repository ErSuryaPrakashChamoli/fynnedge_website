<?php

namespace App\Filament\Resources\Applications\RelationManagers;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\ApplicationDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                TextColumn::make('documentType.label')->label('Document'),
                TextColumn::make('original_filename')->label('File'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (DocumentStatus $state) => match ($state) {
                        DocumentStatus::Uploaded => 'warning',
                        DocumentStatus::Verified => 'success',
                        DocumentStatus::Rejected => 'danger',
                    }),
                TextColumn::make('rejection_reason')->wrap()->toggleable(),
                TextColumn::make('uploaded_at')->dateTime(),
            ])
            ->defaultSort('created_at')
            ->headerActions([])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ApplicationDocument $record) => $record->status !== DocumentStatus::Verified)
                    ->action(fn (ApplicationDocument $record) => $record->update([
                        'status' => DocumentStatus::Verified,
                        'rejection_reason' => null,
                        'verified_at' => now(),
                    ])),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ApplicationDocument $record) => $record->status !== DocumentStatus::Rejected)
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(fn (ApplicationDocument $record, array $data) => $record->update([
                        'status' => DocumentStatus::Rejected,
                        'rejection_reason' => $data['rejection_reason'],
                        'verified_at' => null,
                    ])),
            ])
            ->toolbarActions([]);
    }
}
