<?php

namespace App\Filament\Schemas;

use App\Support\Localization\MachineTranslator;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

class HindiTranslationSection
{
    public const HEADING = 'Hindi translation (हिन्दी)';

    public const HELPER_TEXT = 'Leave blank to show the English text to Hindi visitors.';

    public const AUTOMATIC_TEXT = 'Filled in automatically from the English (Google Translate) whenever the English changes. Correct any field here — your wording is kept until its English changes again.';

    /**
     * What a blank Hindi field means right now: with Google Translate set up
     * it fills itself after saving; without, Hindi visitors see the English.
     */
    public static function description(): string
    {
        return MachineTranslator::isConfigured() ? self::AUTOMATIC_TEXT : self::HELPER_TEXT;
    }

    /**
     * The sentence the Settings-page Hindi tabs add to their own description.
     */
    public static function settingsNote(): string
    {
        return MachineTranslator::isConfigured()
            ? ' English you reword is translated into Hindi automatically after saving (Google Translate) unless you fill in the Hindi yourself.'
            : '';
    }

    /**
     * The collapsed section at the end of a form that holds the Hindi version
     * of every translatable field the form edits.
     *
     * Each Hindi field saves to the `{column}_hi` sibling of its English
     * column, and HasTranslations serves it while a visitor reads the site in
     * Hindi, falling back to the English column when it is blank. So no Hindi
     * field is ever required, and each one mirrors its English field's
     * component and limits. The section adds no state path, so its fields save
     * exactly like the English ones beside it (including through a
     * relationship section such as SeoFormSection's).
     *
     * @param  array<int, Component>  $components
     */
    public static function make(array $components): Section
    {
        return Section::make(self::HEADING)
            ->description(self::description())
            ->collapsible()
            ->collapsed()
            ->columnSpanFull()
            ->components($components);
    }

    /**
     * A Hindi rich editor for an English field that is a plain RichEditor.
     *
     * Its toolbar's attach button stores images on the public disk, for the
     * same reason HtmlBodyEditor does: the editor writes each image's URL into
     * the saved HTML, and the default disk here is private.
     */
    public static function richEditor(string $field, string $label, string $attachmentsDirectory = 'rich-content'): RichEditor
    {
        return RichEditor::make($field)
            ->label($label)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory($attachmentsDirectory)
            ->fileAttachmentsVisibility('public')
            ->columnSpanFull();
    }
}
