<?php

namespace App\Filament\RelationManagers;

use App\Enums\PublishStatus;
use App\Filament\Schemas\HindiTranslationSection;
use App\Models\Faq;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared by every model that morphs FAQs onto itself (LoanProduct, Page).
 * Lives here rather than under one resource because the owner is the
 * polymorphic `faqable` relation, not any single resource.
 */
class FaqsRelationManager extends RelationManager
{
    protected static string $relationship = 'faqs';

    protected static ?string $title = 'FAQs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required()
                    ->live(),
                DateTimePicker::make('published_at')
                    ->helperText('Leave blank to publish immediately once status is Published.')
                    ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                DateTimePicker::make('expires_at')
                    ->helperText('Optional. The FAQ stops appearing publicly after this time.'),
                HindiTranslationSection::make([
                    TextInput::make('question_hi')
                        ->label('Question (Hindi)')
                        ->columnSpanFull(),
                    Textarea::make('answer_hi')
                        ->label('Answer (Hindi)')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PublishStatus $state) => match ($state) {
                        PublishStatus::Published => 'success',
                        PublishStatus::Draft => 'warning',
                    }),
                TextColumn::make('sort_order')->label('Order'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                // Starter FAQs (e.g. the Debt Consolidation pages') arrive as drafts for review;
                // this publishes the reviewed ones in one step.
                BulkAction::make('publish')
                    ->label('Publish selected')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn (Faq $faq) => $faq->update(['status' => PublishStatus::Published]))),
                DeleteBulkAction::make(),
            ])
            ->reorderable('sort_order');
    }
}
