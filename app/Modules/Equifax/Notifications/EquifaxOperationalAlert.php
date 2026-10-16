<?php

namespace App\Modules\Equifax\Notifications;

use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the Equifax operations contact (Equifax Settings → alert email) about
 * an attempt that needs a human: an uncertain result awaiting reconciliation,
 * or a configuration problem (token, plan, balance) that fails every request
 * until fixed. Carries only the masked PAN, the status and a link.
 */
class EquifaxOperationalAlert extends Notification
{
    public function __construct(private readonly EquifaxCreditRequest $request) {}

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
            ->subject('Equifax request needs attention: '.$this->request->status->getLabel())
            ->line('An Equifax '.$this->request->product->getLabel().' request needs attention.')
            ->line('Status: '.$this->request->status->getLabel())
            ->line('Reason: '.($this->request->error_code?->getLabel() ?? '—'))
            ->line('PAN: '.$this->request->pan_masked)
            ->line($this->request->error_code?->safeMessage() ?? '')
            ->action('Open the request', EquifaxCreditRequestResource::getUrl('view', ['record' => $this->request], panel: 'admin'));
    }
}
