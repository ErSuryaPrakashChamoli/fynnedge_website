<?php

namespace App\Filament\Resources\MarketingSections\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class MarketingSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('placement')
                    ->required()
                    ->live()
                    ->options([
                        'home_finance_cta' => 'Homepage — "Not sure which loan fits?" CTA',
                        'home_emi_cta' => 'Homepage — "Plan your EMI" CTA',
                        'home_final_cta' => 'Homepage — closing "Ready to see what you\'re eligible for?" CTA',
                        'home_flexi_hybrid_ticker' => 'Homepage — Flexi Hybrid marquee strip (below header)',
                    ])
                    ->helperText('Where this section appears. Each placement shows its own hardcoded default copy on the website until a section is published here.')
                    ->columnSpanFull(),
                TextInput::make('heading')
                    ->required()
                    ->columnSpanFull()
                    ->helperText(fn (callable $get) => $get('placement') === 'home_flexi_hybrid_ticker' ? 'Used as the small badge label, e.g. "Our Specialty".' : null),
                TextInput::make('subheading')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText(fn (callable $get) => $get('placement') === 'home_flexi_hybrid_ticker' ? 'The scrolling marquee text, e.g. "Flexi Hybrid Term Loan (Overdraft) — Bajaj Finance // Tata Capital // Piramal Finance // Kotak Mahindra Bank".' : null),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('marketing-sections')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->helperText('Optional. JPG or PNG, up to 5MB.'),
                TextInput::make('image_alt')
                    ->label('Image alt text')
                    ->helperText('Describes the image for screen readers and search engines.'),
                TextInput::make('cta_label')
                    ->label('Button label'),
                TextInput::make('cta_url')
                    ->label('Button link')
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value && ! preg_match('#^(https?://|/)#i', (string) $value)) {
                            $fail('The button link must start with http://, https:// or /.');
                        }
                    })
                    ->helperText('A full URL, e.g. https://fynnedge.com/loans, or a site-relative path like /eligibility.'),
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
                    ->label('Expires at')
                    ->helperText('Optional. The section stops appearing publicly after this time.'),
            ]);
    }
}
