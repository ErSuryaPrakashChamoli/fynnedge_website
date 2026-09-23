<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\CalculatorPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable "About the calculator" copy, one row per calculator page in
 * CalculatorCatalog, keyed by its path under /calculators (`gst`,
 * `emi/home-loan`, `eligibility/personal-loan` …) so every page's content is
 * independent. Loan calculators without a row fall back to the loan type's
 * LoanProduct::calculator_explanation (then summary), which EMI, Eligibility
 * and Prepayment pages of the same loan type share.
 */
#[Fillable(['calculator_key', 'title', 'body'])]
class CalculatorPage extends Model
{
    /** @use HasFactory<CalculatorPageFactory> */
    use HasFactory, HasPublicId;

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
