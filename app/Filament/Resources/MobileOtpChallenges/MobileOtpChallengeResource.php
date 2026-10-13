<?php

namespace App\Filament\Resources\MobileOtpChallenges;

use App\Filament\Resources\MobileOtpChallenges\Pages\ListMobileOtpChallenges;
use App\Filament\Resources\MobileOtpChallenges\Pages\ViewMobileOtpChallenge;
use App\Filament\Resources\MobileOtpChallenges\Schemas\MobileOtpChallengeInfolist;
use App\Filament\Resources\MobileOtpChallenges\Tables\MobileOtpChallengesTable;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only log of every OTP the site has issued, for support and abuse
 * investigation. Strictly a window: there is no create, edit or delete, so no
 * admin can generate a code, read one back, or mark a number verified — only
 * a visitor entering the right code ever does that (VerifyMobileOtp).
 *
 * Access is the Shield permission ViewAny:MobileOtpChallenge / View:MobileOtpChallenge,
 * granted to no starter role; super_admin sees it through the Gate bypass.
 */
class MobileOtpChallengeResource extends Resource
{
    protected static ?string $model = MobileOtpChallenge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Mobile OTP Verifications';

    protected static ?string $modelLabel = 'mobile OTP verification';

    protected static ?string $pluralModelLabel = 'mobile OTP verifications';

    protected static ?string $slug = 'mobile-otp-verifications';

    public static function infolist(Schema $schema): Schema
    {
        return MobileOtpChallengeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MobileOtpChallengesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileOtpChallenges::route('/'),
            'view' => ViewMobileOtpChallenge::route('/{record}'),
        ];
    }
}
