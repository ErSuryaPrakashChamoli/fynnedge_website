<?php

use App\Models\Page;
use App\Models\Setting;
use App\Support\Localization\AutoTranslation;
use App\Support\Pages\DebtConsolidationPageContent;
use Illuminate\Database\Migrations\Migration;

/**
 * The loan-by-loan tool at /debt-consolidation/calculator becomes the "Debt
 * Consolidation Analyser", so it is no longer confused with the Debt
 * Consolidation Savings Calculator under /calculators. The address stays.
 * Only copy still exactly as 2026_11_25_150300 shipped it is changed, so a
 * title or SEO line an editor already rewrote is left alone; down() reverses
 * only what this migration wrote.
 *
 * The same goes for the page wording settings: saving Debt Consolidation
 * Pages stores every field, defaults included, and a stored value beats a
 * new default. So a site saved before the rename would keep "Use the
 * calculator" next to the new Savings Calculator card. Stored values still
 * exactly as shipped (English, and the shipped Hindi) are renamed too.
 */
return new class extends Migration
{
    private const SLUG = 'debt-consolidation-calculator';

    /**
     * @var array<string, array{0: string, 1: string}> field => [shipped, renamed]
     */
    private const PAGE_FIELDS = [
        'title' => ['Debt Consolidation Calculator', 'Debt Consolidation Analyser'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}> field => [shipped, renamed]
     */
    private const SEO_FIELDS = [
        'title' => ['Debt Consolidation Calculator — EMI, Savings & Break-even | FynnEdge', 'Debt Consolidation Analyser — Compare Loans, Foreclosure & Break-even | FynnEdge'],
        'description' => ['Free debt consolidation calculator: add multiple loans and see the new EMI, total repayment, foreclosure charges, FOIR and true net savings.', 'Free debt consolidation analyser: add each loan and card to see the new EMI, total repayment, foreclosure charges, FOIR and true net savings.'],
    ];

    /**
     * @var array<string, array<string, array{0: string, 1: string}>> setting key => field => [shipped, renamed]
     */
    private const SETTING_FIELDS = [
        DebtConsolidationPageContent::SETTING_KEY => [
            'secondary_cta_label' => ['Use the calculator', 'Analyse my loans'],
            'calculator_card_title' => ['Debt consolidation calculator', 'Debt analyser'],
            'calculator_card_body' => ['Add your loans and compare your current repayments with one consolidated loan — total cost, monthly outgo, foreclosure charges and break-even.', 'A detailed, loan-by-loan comparison: add each loan and card to see total cost, monthly outgo, foreclosure charges, affordability and break-even.'],
        ],
        DebtConsolidationPageContent::HINDI_SETTING_KEY => [
            'secondary_cta_label' => ['कैलकुलेटर इस्तेमाल करें', 'मेरे लोन का विश्लेषण करें'],
            'calculator_card_title' => ['डेट कंसॉलिडेशन कैलकुलेटर', 'डेट एनालाइज़र'],
            'calculator_card_body' => ['अपने लोन जोड़ें और अपने मौजूदा भुगतानों की तुलना एक कंसॉलिडेटेड लोन से करें — कुल लागत, मासिक खर्च, फोरक्लोज़र चार्ज और ब्रेक-ईवन।', 'हर लोन की विस्तृत तुलना: हर लोन और कार्ड जोड़ें और कुल लागत, मासिक खर्च, फोरक्लोज़र चार्ज, वहन क्षमता और ब्रेक-ईवन देखें।'],
        ],
    ];

    public function up(): void
    {
        $this->swap(from: 0, to: 1);
        $this->swapSettings(from: 0, to: 1);
    }

    public function down(): void
    {
        $this->swap(from: 1, to: 0);
        $this->swapSettings(from: 1, to: 0);
    }

    private function swapSettings(int $from, int $to): void
    {
        foreach (self::SETTING_FIELDS as $key => $fields) {
            $saved = Setting::query()->where('key', $key)->value('value');

            if (! is_array($saved)) {
                continue;
            }

            $changed = false;

            foreach ($fields as $field => $values) {
                if (($saved[$field] ?? null) === $values[$from]) {
                    $saved[$field] = $values[$to];
                    $changed = true;
                }
            }

            if ($changed) {
                // Both languages are swapped here, so no machine translation is queued.
                AutoTranslation::paused(fn () => Setting::set($key, $saved));
            }
        }
    }

    private function swap(int $from, int $to): void
    {
        $page = Page::withTrashed()->where('slug', self::SLUG)->with('seoMeta')->first();

        if ($page === null) {
            return;
        }

        foreach (self::PAGE_FIELDS as $field => $values) {
            if ($page->getRawOriginal($field) === $values[$from]) {
                $page->{$field} = $values[$to];
            }
        }

        // Without touching updated_at: 2026_11_25_150300's down() removes a draft
        // page only while updated_at still equals created_at (never edited), so a
        // timestamp written here would keep the page through a full rollback.
        $page->timestamps = false;
        $page->save();

        if ($page->seoMeta === null) {
            return;
        }

        foreach (self::SEO_FIELDS as $field => $values) {
            if ($page->seoMeta->getRawOriginal($field) === $values[$from]) {
                $page->seoMeta->{$field} = $values[$to];
            }
        }

        $page->seoMeta->timestamps = false;
        $page->seoMeta->save();
    }
};
