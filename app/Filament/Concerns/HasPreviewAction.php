<?php

namespace App\Filament\Concerns;

use Closure;
use Filament\Actions\Action;
use Illuminate\Support\Facades\URL;

/**
 * A "Preview" header action for an Edit page of a Publishable model — opens
 * the real public route via a short-lived signed URL, so a draft or a
 * not-yet-scheduled record can be viewed exactly as it will appear live,
 * without making it reachable by anyone who doesn't already have this
 * signed link. Reaching this action at all already required Filament to
 * have authorized this admin onto the resource's Edit page, so no separate
 * permission check is layered on top.
 */
trait HasPreviewAction
{
    /**
     * @param  string|Closure(object): ?string  $routeName  A fixed route name, or a
     *                                                      resolver for models whose public route name isn't fixed (e.g. Page, whose
     *                                                      route name equals its slug) — return null to hide the action for a record
     *                                                      with no live route at all.
     * @param  Closure(object): array<string, mixed>  $routeParameters
     */
    protected function previewAction(string|Closure $routeName, Closure $routeParameters): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->visible(fn ($record) => (bool) $this->resolvePreviewRouteName($routeName, $record))
            ->url(fn ($record) => URL::temporarySignedRoute(
                $this->resolvePreviewRouteName($routeName, $record),
                now()->addMinutes(30),
                $routeParameters($record),
            ))
            ->openUrlInNewTab();
    }

    private function resolvePreviewRouteName(string|Closure $routeName, object $record): ?string
    {
        return is_string($routeName) ? $routeName : $routeName($record);
    }
}
