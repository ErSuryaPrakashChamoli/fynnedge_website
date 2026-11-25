<?php

namespace App\Modules\FynnOn\Services;

use App\Models\AuditLog;
use App\Modules\Applications\Models\Application;
use App\Modules\FynnOn\DataTransferObjects\FynnOnDelivery;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\FynnOn\Jobs\SendLeadToFynnOn;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use App\Modules\MobileOtp\Enums\MobileOtpStatus;
use App\Modules\MobileOtp\Models\MobileOtpChallenge;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues leads for FynnOn LMS and keeps their delivery history.
 *
 * History is stored in the existing `audit_logs` table (no new table): one
 * immutable row per delivery attempt on the lead itself, action
 * `fynnon.sent|duplicate|failed|abandoned`, user_id null (the system), and
 * `changes` = {event, reference, attempt, http_status, lms_reference, error,
 * duration_ms}. No contact details are copied into the log.
 */
final class FynnOnSync
{
    public const EVENT_CREATED = 'lead.created';

    public const EVENT_REOPENED = 'lead.reopened';

    /**
     * Queue a lead if the LMS is configured, its source is enabled, and it is a
     * lead yet (see isEligible()). Returns whether a job was dispatched.
     */
    public static function queue(Model $lead, string $event = self::EVENT_CREATED): bool
    {
        $source = LeadSource::fromModel($lead);

        if ($source === null || ! FynnOnSettings::sends($source) || ! self::isEligible($source, $lead)) {
            return false;
        }

        SendLeadToFynnOn::dispatch($source->value, (int) $lead->getKey(), $event);

        return true;
    }

    /**
     * An admin's "Send to LMS": resends even a lead already delivered (the
     * LMS receives the same reference and Idempotency-Key, so it updates
     * rather than duplicates).
     */
    public static function queueManually(LeadSource $source, int $leadId): bool
    {
        $lead = $source->modelClass()::query()->find($leadId);

        if ($lead === null || ! FynnOnSettings::isConfigured() || ! self::isEligible($source, $lead)) {
            return false;
        }

        SendLeadToFynnOn::dispatch($source->value, $leadId, self::EVENT_CREATED, force: true);

        return true;
    }

    /**
     * When a record becomes a lead worth calling: an enquiry or score check as
     * soon as it exists; an online application once its mobile is verified; a
     * loan application once submitted; an OTP once verified (never a portal
     * sign-in, which is an existing customer, not a new lead).
     */
    public static function isEligible(LeadSource $source, Model $lead): bool
    {
        return match ($source) {
            LeadSource::Enquiry, LeadSource::CreditScoreCheck => true,
            LeadSource::OnlineApplication => $lead instanceof JourneySession && filled($lead->phone_number) && $lead->phone_verified_at !== null,
            LeadSource::LoanApplication => $lead instanceof Application && $lead->submitted_at !== null,
            LeadSource::OtpVerification => $lead instanceof MobileOtpChallenge
                && $lead->status === MobileOtpStatus::Verified
                && $lead->purpose !== MobileOtpPurpose::CustomerLogin,
        };
    }

    public static function latest(Model $lead): ?AuditLog
    {
        return AuditLog::query()
            ->where('auditable_type', $lead->getMorphClass())
            ->where('auditable_id', $lead->getKey())
            ->whereIn('action', FynnOnSyncStatus::recordedActions())
            ->orderByDesc('id')
            ->first();
    }

    public static function status(Model $lead): FynnOnSyncStatus
    {
        return FynnOnSyncStatus::fromAction(self::latest($lead)?->action);
    }

    /**
     * Idempotency guard: has this event for this lead already been confirmed
     * by the LMS? A queued job re-delivered by the queue (or dispatched twice)
     * then does nothing.
     */
    public static function wasDelivered(Model $lead, string $event): bool
    {
        return AuditLog::query()
            ->where('auditable_type', $lead->getMorphClass())
            ->where('auditable_id', $lead->getKey())
            ->whereIn('action', [FynnOnSyncStatus::Sent->value, FynnOnSyncStatus::Duplicate->value])
            ->get(['changes'])
            ->contains(fn (AuditLog $log): bool => ($log->changes['event'] ?? self::EVENT_CREATED) === $event);
    }

    public static function record(Model $lead, FynnOnSyncStatus $status, FynnOnDelivery $delivery, int $attempt, string $event, string $reference): AuditLog
    {
        return AuditLog::query()->create([
            'auditable_type' => $lead->getMorphClass(),
            'auditable_id' => $lead->getKey(),
            'user_id' => null,
            'action' => $status->value,
            'changes' => $delivery->toLog($attempt, $event, $reference),
        ]);
    }

    /**
     * Managing LMS delivery is part of working leads: the same permission as
     * updating an enquiry (super admins included).
     */
    public static function canManage(): bool
    {
        return (bool) auth()->user()?->can('Update:ContactEnquiry');
    }

    public static function canResend(LeadInboxEntry $entry): bool
    {
        return FynnOnSettings::isConfigured()
            && FynnOnSettings::sends($entry->source)
            && self::canManage()
            && ! $entry->syncStatus()->isDelivered()
            && match ($entry->source) {
                LeadSource::OnlineApplication, LeadSource::OtpVerification => $entry->verified_at !== null,
                default => true,
            };
    }
}
