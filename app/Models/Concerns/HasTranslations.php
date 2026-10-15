<?php

namespace App\Models\Concerns;

use App\Support\Localization\AutoTranslation;
use App\Support\Localization\SiteLocale;
use Illuminate\Database\Eloquent\Model;

/**
 * Serves a model's visitor-facing text in the visitor's language.
 *
 * Each translatable attribute has a sibling column per extra language named
 * `{attribute}_{locale}` (`title_hi`, `body_hi`, ...). While the site runs in
 * Hindi, reading `$model->title` returns `title_hi` when it is filled and the
 * English `title` when it is not, so a record nobody has translated yet still
 * renders instead of going blank. Views therefore never change: they keep
 * reading `$page->body`.
 *
 * Reads are what this trait changes. Saving writes the raw attributes, and the admin
 * panel always runs in English (SiteLocale::appliesTo()), so editors always
 * see and edit the English columns as English, and the Hindi ones by name.
 *
 * A query that selects only some columns simply skips the Hindi value of any
 * column it left out, rather than throwing for a missing attribute.
 */
trait HasTranslations
{
    /**
     * New or changed English is translated into Hindi in the background
     * (AutoTranslation) whenever a Google Translate key is configured.
     */
    public static function bootHasTranslations(): void
    {
        static::saved(fn (Model $model) => AutoTranslation::modelSaved($model));
    }

    /**
     * The attributes that have a translated sibling column.
     *
     * @return list<string>
     */
    abstract public function translatableAttributes(): array;

    public function getAttributeValue($key): mixed
    {
        $value = parent::getAttributeValue($key);

        if (! SiteLocale::isTranslating() || ! in_array($key, $this->translatableAttributes(), true)) {
            return $value;
        }

        $column = $key.'_'.SiteLocale::current();

        if (! array_key_exists($column, $this->attributes)) {
            return $value;
        }

        $translated = parent::getAttributeValue($column);

        return blank($translated) ? $value : $translated;
    }

    /**
     * The translated sibling column names, e.g. ['title_hi', 'body_hi'].
     *
     * @return list<string>
     */
    public static function translationColumns(string $locale = 'hi'): array
    {
        return array_map(
            fn (string $attribute): string => "{$attribute}_{$locale}",
            (new static)->translatableAttributes(),
        );
    }
}
