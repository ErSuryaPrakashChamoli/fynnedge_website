<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Models\Redirect;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Redirect')
                    ->description('Sends visitors and search engines from an old URL to a new one. The query string on the incoming request is carried across automatically.')
                    ->columns(2)
                    ->components([
                        TextInput::make('source_path')
                            ->label('Old path')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/old-loan-page')
                            ->helperText('Path only, starting with /. Trailing slashes and query strings are ignored when matching.')
                            ->dehydrateStateUsing(fn (string $state): string => Redirect::normalizePath($state))
                            /*
                             * Uniqueness is checked against the NORMALISED value, not the
                             * raw input: "/a", "a/" and "/A" are the same row as far as the
                             * middleware is concerned, so a plain unique() rule would let
                             * an admin create a second row that shadows the first and then
                             * fail on the database's unique index instead.
                             */
                            ->rules([
                                fn (?Redirect $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $exists = Redirect::query()
                                        ->where('source_path', Redirect::normalizePath((string) $value))
                                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                        ->exists();

                                    if ($exists) {
                                        $fail('A redirect for that path already exists.');
                                    }
                                },
                            ]),
                        TextInput::make('destination')
                            ->label('New URL or path')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/loans/personal-loan')
                            ->helperText('A path on this site (starting with /) or a full https:// URL elsewhere.')
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $destination = trim((string) $value);

                                    if (! str_starts_with($destination, '/') && ! filter_var($destination, FILTER_VALIDATE_URL)) {
                                        $fail('Enter a path starting with / or a complete URL including https://.');

                                        return;
                                    }

                                    /*
                                     * A row pointing at its own source would bounce the
                                     * browser until it gives up. The middleware also
                                     * refuses to serve such a row, but catching it here is
                                     * what stops an admin from creating one at all —
                                     * including the indirect case, where this destination
                                     * is the source of another active redirect that leads
                                     * back here.
                                     */
                                    $source = Redirect::normalizePath((string) $get('source_path'));

                                    if (Redirect::normalizePath($destination) === $source) {
                                        $fail('The destination is the same as the old path — that would redirect to itself.');

                                        return;
                                    }

                                    if (self::leadsBackTo($destination, $source)) {
                                        $fail('That destination redirects back here, which would create a redirect loop.');
                                    }
                                },
                            ]),
                        Select::make('status_code')
                            ->label('Type')
                            ->required()
                            ->native(false)
                            ->default(301)
                            ->options([
                                301 => '301 — Permanent (passes SEO ranking to the new URL)',
                                302 => '302 — Temporary (keeps ranking on the old URL)',
                            ]),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to keep the row without redirecting.'),
                    ]),
            ]);
    }

    /**
     * Walks the active redirect chain from $destination to see whether it comes
     * back to $source. Bounded by the number of rows it can visit, so a
     * pre-existing loop between other rows cannot hang the request.
     */
    private static function leadsBackTo(string $destination, string $source): bool
    {
        $map = Redirect::activeMap();
        $seen = [];
        $next = Redirect::normalizePath($destination);

        while (isset($map[$next]) && ! isset($seen[$next])) {
            $seen[$next] = true;
            $next = Redirect::normalizePath($map[$next]['destination']);

            if ($next === $source) {
                return true;
            }
        }

        return false;
    }
}
