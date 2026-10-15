<?php

namespace App\Console\Commands;

use App\Jobs\TranslateContentToHindi;
use App\Jobs\TranslateSettingToHindi;
use App\Models\Setting;
use App\Support\Localization\AutoTranslation;
use App\Support\Localization\MachineTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catches up every piece of visitor-facing content that still has no Hindi:
 * content saved while no Google Translate key was set, a translation whose
 * request failed, or rows written outside the models (imports, seeders).
 * Hindi that already exists is never touched. Scheduled daily; safe to run
 * by hand at any time.
 */
class FillHindiTranslations extends Command
{
    protected $signature = 'translations:fill-hindi
                            {--sync : Translate now, in this process, instead of queueing the work}
                            {--dry-run : Only report what is missing}';

    protected $description = 'Translate into Hindi every piece of site content that has no Hindi yet';

    public function handle(): int
    {
        if (! MachineTranslator::isConfigured() && ! $this->option('dry-run')) {
            $this->warn('GOOGLE_TRANSLATE_API_KEY is not set, so nothing can be translated. Untranslated content keeps showing in English.');

            return self::SUCCESS;
        }

        $records = 0;
        $fields = 0;

        foreach (AutoTranslation::translatableModels() as $modelClass) {
            $query = $modelClass::query()->withoutGlobalScopes();

            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $query->whereNull((new $modelClass)->getDeletedAtColumn());
            }

            $query->lazyById(200)->each(function ($model) use (&$records, &$fields): void {
                $missing = AutoTranslation::fieldsNeedingTranslation($model, onlyMissing: true);

                if ($missing === []) {
                    return;
                }

                $records++;
                $fields += count($missing);

                if (! $this->option('dry-run')) {
                    $this->translate(new TranslateContentToHindi($model::class, $model->getKey(), $missing));
                }
            });
        }

        $settings = 0;

        foreach (AutoTranslation::translatableSettingKeys() as $key) {
            $english = Setting::get($key);
            $hindi = Setting::get($key.'_'.AutoTranslation::LOCALE);
            $paths = AutoTranslation::settingPathsNeedingTranslation($english, $english, $hindi, $hindi);

            if ($paths === []) {
                continue;
            }

            $settings++;

            if (! $this->option('dry-run')) {
                $this->translate(new TranslateSettingToHindi($key, $paths, AutoTranslation::fingerprint(json_encode($english)), AutoTranslation::fingerprint(json_encode($hindi))));
            }
        }

        $this->info(sprintf(
            '%s: %d field(s) on %d record(s), and %d setting(s).',
            $this->option('dry-run') ? 'Missing Hindi' : ($this->option('sync') ? 'Translated' : 'Queued for translation'),
            $fields,
            $records,
            $settings,
        ));

        return self::SUCCESS;
    }

    private function translate(TranslateContentToHindi|TranslateSettingToHindi $job): void
    {
        $this->option('sync') ? dispatch_sync($job) : dispatch($job);
    }
}
