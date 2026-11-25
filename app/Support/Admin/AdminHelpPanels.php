<?php

namespace App\Support\Admin;

use App\Filament\Pages\HelpCenter;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;

/**
 * Builds the three help render hooks registered in AdminPanelProvider: the
 * one-line summary under the title, the "Before you start" checklist, and the
 * "About this screen" panel. Each returns '' for screens with no guide entry.
 */
final class AdminHelpPanels
{
    /**
     * @param  array<int, string>  $scopes
     */
    public static function summary(array $scopes): View|string
    {
        $guide = AdminGuide::forScopes($scopes);

        return $guide ? view('filament.admin.screen-summary', ['guide' => $guide]) : '';
    }

    /**
     * Only on list, create and custom pages: on an existing record's edit or
     * view screen the dependency was evidently met when it was created.
     *
     * @param  array<int, string>  $scopes
     */
    public static function prerequisites(array $scopes): View|string
    {
        $page = $scopes[0] ?? null;

        if ($page === null || is_subclass_of($page, EditRecord::class) || is_subclass_of($page, ViewRecord::class)) {
            return '';
        }

        $guide = AdminGuide::forScopes($scopes);

        if (! $guide) {
            return '';
        }

        $missing = collect($guide['needs'])
            ->map(fn (string $key): ?array => AdminRequirements::evaluate($key))
            ->filter(fn (?array $requirement): bool => $requirement !== null && ! $requirement['met'] && $requirement['key'] !== 'author')
            ->values()
            ->all();

        return $missing === [] ? '' : view('filament.admin.screen-prerequisites', ['missing' => $missing]);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public static function guide(array $scopes): View|string
    {
        $guide = AdminGuide::forScopes($scopes);

        if (! $guide) {
            return '';
        }

        $related = collect($guide['related'])
            ->map(fn (string $screen): ?array => ($url = AdminLinks::to($screen)) ? ['url' => $url, 'label' => AdminLinks::label($screen)] : null)
            ->filter()
            ->values()
            ->all();

        $next = null;

        if ($guide['next'] !== null && ($url = AdminLinks::to($guide['next'][0]))) {
            $next = ['url' => $url, 'label' => $guide['next'][1]];
        }

        return view('filament.admin.screen-guide', [
            'guide' => $guide,
            'requirements' => collect($guide['needs'])->map(fn (string $key): ?array => AdminRequirements::evaluate($key))->filter()->values()->all(),
            'related' => $related,
            'next' => $next,
            'helpUrl' => $guide['screen'] === HelpCenter::class ? null : AdminLinks::to(HelpCenter::class),
        ]);
    }
}
