<?php

namespace App\Modules\DebtConsolidation\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * The second pair of eyes on lender policies and scoring configurations. The
 * "maker" of a draft is its author AND anyone who changed it (from the audit
 * trail), so editing someone else's draft and then approving it is refused
 * the same way as approving your own.
 */
final class MakerChecker
{
    public static function isMaker(Model $record, ?int $userId): bool
    {
        if ($userId === null) {
            return true;
        }

        if ((int) $record->getAttribute('created_by') === $userId) {
            return true;
        }

        return method_exists($record, 'auditLogs')
            && $record->auditLogs()->where('user_id', $userId)->whereIn('action', ['created', 'updated'])->exists();
    }
}
