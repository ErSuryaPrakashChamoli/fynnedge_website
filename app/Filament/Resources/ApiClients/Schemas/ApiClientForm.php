<?php

namespace App\Filament\Resources\ApiClients\Schemas;

use App\Models\ApiClient;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class ApiClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('API client')
                    ->description('Another system — such as the LMS — that calls the FynnEdge API with its own key. The key acts as a service account with the role below, so the same permissions, consent and duplicate rules apply as for an employee.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('FynnEdge LMS')
                            ->helperText('Shown as "requested by" on everything this system does.'),
                        Select::make('role')
                            ->label('Role')
                            ->required()
                            ->native(false)
                            ->options(fn (): array => self::roleOptions())
                            ->helperText('What the key may do. "Credit Officer" can request and read CIBIL scores and reports. Super admin is never offered.'),
                        TagsInput::make('allowed_ips')
                            ->label('Allowed IP addresses')
                            ->placeholder('203.0.113.10 or 203.0.113.0/24')
                            ->helperText('Recommended: the other system\'s fixed server address. Leave empty to accept the key from anywhere.')
                            ->nestedRecursiveRules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_string($value) || ! ApiClient::isValidIpOrRange($value)) {
                                        $fail('Enter an IP address (203.0.113.10) or a range (203.0.113.0/24).');
                                    }
                                },
                            ]),
                        Textarea::make('notes')
                            ->maxLength(1000)
                            ->rows(3)
                            ->helperText('Who runs this system and whom to contact about it.'),
                    ]),

                // Read-only: the key itself is never stored, so it cannot be shown again.
                Section::make('Key')
                    ->hiddenOn('create')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('status')
                            ->badge()
                            ->state(fn (ApiClient $record): string => $record->isActive() ? 'Active' : 'Revoked')
                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                        TextEntry::make('key_prefix')
                            ->label('Key starts with')
                            ->state(fn (ApiClient $record): string => $record->maskedKey()),
                        TextEntry::make('key_generated_at')->label('Generated')->dateTime('d M Y, H:i')->placeholder('—'),
                        TextEntry::make('last_used_at')->label('Last used')->dateTime('d M Y, H:i')->placeholder('Never'),
                        TextEntry::make('last_used_ip')->label('Last used from')->placeholder('—'),
                        TextEntry::make('user.name')->label('Service account'),
                    ]),
            ]);
    }

    /**
     * Every role but super_admin, which would bypass every permission check.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return Role::query()
            ->where('name', '!=', config('filament-shield.super_admin.name', 'super_admin'))
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }
}
