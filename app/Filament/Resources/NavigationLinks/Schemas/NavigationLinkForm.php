<?php

namespace App\Filament\Resources\NavigationLinks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NavigationLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(60),

                Section::make('Destination')
                    ->description('Set exactly one of these. An internal route name is safer — it automatically disappears if that route is ever renamed or removed, instead of becoming a broken link.')
                    ->columns(2)
                    ->components([
                        TextInput::make('route_name')
                            ->label('Internal route name')
                            ->helperText('E.g. "contact", "loans.index", "faqs.index". Takes priority over the external link below if both are set.')
                            ->requiredWithout('url'),
                        TextInput::make('url')
                            ->label('External or internal URL')
                            ->requiredWithout('route_name')
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                if ($value && ! preg_match('#^(https?://|/|mailto:|tel:)#i', (string) $value)) {
                                    $fail('The link must start with http://, https://, /, mailto: or tel:.');
                                }
                            })
                            ->helperText('A full URL (https://...) or a site-relative path (/careers). Never a route name.'),
                        Toggle::make('is_external')
                            ->label('Opens in a new tab')
                            ->helperText('Enable for links leaving the FynnEdge site.'),
                    ]),

                Select::make('location')
                    ->options(['footer' => 'Footer — Quick Links'])
                    ->default('footer')
                    ->required(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
