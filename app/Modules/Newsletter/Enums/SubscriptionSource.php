<?php

namespace App\Modules\Newsletter\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a signup form was rendered. The form posts this as a hidden field, so
 * it is visitor-supplied and MUST be validated against these cases before it is
 * stored — an unrecognised value falls back to Website rather than being
 * trusted, which also keeps the admin's source filter a closed list.
 */
enum SubscriptionSource: string implements HasLabel
{
    case Homepage = 'homepage';
    case Blog = 'blog';
    case BlogIndex = 'blog_index';
    case Footer = 'footer';
    case Website = 'website';

    public function getLabel(): string
    {
        return match ($this) {
            self::Homepage => 'Homepage',
            self::Blog => 'Blog article',
            self::BlogIndex => 'Blog listing',
            self::Footer => 'Footer',
            self::Website => 'Website',
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Website;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])->all();
    }
}
