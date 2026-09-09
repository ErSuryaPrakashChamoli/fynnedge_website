<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('banners')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->helperText('Recommended: 1440 × 1000px (roughly 1.4:1), JPG/PNG/WebP up to 5MB. It fills the right-hand 60% of the homepage banner — about 706 × 500px on a desktop, so 1440px wide keeps it sharp on high-DPI screens. The box is 1.2:1 on smaller laptops and 1.8:1 on tablets, and the image is centre-cropped to fit, so keep faces, logos and any text in the middle and leave room around the edges.')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('image_alt')
                    ->label('Image alt text')
                    ->columnSpanFull(),
                TextInput::make('heading')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('subtitle')
                    ->columnSpanFull(),
                TextInput::make('cta_label')
                    ->label('Button label'),
                TextInput::make('cta_url')
                    ->label('Button link')
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if ($value && ! preg_match('#^(https?://|/)#i', (string) $value)) {
                            $fail('The button link must start with http://, https:// or /.');
                        }
                    })
                    ->helperText('A full URL, e.g. https://fynnedge.com/loans, or a site-relative path like /loans.'),
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
                    ->helperText('Optional. The banner stops appearing publicly after this time.'),
            ]);
    }
}
