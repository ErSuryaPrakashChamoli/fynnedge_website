<?php

namespace App\Modules\DebtConsolidation\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The second pair of eyes. The "maker" of a record is its author (created_by
 * or recorded_by) AND anyone who changed it (from the audit trail), so
 * editing someone else's record and then approving it is refused the same
 * way as approving your own.
 *
 * Lender policies and scoring configurations use isMaker() (the super admin
 * may approve). Foreclosure quotations, closure payments and closure
 * verification use ensureIndependent(): mandatory, no exemption.
 */
final class MakerChecker
{
    public static function isMaker(Model $record, ?int $userId): bool
    {
        if ($userId === null) {
            return true;
        }

        if ((int) $record->getAttribute('created_by') === $userId || (int) $record->getAttribute('recorded_by') === $userId) {
            return true;
        }

        return method_exists($record, 'auditLogs')
            && $record->auditLogs()->where('user_id', $userId)->whereIn('action', ['created', 'updated'])->exists();
    }

    /**
     * Mandatory independent verification (OPS-D1, Owner decision 2026-11-26).
     * The second step on a foreclosure quotation, a closure payment or a
     * closure must be done by someone who neither recorded nor changed that
     * record, nor moved it through any of its statuses. No one is exempt,
     * the super admin included.
     *
     * @throws ValidationException
     */
    public static function ensureIndependent(Model $record, ?int $userId, string $field, string $message): void
    {
        $workedOnIt = self::isMaker($record, $userId)
            || (method_exists($record, 'statusChanges') && $record->statusChanges()->where('user_id', $userId)->exists());

        if ($workedOnIt) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
