<?php

namespace App\Support\Media;

use App\Models\Banner;
use App\Models\CompanyPhoto;
use App\Models\HowItWorksStep;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Models\MarketingSection;
use App\Models\SeoMeta;
use App\Models\Testimonial;

/**
 * The curated list of genuine media-path fields the Media Governance catalog
 * scans. Adding a new image field to a model/Setting means adding one entry
 * here — nothing else discovers media automatically, by design (see 5.1: "do
 * not blindly scan every database column").
 */
final class MediaRegistry
{
    /**
     * @return array<int, MediaFieldDefinition>
     */
    public static function all(): array
    {
        return [
            MediaFieldDefinition::forModel(
                modelClass: Banner::class,
                label: 'Banner',
                pathField: 'image_path',
                directory: 'banners',
                altField: 'image_alt',
                recordLabelField: 'heading',
            ),
            MediaFieldDefinition::forModel(
                modelClass: CompanyPhoto::class,
                label: 'Company Photo',
                pathField: 'photo_path',
                directory: 'company-photos',
                altField: 'photo_alt',
                recordLabelField: 'caption',
            ),
            MediaFieldDefinition::forModel(
                modelClass: Testimonial::class,
                label: 'Testimonial',
                pathField: 'avatar_path',
                directory: 'testimonials',
                altField: 'avatar_alt',
                recordLabelField: 'customer_name',
                withTrashed: true,
            ),
            MediaFieldDefinition::forModel(
                modelClass: LoanProduct::class,
                label: 'Loan Product',
                pathField: 'image_path',
                directory: 'loan-products',
                altField: 'image_alt',
                recordLabelField: 'name',
                withTrashed: true,
            ),
            MediaFieldDefinition::forModel(
                modelClass: MarketingSection::class,
                label: 'Marketing Section',
                pathField: 'image_path',
                directory: 'marketing-sections',
                altField: 'image_alt',
                recordLabelField: 'heading',
                withTrashed: true,
            ),
            MediaFieldDefinition::forModel(
                modelClass: Lender::class,
                label: 'Lender Logo',
                pathField: 'logo_path',
                directory: 'lenders',
                recordLabelField: 'name',
                withTrashed: true,
            ),
            MediaFieldDefinition::forModel(
                modelClass: HowItWorksStep::class,
                label: 'How It Works Step',
                pathField: 'icon_path',
                directory: 'how-it-works',
                recordLabelField: 'title',
            ),
            MediaFieldDefinition::forModel(
                modelClass: SeoMeta::class,
                label: 'SEO Social Image',
                pathField: 'og_image_path',
                directory: 'seo',
                recordLabelField: null,
            ),
            MediaFieldDefinition::forSetting(
                settingKey: 'site_logo',
                label: 'Site Logo',
                directory: 'branding',
            ),
            MediaFieldDefinition::forSetting(
                settingKey: 'site_favicon',
                label: 'Site Favicon',
                directory: 'branding',
            ),
            MediaFieldDefinition::forSetting(
                settingKey: 'seo_default_og_image',
                label: 'Default SEO Social Image',
                directory: 'seo',
            ),
            MediaFieldDefinition::forSetting(
                settingKey: 'founder_photo',
                label: 'Founder Photo',
                directory: 'founder',
            ),
        ];
    }
}
