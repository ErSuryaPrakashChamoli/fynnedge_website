<?php

namespace App\Modules\Applications\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasLabel
{
    case Uploaded = 'uploaded';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }
}
