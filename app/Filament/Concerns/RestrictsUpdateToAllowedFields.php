<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Arr;

/**
 * The actual security boundary for a restricted editing experience that
 * shares its underlying model/table with a fuller, more privileged resource
 * (see LoanProductContentResource / LoanProductSeoResource, both operating
 * on the real LoanProduct record). Filament's own form-state extraction
 * already only dehydrates schema-defined fields, but per Phase 8's explicit
 * requirement, authorization must not rest on UI visibility alone — this
 * re-asserts the allowlist immediately before the record is persisted, so a
 * field outside it can never reach the database through this page no matter
 * how the request was crafted.
 */
trait RestrictsUpdateToAllowedFields
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return Arr::only($data, static::allowedUpdateFields());
    }

    /**
     * @return array<int, string>
     */
    abstract protected static function allowedUpdateFields(): array;
}
