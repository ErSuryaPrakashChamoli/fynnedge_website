<?php

namespace App\Filament\Resources\Redirects\Schemas;

use App\Models\Redirect;
use App\Support\UrlPath;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->description('Sends visitors and search engines from an old URL to a new one, or tells them the old page is gone for good. The query string on the incoming request is carried across automatically.')
                    ->columns(2)
                    ->components([
                        TextInput::make('source_path')
                            ->label('Old path')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/old-loan-page')
                            ->helperText('Path only, starting with /. Trailing slashes and query strings are ignored when matching.')
                            // Prefilled by the "Add a rule" action on Missing pages (404).
                            ->default(fn (): ?string => is_string($path = request()->query('source_path')) ? $path : null)
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
                        Select::make('status_code')
                            ->label('Type')
                            ->required()
                            ->native(false)
                            ->live()
                            ->default(Redirect::PERMANENT)
                            ->options([
                                Redirect::PERMANENT => '301 — Permanent (passes SEO ranking to the new URL)',
                                Redirect::TEMPORARY => '302 — Temporary (keeps ranking on the old URL)',
                                Redirect::GONE => '410 — Gone (removed for good, nothing equivalent exists)',
                            ]),
                        TextInput::make('destination')
                            ->label('New URL or path')
                            ->required(fn (Get $get): bool => ! self::isGone($get))
                            ->hidden(fn (Get $get): bool => self::isGone($get))
                            // A row switched to Gone must drop its old destination, not keep it hidden.
                            ->dehydratedWhenHidden()
                            ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => self::isGone($get) || blank($state) ? null : trim($state))
                            ->maxLength(255)
                            ->placeholder('/loans/personal-loan')
                            ->helperText('A path on this site (starting with /) or a full https:// URL elsewhere. Point at the final page, not at another old URL.')
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $destination = trim((string) $value);

                                    if (! str_starts_with($destination, '/') && ! self::isWebUrl($destination)) {
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

                                        return;
                                    }

                                    if (! str_starts_with($destination, '/')) {
                                        return;
                                    }

                                    /*
                                     * The middleware flattens a chain at request time, but a
                                     * row that points at another old URL is still a mistake
                                     * waiting to happen: change or delete the other row and
                                     * this one silently changes with it. Point straight at
                                     * the page.
                                     */
                                    $onward = Redirect::activeMap()[Redirect::normalizePath($destination)] ?? null;

                                    if ($onward !== null && $onward['status_code'] === Redirect::GONE) {
                                        $fail('That path is itself marked Gone (410). Choose a page that exists, or make this row Gone too.');

                                        return;
                                    }

                                    if ($onward !== null) {
                                        $fail("That path already redirects to {$onward['destination']}. Enter that destination instead, so visitors take one hop.");

                                        return;
                                    }

                                    if (! UrlPath::isRoutable(Redirect::normalizePath($destination))) {
                                        $fail('No page on this site answers that path. Check it for typos.');
                                    }
                                },
                            ]),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to keep the row without applying it.'),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->helperText('Why this rule exists, e.g. which report listed the old URL and what the old page was.'),
                    ]),
            ]);
    }

    private static function isGone(Get $get): bool
    {
        return (int) $get('status_code') === Redirect::GONE;
    }

    /**
     * Only http(s): FILTER_VALIDATE_URL alone also accepts schemes such as
     * javascript: or ftp:, none of which belong in a Location header.
     */
    private static function isWebUrl(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($destination, PHP_URL_SCHEME)), ['http', 'https'], true);
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

        while (isset($map[$next]) && $map[$next]['destination'] !== null && ! isset($seen[$next])) {
            $seen[$next] = true;
            $next = Redirect::normalizePath($map[$next]['destination']);

            if ($next === $source) {
                return true;
            }
        }

        return false;
    }
}
