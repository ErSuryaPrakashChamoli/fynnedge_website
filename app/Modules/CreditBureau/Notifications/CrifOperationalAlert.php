<?php

namespace App\Modules\CreditBureau\Notifications;

use App\Filament\Resources\CrifReports\CrifReportResource;
use App\Modules\CreditBureau\Models\CrifReport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the operations contact (CRIF Settings → alert email) about an attempt
 * that needs a human: an uncertain result awaiting reconciliation, or a
 * configuration problem (token, balance) that will fail every request until
 * fixed. Carries only the masked PAN, the status and a link — never customer
 * details or report contents.
 */
class CrifOperationalAlert extends Notification
{
    public function __construct(private readonly CrifReport $report) {}

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
            ->subject('CRIF report needs attention: '.$this->report->status->getLabel())
            ->line('A CRIF credit report request needs attention.')
            ->line('Status: '.$this->report->status->getLabel())
            ->line('Reason: '.($this->report->error_code?->getLabel() ?? '—'))
            ->line('PAN: '.$this->report->pan_masked)
            ->line($this->report->error_code?->safeMessage() ?? '')
            ->action('Open the request', CrifReportResource::getUrl('view', ['record' => $this->report], panel: 'admin'));
    }
}
