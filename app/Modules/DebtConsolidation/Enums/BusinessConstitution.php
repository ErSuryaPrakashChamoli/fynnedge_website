<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum BusinessConstitution: string implements HasLabel
{
    case Proprietorship = 'proprietorship';
    case Partnership = 'partnership';
    case Llp = 'llp';
    case PrivateLimited = 'private_limited';
    case PublicLimited = 'public_limited';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proprietorship => __('Sole proprietorship'),
            self::Partnership => __('Partnership firm'),
            self::Llp => __('LLP'),
            self::PrivateLimited => __('Private limited company'),
            self::PublicLimited => __('Public limited company'),
            self::Other => __('Other'),
        };
    }
}
