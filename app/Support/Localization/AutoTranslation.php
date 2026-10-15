<?php

namespace App\Support\Localization;

use App\Jobs\TranslateContentToHindi;
use App\Jobs\TranslateSettingToHindi;
use App\Models\Concerns\HasTranslations;
use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Calculators\CalculatorPagesContent;
use App\Support\Enquiries\QuickEnquiryPageContent;
use App\Support\Pages\AboutPageContent;
use App\Support\Pages\CreditScorePageContent;
use ArrayObject;
use Illuminate\Database\Eloquent\Model;

use function Illuminate\Support\defer;

/**
 * Keeps the Hindi version of admin-edited content in step with the English,
 * through Google Translate (MachineTranslator), so whatever an editor adds or
 * changes reads in Hindi without anyone typing it twice.
 *
 * The rule, for every translatable model field and every piece of Settings
 * page copy: the Hindi is (re)translated whenever it is blank, or its English
 * changed in a save that left the Hindi alone. Hindi an editor typed or
 * corrected in the same save always wins and is never overwritten, until the
 * English beside it changes again. Translation runs on the queue, after the
 * save commits; each job re-checks that neither language moved in the
 * meantime, so a slow job can never undo a newer edit.
 *
 * Without a Google API key nothing is queued and untranslated content keeps
 * falling back to English. `php artisan translations:fill-hindi` catches up
 * anything left blank (it also runs daily).
 */
final class AutoTranslation
{
    public const LOCALE = 'hi';

    private static bool $paused = false;

    /**
     * The visitor-facing Settings: single text values, and the page-copy
     * arrays behind the About, Quick Enquiry, Calculator and Credit Score pages.
     *
     * @return list<string>
     */
    public static function translatableSettingKeys(): array
    {
        return [
            'site_tagline',
            'hero_eyebrow',
            'hero_heading',
            'hero_heading_accent',
            'hero_subheading',
            'footer_disclaimer',
            'contact_address',
            'founder_name',
            'seo_meta_description',
            AboutPageContent::SETTING_KEY,
            QuickEnquiryPageContent::SETTING_KEY,
            CalculatorPagesContent::SETTING_KEY,
            CreditScorePageContent::SETTING_KEY,
            ...array_map(CreditScorePageContent::settingKey(...), BureauName::cases()),
        ];
    }

    /**
     * Every model with translatable columns (they use HasTranslations).
     *
     * @return list<class-string<Model>>
     */
    public static function translatableModels(): array
    {
        $classes = [];

        foreach ([...glob(app_path('Models/*.php')), ...glob(app_path('Modules/*/Models/*.php'))] as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path()) + 1));

            if (class_exists($class) && is_subclass_of($class, Model::class) && in_array(HasTranslations::class, class_uses_recursive($class), true)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Called after every save of a model that uses HasTranslations. The row is
     * re-read so the decision rests on what is stored, not on this instance:
     * a background translation may have filled the Hindi since it was loaded,
     * and columns a save never mentioned are not on the instance at all.
     */
    public static function modelSaved(Model $model): void
    {
        if (self::$paused || ! MachineTranslator::isConfigured()) {
            return;
        }

        $row = (array) $model->getConnection()->table($model->getTable())->where($model->getKeyName(), $model->getKey())->first();
        $fields = $row === [] ? [] : self::fieldsNeedingTranslation($model, row: $row);

        if ($fields !== []) {
            TranslateContentToHindi::dispatch($model::class, $model->getKey(), $fields)->afterCommit();
        }
    }

    /**
     * The fields whose Hindi is blank, or whose English changed in the save
     * just made while their Hindi did not.
     *
     * @param  array<string, mixed>|null  $row  the stored row (defaults to the instance's attributes)
     * @return array<string, array{0: string, 1: string}> attribute => [fingerprint of its stored English, of its stored Hindi]
     */
    public static function fieldsNeedingTranslation(Model $model, bool $onlyMissing = false, ?array $row = null): array
    {
        $row ??= $model->getAttributes();
        $fields = [];

        foreach ($model->translatableAttributes() as $attribute) {
            $column = $attribute.'_'.self::LOCALE;

            if (! array_key_exists($attribute, $row) || ! array_key_exists($column, $row) || self::isBlank($row[$attribute])) {
                continue;
            }

            $missing = self::isBlank($row[$column]);
            $stale = ! $onlyMissing && $model->wasChanged($attribute) && ! $model->wasChanged($column);

            if ($missing || $stale) {
                $fields[$attribute] = [self::fingerprint($row[$attribute]), self::fingerprint($row[$column])];
            }
        }

        return $fields;
    }

    /**
     * Called by Setting::set() before it writes; the decision is taken once
     * the request (or command, or job) has finished saving.
     */
    public static function settingSaving(string $key): void
    {
        if (self::$paused || ! MachineTranslator::isConfigured()) {
            return;
        }

        $englishKey = str_ends_with($key, '_'.self::LOCALE) ? substr($key, 0, -strlen('_'.self::LOCALE)) : $key;

        $baselines = self::settingBaselines();

        if (! in_array($englishKey, self::translatableSettingKeys(), true) || $baselines->offsetExists($englishKey)) {
            return;
        }

        $baselines[$englishKey] = [
            'english' => Setting::get($englishKey),
            'hindi' => Setting::get($englishKey.'_'.self::LOCALE),
        ];

        defer(fn () => self::dispatchSetting($englishKey));
    }

    public static function dispatchSetting(string $key): void
    {
        $baselines = self::settingBaselines();
        $baseline = $baselines[$key] ?? null;
        unset($baselines[$key]);

        if ($baseline === null) {
            return;
        }

        $english = Setting::get($key);
        $hindi = Setting::get($key.'_'.self::LOCALE);
        $paths = self::settingPathsNeedingTranslation($baseline['english'], $english, $baseline['hindi'], $hindi);

        if ($paths !== []) {
            TranslateSettingToHindi::dispatch($key, $paths, self::fingerprint(json_encode($english)), self::fingerprint(json_encode($hindi)));
        }
    }

    /**
     * Which pieces of a setting need (new) Hindi. A piece is identified by its
     * dot path ('' for a setting that is a single text). Hindi the editor
     * changed in the same request is left alone.
     *
     * @return list<string>
     */
    public static function settingPathsNeedingTranslation(mixed $englishBefore, mixed $english, mixed $hindiBefore, mixed $hindi): array
    {
        $paths = [];

        self::walkTexts($english, '', false, function (string $path, string $text, bool $inList) use (&$paths, $englishBefore, $hindiBefore, $hindi): void {
            $hindiNow = self::at($hindi, $path);

            if ($hindiNow !== self::at($hindiBefore, $path)) {
                return;
            }

            // Outside a list, English that lang/hi.json translates needs no
            // Hindi of its own — unless an old Hindi override would now
            // shadow it. A list's Hindi copy is all-or-nothing, so every item
            // in it needs its own entry.
            $changed = self::at($englishBefore, $path) !== $text;
            $hasOverride = filled($hindiNow);
            $builtIn = ! $inList && Phrasebook::known($text) !== null;

            if ($changed ? ($hasOverride || ! $builtIn) : (! $hasOverride && ! $builtIn)) {
                $paths[] = $path;
            }
        });

        return $paths;
    }

    /**
     * Each setting as it was before the current request (or job, or command)
     * first saved it, with its Hindi at that moment. Settings save English and
     * Hindi as two keys, in either order, so the decision waits for the end of
     * the request. Scoped to the container, so it never outlives a request.
     *
     * @return ArrayObject<string, array{english: mixed, hindi: mixed}>
     */
    private static function settingBaselines(): ArrayObject
    {
        $binding = self::class.'.setting-baselines';

        if (! app()->bound($binding)) {
            app()->scoped($binding, fn (): ArrayObject => new ArrayObject);
        }

        return app($binding);
    }

    /**
     * Runs $callback without queuing any translation — for writes that ARE
     * translations, so they never trigger another round.
     */
    public static function paused(callable $callback): mixed
    {
        $previous = self::$paused;
        self::$paused = true;

        try {
            return $callback();
        } finally {
            self::$paused = $previous;
        }
    }

    public static function isBlank(mixed $raw): bool
    {
        if (is_array($raw)) {
            return $raw === [];
        }

        return $raw === null || in_array(trim((string) $raw), ['', '[]', '{}', 'null'], true);
    }

    public static function fingerprint(mixed $raw): string
    {
        return sha1(is_array($raw) ? (string) json_encode($raw) : (string) $raw);
    }

    public static function at(mixed $value, string $path): mixed
    {
        return $path === '' ? $value : data_get($value, $path);
    }

    /**
     * @param  callable(string, string, bool): void  $callback  receives each human-readable text's path, the text, and whether it sits inside a list
     */
    public static function walkTexts(mixed $value, string $path, bool $inList, callable $callback): void
    {
        if (is_array($value)) {
            $isList = array_is_list($value);

            foreach ($value as $key => $item) {
                if (in_array($key, ['url', 'href', 'link', 'value', 'key', 'icon', 'slug'], true)) {
                    continue;
                }

                self::walkTexts($item, $path === '' ? (string) $key : "{$path}.{$key}", $inList || $isList, $callback);
            }

            return;
        }

        if (is_string($value) && preg_match('/\p{L}{2,}/u', $value) === 1 && preg_match('#^(https?://|/|mailto:|tel:)\S*$#i', trim($value)) !== 1) {
            $callback($path, $value, $inList);
        }
    }
}
