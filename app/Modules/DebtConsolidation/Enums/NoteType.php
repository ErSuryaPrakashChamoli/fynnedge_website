<?php

namespace App\Modules\DebtConsolidation\Enums;

use Filament\Support\Contracts\HasLabel;

enum NoteType: string implements HasLabel
{
    case InternalRemark = 'internal_remark';
    case Call = 'call';
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';
    case CustomerMessage = 'customer_message';
    case FollowUp = 'follow_up';

    public function getLabel(): string
    {
        return match ($this) {
            self::InternalRemark => __('Internal remark'),
            self::Call => __('Phone call'),
            self::Email => __('Email'),
            self::Sms => __('SMS'),
            self::WhatsApp => __('WhatsApp'),
            self::Meeting => __('Meeting'),
            self::CustomerMessage => __('Message from customer'),
            self::FollowUp => __('Follow-up'),
        };
    }
}
