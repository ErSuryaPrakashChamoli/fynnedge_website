<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an immutable AuditLog row on create/update/delete. Native Eloquent events —
 * no third-party package — scoped to the models that actually need a change history
 * (see .ai/rules for which ones and why).
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $model->writeAuditLog('created', $model->attributesToArray());
        });

        static::updated(function ($model): void {
            $changes = $model->auditableChanges();

            if ($changes !== []) {
                $model->writeAuditLog('updated', $changes);
            }
        });

        static::deleted(function ($model): void {
            $model->writeAuditLog('deleted', null);
        });
    }

    public function auditLogs(): MorphMany
    {
        // created_at has second-level precision, so two log rows written within the
        // same second tie — break ties with id, which always reflects write order.
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditableChanges(): array
    {
        $ignored = ['updated_at', 'created_at'];

        return collect($this->getChanges())
            ->except($ignored)
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    protected function writeAuditLog(string $action, ?array $changes): void
    {
        AuditLog::query()->create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'user_id' => Auth::id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
