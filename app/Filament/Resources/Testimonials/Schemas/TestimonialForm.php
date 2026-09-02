<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_name')
                    ->required(),
                TextInput::make('role_location')
                    ->label('Role / location')
                    ->helperText('E.g. "Personal Loan customer, Bengaluru". Shown under the customer name.'),
                Select::make('loan_category')
                    ->label('Loan category')
                    ->options(LoanCategory::class)
                    ->native(false)
                    ->helperText('Leave empty to show this testimonial on every loan page. Set it to show it only on that category\'s pages (in addition to the general ones).'),
                Select::make('rating')
                    ->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'])
                    ->native(false),
                Textarea::make('quote')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('avatar_path')
                    ->label('Avatar')
                    ->image()
                    ->disk('public')
                    ->directory('testimonials')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048),
                TextInput::make('avatar_alt')
                    ->label('Avatar alt text'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required()
                    ->live(),
                DateTimePicker::make('published_at')
                    ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                DateTimePicker::make('expires_at')
                    ->helperText('Optional. The testimonial stops appearing publicly after this time.'),
            ]);
    }
}
