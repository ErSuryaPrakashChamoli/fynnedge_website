<?php

namespace App\Filament\Resources\CreditScoreChecks;

use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Filament\Resources\CreditScoreChecks\Pages\ListCreditScoreChecks;
use App\Filament\Resources\CreditScoreChecks\Pages\ViewCreditScoreCheck;
use App\Filament\Resources\CreditScoreChecks\Schemas\CreditScoreCheckForm;
use App\Filament\Resources\CreditScoreChecks\Tables\CreditScoreChecksTable;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CreditScoreCheckResource extends Resource
{
    protected static ?string $model = CreditScoreCheck::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Credit Score Checks';

    protected static ?string $modelLabel = 'credit score check';

    public static function form(Schema $schema): Schema
    {
        return CreditScoreCheckForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CreditScoreChecksTable::configure($table);
    }

    /**
     * The admin page of the bureau request that holds this check's full report
     * — Experian, CIBIL or Equifax, including the Experian stand-in on the
     * CIBIL page. Null when the provider kept no report (demo, CRIF's public
     * check) or the signed-in user may not open that bureau's requests.
     */
    public static function bureauReportUrl(CreditScoreCheck $check): ?string
    {
        $request = $check->bureauRequest();

        if ($request === null || ! auth()->user()?->can('view', $request)) {
            return null;
        }

        $resource = match (true) {
            $request instanceof CibilCreditRequest => CibilCreditRequestResource::class,
            $request instanceof EquifaxCreditRequest => EquifaxCreditRequestResource::class,
            default => ExperianCreditRequestResource::class,
        };

        return $resource::getUrl('view', ['record' => $request]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditScoreChecks::route('/'),
            'view' => ViewCreditScoreCheck::route('/{record}'),
        ];
    }
}
