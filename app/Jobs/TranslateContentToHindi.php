<?php

namespace App\Jobs;

use App\Models\Lender;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Localization\AutoTranslation;
use App\Support\Localization\MachineTranslator;
use App\Support\Rates\RateBook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fills the `_hi` columns of one record through Google Translate. Queued by
 * AutoTranslation when a save leaves Hindi blank or out of date.
 */
class TranslateContentToHindi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, array{0: string, 1: string}>  $fields  attribute => [fingerprint of its English, of its Hindi] when queued
     */
    public function __construct(
        public string $modelClass,
        public int|string $modelKey,
        public array $fields,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MachineTranslator $translator): void
    {
        $model = $this->modelClass::query()->withoutGlobalScopes()->find($this->modelKey);

        if (! $model) {
            return;
        }

        $attributes = $model->getAttributes();
        $english = [];

        foreach ($this->fields as $attribute => [$englishFingerprint, $hindiFingerprint]) {
            $column = $attribute.'_'.AutoTranslation::LOCALE;

            // Either language moved since this was queued: the newer save queued
            // its own translation, or an editor wrote this Hindi themselves.
            if (AutoTranslation::fingerprint($attributes[$attribute] ?? null) !== $englishFingerprint
                || AutoTranslation::fingerprint($attributes[$column] ?? null) !== $hindiFingerprint) {
                continue;
            }

            $english[$attribute] = $model->hasCast($attribute, ['array', 'json'])
                ? $model->getAttribute($attribute)
                : $attributes[$attribute];
        }

        if ($english === []) {
            return;
        }

        foreach ($translator->translateValues($english) as $attribute => $hindi) {
            $model->setAttribute($attribute.'_'.AutoTranslation::LOCALE, $hindi);
        }

        // A translation is not an edit: no new "updated" time, audit row or
        // another round of translation.
        $model->timestamps = false;
        AutoTranslation::paused(fn () => $model->saveQuietly());

        // saveQuietly() also skips the hooks that clear RateBook's cached rate
        // rows, which carry these models' Hindi texts.
        if ($model instanceof LenderProductRate || $model instanceof LenderProduct || $model instanceof Lender || $model instanceof LoanProduct) {
            RateBook::flush();
        }
    }
}
