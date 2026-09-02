<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an immutable AuditLog row on create/update/delete. Native Eloquent events —
 * no third-party package — scoped to the models that actually need a change history
 * (see .ai/rules for which ones and why).
 *
 * Every changed field is stored as ['old' => ..., 'new' => ...] (old is null on
 * create, new is null on delete), so the Filament History UI can render a
 * consistent before/after line for any action without special-casing per type.
 *
 * A model with any field that should never be logged (PII, KYC, credit/financial
 * detail, secrets/tokens) must override auditExcept() to list those keys — they're
 * stripped before a row is ever written, not just hidden in the UI.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $changes = collect($model->auditableAttributes())
                ->mapWithKeys(fn ($value, $key) => [$key => ['old' => null, 'new' => $value]])
                ->all();

            $model->writeAuditLog('created', $changes ?: null);
        });

        static::updated(function ($model): void {
            $changes = $model->auditableChanges();

            if ($changes !== []) {
                $model->writeAuditLog('updated', $changes);
            }
        });

        static::deleted(function ($model): void {
            $changes = collect($model->auditableAttributes())
                ->mapWithKeys(fn ($value, $key) => [$key => ['old' => $value, 'new' => null]])
                ->all();

            $model->writeAuditLog('deleted', $changes ?: null);
        });
    }

    public function auditLogs(): MorphMany
    {
        // created_at has second-level precision, so two log rows written within the
        // same second tie — break ties with id, which always reflects write order.
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Fields this model's audit trail must never record — PII, KYC, credit/financial
     * detail, passwords, API keys, tokens, or any other secret. Empty by default;
     * override on any model whose columns include something sensitive.
     *
     * @return array<int, string>
     */
    protected function auditExcept(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditableAttributes(): array
    {
        $ignored = ['created_at', 'updated_at', 'deleted_at', ...$this->auditExcept()];

        return collect($this->attributesToArray())
            ->except($ignored)
            ->all();
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function auditableChanges(): array
    {
        $ignored = ['updated_at', 'created_at', ...$this->auditExcept()];

        return collect($this->getChanges())
            ->except($ignored)
            ->mapWithKeys(fn ($newValue, $key) => [
                $key => ['old' => $this->getOriginal($key), 'new' => $newValue],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>|null  $changes
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
