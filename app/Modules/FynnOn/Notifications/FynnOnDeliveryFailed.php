<?php

namespace App\Modules\FynnOn\Notifications;

use App\Filament\Pages\FynnOnSync;
use App\Modules\Leads\Enums\LeadSource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the FynnOn LMS alert contact (FYNNON_LMS_ALERT_EMAIL) that a lead
 * could not be delivered after every retry. Carries the lead reference, the
 * safe error and a link — never the person's name or number.
 */
class FynnOnDeliveryFailed extends Notification
{
    public function __construct(
        private readonly LeadSource $source,
        private readonly string $reference,
        private readonly ?string $error,
        private readonly int $attempts,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('FynnOn LMS: a website lead could not be delivered')
            ->line('A new '.strtolower($this->source->getLabel()).' from the website did not reach FynnOn LMS after '.$this->attempts.' attempt(s).')
            ->line('Reference: '.$this->reference)
            ->line('Reason: '.($this->error ?? 'unknown'))
            ->action('Open FynnOn LMS Sync', FynnOnSync::getUrl(panel: 'admin'));
    }
}
