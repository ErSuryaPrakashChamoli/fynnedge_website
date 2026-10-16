<?php

namespace App\Modules\Cibil\Notifications;

use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Modules\Cibil\Models\CibilCreditRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the CIBIL operations contact (CIBIL Settings → alert email) about
 * an attempt that needs a human: an uncertain result awaiting reconciliation,
 * or a configuration problem (token, plan, balance) that fails every request
 * until fixed. Carries only the masked PAN, the status and a link.
 */
class CibilOperationalAlert extends Notification
{
    public function __construct(private readonly CibilCreditRequest $request) {}

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
            ->subject('CIBIL request needs attention: '.$this->request->status->getLabel())
            ->line('A CIBIL '.$this->request->product->getLabel().' request needs attention.')
            ->line('Status: '.$this->request->status->getLabel())
            ->line('Reason: '.($this->request->error_code?->getLabel() ?? '—'))
            ->line('PAN: '.$this->request->pan_masked)
            ->line($this->request->error_code?->safeMessage() ?? '')
            ->action('Open the request', CibilCreditRequestResource::getUrl('view', ['record' => $this->request], panel: 'admin'));
    }
}
