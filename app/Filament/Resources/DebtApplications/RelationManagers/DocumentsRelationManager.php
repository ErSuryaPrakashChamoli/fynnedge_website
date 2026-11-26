<?php

namespace App\Filament\Resources\DebtApplications\RelationManagers;

use App\Modules\Applications\Enums\DocumentStatus;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\DebtConsolidation\Actions\ReviewDebtDocument;
use App\Modules\DebtConsolidation\Actions\UploadDebtDocument;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Case documents: uploaded by the customer (portal) or staff, encrypted at
 * rest, read by people only. Staff verify or reject each one; a replacement
 * becomes a new version and the old one is kept.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Document')->state(fn (DebtDocument $record): string => $record->title())->description(fn (DebtDocument $record): ?string => $record->liability?->lender_name),
                TextColumn::make('version')->prefix('v')->description(fn (DebtDocument $record): ?string => $record->isCurrent() ? null : 'Replaced'),
                TextColumn::make('status')->badge()->color(fn (DocumentStatus $state): string => match ($state) {
                    DocumentStatus::Verified => 'success',
                    DocumentStatus::Rejected => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('rejection_reason')->wrap()->placeholder('—'),
                TextColumn::make('uploaded_by_type')->label('From')->badge()->color('gray'),
                TextColumn::make('size')->formatStateUsing(fn (int $state): string => number_format($state / 1024, 0).' KB'),
                TextColumn::make('created_at')->label('Uploaded')->dateTime('j M Y, g:i a', 'Asia/Kolkata'),
            ])
            ->filters([
                TernaryFilter::make('current')->label('Only current versions')->default(true)
                    ->queries(true: fn (Builder $query) => $query->whereDoesntHave('supersededBy'), false: fn (Builder $query) => $query, blank: fn (Builder $query) => $query),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Upload document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('create', DebtDocument::class))
                    ->schema([
                        FileUpload::make('file')->disk('local')->directory('livewire-tmp/debt')->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize((int) config('debt_consolidation.documents.max_kilobytes')),
                        Select::make('document_type_id')->label('Type')->options(fn () => DocumentType::query()->orderBy('order')->pluck('label', 'id'))->required(),
                        TextInput::make('label')->maxLength(150),
                        Select::make('debt_liability_id')->label('About which liability')->options(fn () => $this->getOwnerRecord()->liabilities()->pluck('lender_name', 'id')),
                    ])
                    ->action(function (array $data): void {
                        $path = Storage::disk('local')->path($data['file']);
                        /** @var DebtApplication $application */
                        $application = $this->getOwnerRecord();

                        try {
                            app(UploadDebtDocument::class)->handle($application, new UploadedFile($path, basename($path), null, null, true), DocumentType::query()->find($data['document_type_id']), ActorType::Staff, auth()->id(), $data['label'] ?? null, isset($data['debt_liability_id']) ? (int) $data['debt_liability_id'] : null);
                        } finally {
                            Storage::disk('local')->delete($data['file']);
                        }

                        Notification::make()->title('Document stored (encrypted)')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (DebtDocument $record): bool => (bool) auth()->user()?->can('view', $record))
                    ->action(function (DebtDocument $record): StreamedResponse {
                        $record->application->recordEvent('document_downloaded', ['document' => $record->public_id], auth()->id());

                        return response()->streamDownload(fn () => print $record->contents(), $record->downloadName(), [
                            'Content-Type' => $record->mime_type,
                            'X-Content-Type-Options' => 'nosniff',
                            'Cache-Control' => 'private, no-store',
                        ]);
                    }),
                Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DebtDocument $record): bool => $record->status !== DocumentStatus::Verified && (bool) auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->modalDescription('Confirm you have read the document and it is genuine, legible and current.')
                    ->action(fn (DebtDocument $record) => app(ReviewDebtDocument::class)->verify($record, auth()->id())),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DebtDocument $record): bool => $record->status !== DocumentStatus::Rejected && (bool) auth()->user()?->can('update', $record))
                    ->schema([Textarea::make('reason')->label('Reason (shown to the customer)')->required()->maxLength(255)])
                    ->action(fn (DebtDocument $record, array $data) => app(ReviewDebtDocument::class)->reject($record, auth()->id(), $data['reason'])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
