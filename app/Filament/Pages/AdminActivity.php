<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FormatsAuditChanges;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Reads the SAME audit_logs table/AuditLog model that powers each resource's
 * History tab (see AuditLogsRelationManager) — this is a cross-record feed
 * of that same data, not a second activity-tracking system. Gated on the
 * identical View:AuditLog permission for the same reason: it's the same
 * data, just viewed differently.
 */
class AdminActivity extends Page
{
    use FormatsAuditChanges;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Access Control';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?string $title = 'Admin Activity';

    protected string $view = 'filament.pages.admin-activity';

    #[Url]
    public ?int $userId = null;

    #[Url]
    public ?string $action = null;

    #[Url]
    public ?string $model = null;

    #[Url]
    public ?string $dateFrom = null;

    #[Url]
    public ?string $dateTo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:AuditLog');
    }

    /**
     * @return array<int|string, string>
     */
    public function userOptions(): array
    {
        return ['' => 'All admins', ...User::query()->orderBy('name')->pluck('name', 'id')->all()];
    }

    /**
     * @return array<string, string>
     */
    public function actionOptions(): array
    {
        return ['' => 'All actions', 'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'];
    }

    /**
     * Every distinct model currently represented in the audit log, humanized
     * for the filter dropdown — built from the data itself rather than a
     * hardcoded list, so a newly-audited model appears automatically.
     *
     * @return array<string, string>
     */
    public function modelOptions(): array
    {
        return ['' => 'All entities', ...AuditLog::query()
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->mapWithKeys(fn (string $type) => [$type => Str::headline(class_basename($type))])
            ->all(),
        ];
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user', 'auditable'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }

    private function filteredQuery(): Builder
    {
        return AuditLog::query()
            ->when($this->userId, fn (Builder $query) => $query->where('user_id', $this->userId))
            ->when($this->action, fn (Builder $query) => $query->where('action', $this->action))
            ->when($this->model, fn (Builder $query) => $query->where('auditable_type', $this->model))
            ->when($this->dateFrom, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['userId', 'action', 'model', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    /**
     * A human label for the audited record — tries the common "what is this
     * called" attributes in order, falling back to the record's id/public_id
     * so the row is still identifiable even for a model with none of them.
     */
    public static function recordLabel(AuditLog $log): string
    {
        $record = $log->auditable;

        if (! $record) {
            return "#{$log->auditable_id} (deleted)";
        }

        foreach (['name', 'title', 'heading', 'question', 'customer_name', 'label'] as $attribute) {
            if (filled($record->{$attribute} ?? null)) {
                return (string) $record->{$attribute};
            }
        }

        return $record->public_id ?? (string) $record->getKey();
    }
}
