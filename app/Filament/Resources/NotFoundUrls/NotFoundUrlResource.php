<?php

namespace App\Filament\Resources\NotFoundUrls;

use App\Filament\Resources\NotFoundUrls\Pages\ListNotFoundUrls;
use App\Filament\Resources\NotFoundUrls\Tables\NotFoundUrlsTable;
use App\Models\NotFoundUrl;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The paths visitors and crawlers asked for that answered 404, counted by
 * App\Http\Middleware\RecordNotFoundRequests. Read-only: rows are recorded,
 * never typed. The fix for a row is a rule in Redirects (a 301 to the
 * equivalent page, or 410 Gone), which the "Add rule" action opens prefilled.
 *
 * Next to Redirects under Website Settings, since the two are used together.
 */
class NotFoundUrlResource extends Resource
{
    protected static ?string $model = NotFoundUrl::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'Missing pages (404)';

    protected static ?string $modelLabel = 'missing page';

    protected static ?string $recordTitleAttribute = 'path';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NotFoundUrlsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotFoundUrls::route('/'),
        ];
    }
}
