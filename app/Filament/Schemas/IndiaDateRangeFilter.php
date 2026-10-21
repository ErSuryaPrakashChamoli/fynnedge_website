<?php

namespace App\Filament\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * A "from / until" table filter on a timestamp column, counted in whole days
 * of India time. The app stores UTC, so each picked day becomes its UTC start
 * and end; whereDate() would compare UTC dates and move anything between
 * 00:00 and 05:30 IST to the previous day. Pair it with a column shown in
 * the same timezone so the list and the filter agree.
 */
final class IndiaDateRangeFilter
{
    public const TIMEZONE = 'Asia/Kolkata';

    public static function make(string $column, string $label): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                DatePicker::make('from')->label("{$label} from"),
                DatePicker::make('until')->label("{$label} until"),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->where($query->qualifyColumn($column), '>=', Carbon::parse($date, self::TIMEZONE)->startOfDay()->utc()))
                ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->where($query->qualifyColumn($column), '<=', Carbon::parse($date, self::TIMEZONE)->endOfDay()->utc())))
            ->indicateUsing(fn (array $data): array => array_filter([
                ($data['from'] ?? null) ? "{$label} from {$data['from']}" : null,
                ($data['until'] ?? null) ? "{$label} until {$data['until']}" : null,
            ]));
    }
}
