<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->helperText('Leave blank to keep the current password.'),
                Toggle::make('is_admin')
                    ->label('Can log into the admin panel')
                    ->default(true)
                    ->helperText('Only answers whether this account can log in at all — the roles below decide which modules they can actually see and use.'),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('E.g. Marketing, SEO. A role determines exactly which modules this user gets access to — manage roles under Access Control → Roles.')
                    ->columnSpanFull(),
            ]);
    }
}
