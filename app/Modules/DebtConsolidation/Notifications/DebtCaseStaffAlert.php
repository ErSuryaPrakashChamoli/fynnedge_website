<?php

namespace App\Modules\DebtConsolidation\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the Debt Consolidation team (settings: staff notification email, or
 * the assigned advisor) that a case needs attention. Carries the reference,
 * applicant type and a link — never the person's name, number or figures.
 */
class DebtCaseStaffAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $reference,
        public readonly string $summary,
        public readonly string $url,
    ) {
        $this->afterCommit();
    }

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
            ->subject('Debt Consolidation: '.$this->subjectLine.' ('.$this->reference.')')
            ->line($this->summary)
            ->line('Reference: '.$this->reference)
            ->action('Open the case', $this->url);
    }
}
