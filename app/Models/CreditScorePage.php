<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Modules\CreditScore\Enums\BureauName;
use Database\Factories\CreditScorePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable "About" copy, one row per /credit-score/{bureau} page, keyed
 * by its bureau (the page's path under /credit-score) so every page's content
 * is independent — the same shape as CalculatorPage. The heading, intro and
 * meta tags above the check form stay in CreditScorePageContent.
 */
#[Fillable(['bureau', 'title', 'body'])]
class CreditScorePage extends Model
{
    /** @use HasFactory<CreditScorePageFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'bureau' => BureauName::class,
        ];
    }

    /**
     * The "About" section for a bureau page. A body that is blank once the
     * editor's empty markup is stripped counts as no content, so the page
     * hides the section instead of showing a heading with nothing under it.
     *
     * @return array{heading: string, body: ?string}
     */
    public static function aboutFor(BureauName $bureau): array
    {
        $page = self::query()->where('bureau', $bureau->value)->first();

        $body = trim(html_entity_decode(strip_tags((string) $page?->body, '<img><iframe><video><table>')), " \n\r\t\v\0\u{A0}") !== ''
            ? $page->body
            : null;

        return [
            'heading' => filled($page?->title) ? $page->title : self::defaultHeading($bureau),
            'body' => $body,
        ];
    }

    public static function defaultHeading(BureauName $bureau): string
    {
        return 'About the '.$bureau->getLabel().' score';
    }
}
