<?php

namespace App\Filament\Resources\DocumentTypes\Schemas;

use App\Modules\Applications\Models\DocumentType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocumentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('key')
                    ->required()
                    ->unique(DocumentType::class, 'key', ignoreRecord: true)
                    ->helperText('Stable identifier used by lender document requirements, e.g. "pan_card".'),
                TextInput::make('label')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('order')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
