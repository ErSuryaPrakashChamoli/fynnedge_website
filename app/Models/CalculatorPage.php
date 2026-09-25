<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\Calculators\CalculatorCatalog;
use Database\Factories\CalculatorPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable page copy, one row per calculator page in
 * CalculatorCatalog, keyed by its path under /calculators (`gst`,
 * `emi/home-loan`, `eligibility/personal-loan` …) so every page's content is
 * independent. Loan calculators without a row fall back to the loan type's
 * LoanProduct::calculator_explanation (then summary), which EMI, Eligibility
 * and Prepayment pages of the same loan type share.
 *
 * The row can also override the page's own headline, introduction and meta
 * title/description; blank fields keep the shared wording from
 * CalculatorPagesContent (Website Settings → Calculators Page).
 */
#[Fillable(['calculator_key', 'heading', 'description', 'meta_title', 'meta_description', 'title', 'body'])]
class CalculatorPage extends Model
{
    /** @use HasFactory<CalculatorPageFactory> */
    use HasFactory, HasPublicId;

    public const CONTENT_FIELDS = ['heading', 'description', 'meta_title', 'meta_description'];

    /**
     * Gives every page in CalculatorCatalog a (blank) row, so the admin list
     * shows each calculator ready to edit. Blank rows change nothing on the
     * site: every empty field falls back to its shared default.
     */
    public static function ensureRowForEveryCalculator(): void
    {
        $existing = self::query()->pluck('calculator_key')->all();

        foreach (array_diff(array_keys(CalculatorCatalog::pages()), $existing) as $pageKey) {
            self::query()->create(['calculator_key' => $pageKey]);
        }
    }

    /**
     * The page's shared heading copy with this page's own filled-in fields
     * laid over it, so each calculator page can read differently.
     *
     * @param  array<string, string>  $content
     * @return array<string, string>
     */
    public static function contentFor(string $pageKey, array $content): array
    {
        $page = self::query()->where('calculator_key', $pageKey)->first();

        foreach (self::CONTENT_FIELDS as $field) {
            if (filled($page?->{$field})) {
                $content[$field] = trim($page->{$field});
            }
        }

        return $content;
    }

    /**
     * The "About" section for a calculator page. A body that is blank once
     * the editor's empty markup is stripped counts as no content, so the
     * page can hide the section instead of showing a heading with nothing
     * under it.
     *
     * @return array{heading: string, body: ?string}
     */
    public static function aboutFor(string $pageKey, string $defaultHeading, ?string $fallbackBody = null): array
    {
        $page = self::query()->where('calculator_key', $pageKey)->first();

        $body = collect([$page?->body, $fallbackBody])
            ->first(fn (?string $html): bool => trim(html_entity_decode(strip_tags((string) $html, '<img><iframe><video><table>')), " \n\r\t\v\0\u{A0}") !== '');

        return [
            'heading' => filled($page?->title) ? $page->title : $defaultHeading,
            'body' => $body,
        ];
    }
}
