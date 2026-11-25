<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The admin home, without the "Dashboard" title and summary line above the
 * welcome banner (user decision, 2026-11-24): the sidebar already marks the
 * page. With no heading, breadcrumbs or header actions Filament skips the
 * page header entirely, which also drops the summary rendered inside it. The
 * browser tab and the sidebar item still read "Dashboard".
 */
class Dashboard extends BaseDashboard
{
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
