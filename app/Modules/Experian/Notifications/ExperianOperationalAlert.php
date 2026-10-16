<?php

namespace App\Modules\Experian\Notifications;

use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the Experian operations contact (Experian Settings → alert email) about
 * an attempt that needs a human: an uncertain result awaiting reconciliation,
 * or a configuration problem (token, plan, balance) that fails every request
 * until fixed. Carries only the masked PAN, the status and a link.
 */
class ExperianOperationalAlert extends Notification
{
    public function __construct(private readonly ExperianCreditRequest $request) {}

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
            ->subject('Experian request needs attention: '.$this->request->status->getLabel())
            ->line('An Experian '.$this->request->product->getLabel().' request needs attention.')
            ->line('Status: '.$this->request->status->getLabel())
            ->line('Reason: '.($this->request->error_code?->getLabel() ?? '—'))
            ->line('PAN: '.$this->request->pan_masked)
            ->line($this->request->error_code?->safeMessage() ?? '')
            ->action('Open the request', ExperianCreditRequestResource::getUrl('view', ['record' => $this->request], panel: 'admin'));
    }
}
