<?php

namespace App\Modules\DebtConsolidation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A customer-facing update on their debt consolidation case, kept in the
 * portal (database) and emailed when the customer gave an address: the one
 * on the case (typed in the enquiry or application), else the account's
 * (Customer::routeNotificationForMail). The
 * stored text is the English source; the portal shows it through __() in
 * the visitor's language. Never carries figures, documents or lender
 * decisions beyond their status — those are seen in the signed-in portal.
 */
class DebtCaseUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $title,
        public readonly string $message,
        public readonly array $parameters = [],
        public readonly ?string $url = null,
        public readonly bool $sendEmail = true,
        public readonly ?string $recipientEmail = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->sendEmail && filled($this->recipientEmail ?? $notifiable->email ?? null) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__($this->title, $this->parameters).' — '.$this->reference)
            ->greeting(__('Hello'))
            ->line(__($this->message, $this->parameters))
            ->line(__('Your reference: :reference', ['reference' => $this->reference]));

        if ($this->url !== null) {
            $mail->action(__('View my case'), $this->url);
        }

        return $mail->line(__('FynnEdge is a loan advisory and facilitation company. Credit decisions are made only by the lender.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->reference,
            'title' => $this->title,
            'message' => $this->message,
            'parameters' => $this->parameters,
            'url' => $this->url,
        ];
    }
}
