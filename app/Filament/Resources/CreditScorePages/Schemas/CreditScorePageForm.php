<?php

namespace App\Filament\Resources\CreditScorePages\Schemas;

use App\Models\CreditScorePage;
use App\Modules\CreditScore\Enums\BureauName;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CreditScorePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bureau')
                    ->label('Credit score page')
                    ->options(collect(BureauName::cases())->mapWithKeys(
                        fn (BureauName $bureau): array => [$bureau->value => $bureau->getLabel().' — /credit-score/'.$bureau->value],
                    ))
                    ->required()
                    ->unique(CreditScorePage::class, 'bureau', ignoreRecord: true)
                    ->helperText('Which credit score page this content appears on. Each page has its own content — the CIBIL and Experian pages are edited separately.'),
                TextInput::make('title')
                    ->label('Heading')
                    ->helperText('Optional — leave blank to use the default heading, e.g. "About the CIBIL score".'),
                RichEditor::make('body')
                    ->label('About')
                    ->helperText('Shown below the credit score check. Leave empty to hide the section. The headline, introduction, meta tags and search engine indexing are under Website Settings → Credit Score Page. Changes go live immediately.')
                    ->columnSpanFull(),
            ]);
    }
}
