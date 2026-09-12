<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EnquiryType: string implements HasLabel
{
    case Contact = 'contact';
    case QuickEnquiry = 'quick_enquiry';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contact => 'Contact Form',
            self::QuickEnquiry => 'Quick Enquiry',
        };
    }
}
