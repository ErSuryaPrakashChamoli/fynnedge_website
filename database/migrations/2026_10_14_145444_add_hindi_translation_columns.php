<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every visitor-facing text column gets a nullable Hindi sibling named
     * `{column}_hi`, placed right after it. HasTranslations serves the sibling
     * while the site runs in Hindi and falls back to the English column when
     * it is blank, so adding these changes nothing until a translation exists.
     *
     * Each sibling has the same type as its English column; json columns hold
     * the same shape (a list of strings, or of label/value items).
     *
     * @var array<string, array<string, string>> table => [column => column type]
     */
    private const COLUMNS = [
        'achievements' => ['label' => 'string', 'prefix' => 'string', 'suffix' => 'string'],
        'articles' => ['title' => 'string', 'excerpt' => 'string', 'image_alt' => 'string', 'body' => 'longText'],
        'banners' => ['image_alt' => 'string', 'heading' => 'string', 'subtitle' => 'string', 'cta_label' => 'string'],
        'calculator_pages' => ['heading' => 'string', 'description' => 'text', 'meta_title' => 'string', 'meta_description' => 'text', 'title' => 'string', 'body' => 'longText'],
        'company_photos' => ['photo_alt' => 'string', 'caption' => 'string'],
        'credit_score_pages' => ['title' => 'string', 'body' => 'longText'],
        'document_types' => ['label' => 'string', 'description' => 'string'],
        'eligibility_rules' => ['label' => 'string', 'customer_message' => 'string'],
        'faqs' => ['question' => 'string', 'answer' => 'longText'],
        'grievance_levels' => ['level' => 'string', 'turnaround_time' => 'string', 'contact_name' => 'string', 'designation' => 'string', 'address' => 'string'],
        'how_it_works_steps' => ['title' => 'string', 'description' => 'text'],
        'job_openings' => ['title' => 'string'],
        'journey_steps' => ['title' => 'string', 'description' => 'text'],
        'journey_step_fields' => ['label' => 'string', 'options' => 'json', 'help_text' => 'string'],
        'lender_product_document_requirements' => ['notes' => 'string'],
        'lender_product_rates' => ['variant' => 'string', 'borrower_segment' => 'string', 'promo_label' => 'string', 'eligibility_notes' => 'text', 'disclaimer' => 'text'],
        'lender_products' => ['processing_fee_note' => 'string'],
        'lenders' => ['description' => 'text'],
        'loan_landing_pages' => ['title' => 'string', 'city' => 'string', 'excerpt' => 'string', 'cta_label' => 'string', 'body' => 'longText'],
        'loan_products' => [
            'name' => 'string',
            'marketing_headline' => 'string',
            'summary' => 'string',
            'body' => 'longText',
            'image_alt' => 'string',
            'cta_label' => 'string',
            'features' => 'json',
            'benefits' => 'json',
            'eligibility_points' => 'json',
            'documents_required' => 'json',
            'process_steps' => 'json',
            'interest_rate_note' => 'string',
            'calculator_explanation' => 'longText',
        ],
        'marketing_sections' => ['heading' => 'string', 'subheading' => 'string', 'description' => 'text', 'image_alt' => 'string', 'cta_label' => 'string'],
        'navigation_links' => ['label' => 'string'],
        'pages' => ['title' => 'string', 'excerpt' => 'string', 'body' => 'longText'],
        'promo_bars' => ['eyebrow' => 'string', 'headline' => 'string', 'rotating_messages' => 'json', 'cta_label' => 'string', 'image_alt' => 'string'],
        'seo_metas' => ['title' => 'string', 'description' => 'string'],
        'social_videos' => ['title' => 'string', 'caption' => 'text', 'poster_alt' => 'string'],
        'testimonials' => ['customer_name' => 'string', 'role_location' => 'string', 'quote' => 'text', 'avatar_alt' => 'string'],
        'video_testimonials' => ['customer_name' => 'string', 'role_location' => 'string', 'customer_photo_alt' => 'string', 'headline' => 'string', 'quote' => 'text', 'poster_alt' => 'string'],
    ];

    /**
     * Hindi runs longer than the English it translates (often 1.2–1.5× the
     * characters), so a 255-character English line needs more room than 255.
     */
    private const HINDI_STRING_LENGTH = 500;

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column => $type) {
                    $translation = $type === 'string'
                        ? $table->string("{$column}_hi", self::HINDI_STRING_LENGTH)
                        : $table->{$type}("{$column}_hi");

                    $translation->nullable()->after($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            $existing = array_values(array_filter(
                array_map(fn (string $column): string => "{$column}_hi", array_keys($columns)),
                fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($existing !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
            }
        }
    }
};
