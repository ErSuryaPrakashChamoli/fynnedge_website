<?php

namespace App\Filament\Resources\Authors\Schemas;

use App\Filament\Schemas\HindiTranslationSection;
use App\Models\Author;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Author')
                    ->description('Shown as the byline on their articles, in an "About the author" box, and on their own author page.')
                    ->columnSpanFull()
                    ->components([
                        Grid::make(['default' => 1, 'md' => 3])->components([
                            FileUpload::make('photo_path')
                                ->label('Photo')
                                ->image()
                                ->avatar()
                                ->imageEditor()
                                ->disk('public')
                                ->directory(Author::PHOTO_DIRECTORY)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize(2048)
                                ->helperText('Square photo, JPG/PNG/WebP, up to 2MB.'),
                            Grid::make(1)->columnSpan(['md' => 2])->components([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                                        if ($operation === 'create') {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('designation')
                                    ->maxLength(120)
                                    ->placeholder('e.g. Senior Loan Advisor, CFP'),
                                /*
                                 * The slug is the public author page address, so it is only
                                 * derived from the name while creating — renaming an author
                                 * must not break /resources/authors/{slug} links.
                                 */
                                TextInput::make('slug')
                                    ->label('Author page address')
                                    ->prefix('/resources/authors/')
                                    ->required()
                                    ->maxLength(80)
                                    ->alphaDash()
                                    ->regex('/^[a-z0-9-]+$/')
                                    ->unique(Author::class, 'slug', ignoreRecord: true)
                                    ->helperText('Lowercase letters, numbers and hyphens. Changing it breaks existing links to the author page.'),
                            ]),
                        ]),
                        Textarea::make('bio')
                            ->label('Short bio')
                            ->rows(3)
                            ->maxLength(800),
                        Grid::make(['default' => 1, 'md' => 3])->components([
                            TextInput::make('profile_url')
                                ->label('Profile link (optional)')
                                ->url()
                                ->rule('starts_with:https://')
                                ->placeholder('https://www.linkedin.com/in/…')
                                ->maxLength(255)
                                ->columnSpan(['md' => 2]),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->helperText('Off hides the author from the website but keeps their articles linked.')
                                ->inline(false),
                        ]),
                    ]),

                HindiTranslationSection::make([
                    TextInput::make('name_hi')
                        ->label('Name (Hindi)')
                        ->maxLength(180),
                    TextInput::make('designation_hi')
                        ->label('Designation (Hindi)')
                        ->maxLength(180),
                    Textarea::make('bio_hi')
                        ->label('Short bio (Hindi)')
                        ->rows(3)
                        ->maxLength(1200)
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
