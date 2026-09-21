<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class HtmlBodyEditor
{
    /**
     * A visual rich editor with an "Edit as HTML" source mode for the same field.
     *
     * The rich editor converts its state to a TipTap document as soon as the form
     * hydrates, which silently drops any markup TipTap has no node for (divs,
     * sections, custom classes). So the HTML source lives on its own non-saved
     * path, filled from the RAW stored value before that conversion runs — the
     * toggle and code editor must stay ahead of the rich editor in this array.
     * A body the visual editor could not save back unchanged opens in HTML mode,
     * so saving an unrelated field never strips it.
     *
     * Images inserted with the toolbar's attach button are stored on the public
     * disk under $attachmentsDirectory: the editor writes each image's URL into
     * the saved HTML, so the public page renders it without any lookup. Left to
     * Filament's default the upload would follow FILESYSTEM_DISK, which is the
     * private disk here, and every inline image would 404 on the site.
     *
     * @return array<int, Toggle|CodeEditor|RichEditor>
     */
    public static function make(string $field = 'body', string $attachmentsDirectory = 'rich-content'): array
    {
        $modeField = "{$field}_html_mode";
        $sourceField = "{$field}_html";

        $richEditor = RichEditor::make($field)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory($attachmentsDirectory)
            ->fileAttachmentsVisibility('public')
            ->hidden(fn (Get $get): bool => (bool) $get($modeField))
            ->dehydratedWhenHidden()
            ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => $get($modeField) ? self::sanitize($get($sourceField)) : $state)
            ->columnSpanFull();

        return [
            Toggle::make($modeField)
                ->label('Edit as HTML')
                ->helperText('Write or paste raw HTML. Scripts and event handlers are removed on save. Switching back to the visual editor drops markup it does not support, such as custom classes.')
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(fn (Toggle $component): Toggle => $component->state(self::needsHtmlMode($richEditor->getRawState(), $richEditor)))
                ->afterStateUpdated(function (bool $state, Get $get, Set $set) use ($field, $sourceField): void {
                    if ($state) {
                        $set($sourceField, $get($field));

                        return;
                    }

                    $set($field, $get($sourceField));
                })
                ->columnSpanFull(),
            CodeEditor::make($sourceField)
                ->label('HTML')
                ->language(Language::Html)
                ->wrap()
                ->visible(fn (Get $get): bool => (bool) $get($modeField))
                ->dehydrated(false)
                ->afterStateHydrated(fn (CodeEditor $component): CodeEditor => $component->state(is_string($raw = $richEditor->getRawState()) ? $raw : null))
                ->columnSpanFull(),
            $richEditor,
        ];
    }

    private static function needsHtmlMode(mixed $html, RichEditor $richEditor): bool
    {
        if (! is_string($html) || blank($html)) {
            return false;
        }

        $roundTripped = $richEditor->getTipTapEditor()->setContent($html)->getHtml();

        return self::collapseWhitespace($roundTripped) !== self::collapseWhitespace($html);
    }

    private static function collapseWhitespace(string $html): string
    {
        return (string) preg_replace('/>\s+</', '><', trim($html));
    }

    private static function sanitize(?string $html): ?string
    {
        return blank($html) ? null : Str::sanitizeHtml($html);
    }
}
