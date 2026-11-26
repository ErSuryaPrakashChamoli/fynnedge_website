<?php

namespace App\Modules\DebtConsolidation\Notifications;

use App\Modules\DebtConsolidation\Enums\ClosureStatus;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtLenderApplication;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * The one place that decides who hears about what on a case. Customer
 * messages go to the portal and, when enabled in settings and the customer
 * gave an email, by email. Staff alerts go to the settings address and the
 * assigned advisor. A failure to notify never breaks the action that
 * triggered it.
 */
class DebtCaseNotifier
{
    use Localizable;

    public function enquiryReceived(DebtApplication $application): void
    {
        $this->customer($application, 'We have received your enquiry', 'Thank you. A FynnEdge debt consolidation advisor will call you shortly. You can add your loans for a detailed assessment at any time.');
        $this->staff($application, 'new enquiry', 'A new '.$application->applicant_type->value.' debt consolidation enquiry was received.');
    }

    public function applicationSubmitted(DebtApplication $application): void
    {
        $this->customer($application, 'Your assessment request is submitted', 'We have your loan details. An advisor will review your assessment and contact you with the options that fit.');
        $this->staff($application, 'assessment submitted', 'A customer submitted a detailed debt consolidation assessment.');
    }

    /**
     * The customer answered staff's request for more information: the case is
     * back with the advisor, who is told.
     */
    public function applicationResubmitted(DebtApplication $application): void
    {
        $this->customer($application, 'We have your updated details', 'Thank you. Your advisor will review the information you added and contact you.');
        $this->staff($application, 'information received', 'The customer updated their debt consolidation case after you asked for more information.');
    }

    /**
     * The team must stop sharing the case for that purpose (for lender sharing:
     * with that lender) from now on.
     */
    public function consentWithdrawn(DebtApplication $application, DebtConsent $consent): void
    {
        $lender = $consent->lenderProduct?->lender?->name;

        $this->staff($application, 'consent withdrawn', 'The customer withdrew their '.str_replace('_', ' ', $consent->purpose->value).' consent'.($lender ? ' for '.$lender : '').'. Do not share their information for that purpose from now on.');
    }

    public function statusChanged(DebtApplication $application, DebtApplicationStatus $status): void
    {
        if (in_array($status, [DebtApplicationStatus::Draft, DebtApplicationStatus::EnquiryReceived], true)) {
            return;
        }

        $this->customer($application, 'Your case status has changed', 'The status of your debt consolidation case is now: :status.', ['status' => $this->english(fn (): string => $status->getLabel())]);
    }

    public function lenderApplicationChanged(DebtLenderApplication $lenderApplication, LenderApplicationStatus $status): void
    {
        if ($status === LenderApplicationStatus::NotSubmitted) {
            return;
        }

        $this->customer($lenderApplication->application, 'Update on your lender application', 'Your application with :lender is now: :status.', [
            'lender' => $lenderApplication->lender_name,
            'status' => $this->english(fn (): string => $status->getLabel()),
        ]);
    }

    public function closureChanged(DebtClosure $closure, ClosureStatus $status): void
    {
        if (! in_array($status, [ClosureStatus::PaymentConfirmed, ClosureStatus::NocReceived, ClosureStatus::ClosureVerified], true)) {
            return;
        }

        $this->customer($closure->application, 'Update on closing an existing loan', 'Closure of your :lender loan is now: :status.', [
            'lender' => $closure->liability?->lender_name ?? '',
            'status' => $this->english(fn (): string => $status->getLabel()),
        ]);
    }

    public function reportReleased(DebtReport $report): void
    {
        $this->customer($report->application, 'A new report is ready', 'Your :report is ready to download from your profile.', ['report' => $this->english(fn (): string => $report->report_type->getLabel())]);
    }

    /**
     * Stored notification text is English (the portal translates it on
     * display), whatever language the request that triggered it used.
     *
     * @param  callable(): string  $label
     */
    private function english(callable $label): string
    {
        return $this->withLocale('en', $label);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function customer(DebtApplication $application, string $title, string $message, array $parameters = []): void
    {
        $customer = $application->customer;

        if ($customer === null) {
            return;
        }

        $url = Route::has('portal.debt.show') ? route('portal.debt.show', $application) : null;

        try {
            $customer->notify(new DebtCaseUpdate(
                $application->reference,
                $title,
                $message,
                $parameters,
                $url,
                (bool) DebtConsolidationSettings::get('customer_email_notifications'),
                $application->email,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function staff(DebtApplication $application, string $subject, string $summary): void
    {
        $recipients = array_values(array_unique(array_filter([
            DebtConsolidationSettings::get('staff_notification_email'),
            $application->assignee?->email,
        ])));

        if ($recipients === [] || ! Route::has('filament.admin.resources.debt-applications.view')) {
            return;
        }

        try {
            Notification::route('mail', $recipients)->notify(new DebtCaseStaffAlert(
                $subject,
                $application->reference,
                $summary,
                route('filament.admin.resources.debt-applications.view', $application),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
