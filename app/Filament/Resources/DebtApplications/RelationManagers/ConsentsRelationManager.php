<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Modules\DebtConsolidation\Models\DebtConsent;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Every consent with its exact wording, version, hash, time and source.
 * Consents are never edited; withdrawing one is recorded with a reason.
 */
class ConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    protected static ?string $title = 'Consents';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('purpose')->badge(),
            TextEntry::make('lenderProduct.lender.name')->label('Lender')->placeholder('—'),
            TextEntry::make('consent_text')->label('Exact wording')->columnSpanFull(),
            TextEntry::make('text_version')->label('Version'),
            TextEntry::make('text_hash')->label('SHA-256')->fontFamily('mono'),
            TextEntry::make('method'),
            TextEntry::make('granted_at')->dateTime('j M Y, g:i:s a', 'Asia/Kolkata'),
            TextEntry::make('ip_address')->placeholder('—'),
            TextEntry::make('recorder.name')->label('Recorded by')->placeholder('Customer, online'),
            TextEntry::make('revoked_at')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->placeholder('Active'),
            TextEntry::make('revocation_reason')->placeholder('—'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purpose')->badge(),
                TextColumn::make('lenderProduct.lender.name')->label('Lender')->placeholder('—'),
                TextColumn::make('method')->badge()->color('gray'),
                TextColumn::make('granted_at')->dateTime('j M Y, g:i a', 'Asia/Kolkata'),
                TextColumn::make('revoked_at')->label('Withdrawn')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('revoke')
                    ->label('Record withdrawal')
                    ->color('danger')
                    ->visible(fn (DebtConsent $record): bool => $record->isActive() && (bool) auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(function (DebtConsent $record, array $data): void {
                        $record->update(['revoked_at' => now(), 'revocation_reason' => $data['reason']]);
                        $record->application->recordEvent('consent_withdrawn', ['consent' => $record->public_id, 'purpose' => $record->purpose->value], auth()->id());
                    }),
            ])
            ->defaultSort('granted_at', 'desc');
    }
}
