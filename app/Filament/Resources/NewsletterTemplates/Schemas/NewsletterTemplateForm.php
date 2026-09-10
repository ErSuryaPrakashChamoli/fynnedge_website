<?php

namespace App\Filament\Resources\NewsletterTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template')
                    ->description('A starting point for new campaigns. The branded header, footer and unsubscribe link come from the email layout, so a template only holds the body.')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->label('Name')->required()->maxLength(120),
                        Toggle::make('is_active')
                            ->label('Available when composing')
                            ->default(true)
                            ->helperText('Turn off to retire a template without deleting campaigns that used it.'),
                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(200)
                            ->helperText('What this template is for, shown to whoever composes a campaign.')
                            ->columnSpanFull(),
                        RichEditor::make('content')->label('Body')->required()->columnSpanFull(),
                    ]),
            ]);
    }
}
