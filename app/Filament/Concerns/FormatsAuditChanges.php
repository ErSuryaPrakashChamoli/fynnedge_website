<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Shared "Label: old → new" rendering for an AuditLog's `changes` column —
 * used by AuditLogsRelationManager (the per-record History tab) and the
 * Activity Dashboard (the cross-record admin activity feed), so both render
 * audit diffs identically without duplicating the formatting logic.
 */
trait FormatsAuditChanges
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public static function formatChanges(?array $changes): string
    {
        if (! $changes) {
            return '—';
        }

        return collect($changes)
            ->map(function (mixed $change, string $key) {
                $label = e(Str::headline($key));

                // A handful of rows predate this trait storing {old, new}
                // pairs and hold a bare value instead — render those as a
                // single value rather than fail on the shape mismatch.
                if (! is_array($change) || ! array_key_exists('old', $change) || ! array_key_exists('new', $change)) {
                    return "<strong>{$label}:</strong> ".static::formatValue($change);
                }

                $old = static::formatValue($change['old']);
                $new = static::formatValue($change['new']);

                return match (true) {
                    $change['old'] === null && $change['new'] !== null => "<strong>{$label}:</strong> {$new}",
                    $change['new'] === null && $change['old'] !== null => "<strong>{$label}:</strong> {$old} → <em>removed</em>",
                    default => "<strong>{$label}:</strong> {$old} → {$new}",
                };
            })
            ->implode('<br>');
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<span style="opacity:.6">—</span>';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $encoded = json_encode($value);

            return e(Str::limit($encoded, 80));
        }

        return e(Str::limit((string) $value, 120));
    }
}
