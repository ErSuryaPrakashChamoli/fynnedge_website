<?php

namespace App\Support\Admin;

use Filament\Pages\Page;
use Filament\Resources\Resource;
use Throwable;

/**
 * Links between admin screens that respect the signed-in user's permissions:
 * a help panel never offers a link the user would only be refused at.
 */
final class AdminLinks
{
    /**
     * The screen's URL (a resource's list, or its create form), or null when
     * the current user cannot open it or the class is not a screen.
     */
    public static function to(string $screen, bool $create = false): ?string
    {
        try {
            if (is_subclass_of($screen, Resource::class)) {
                if ($create) {
                    return $screen::hasPage('create') && $screen::canCreate() ? $screen::getUrl('create') : null;
                }

                return $screen::canAccess() && $screen::hasPage('index') ? $screen::getUrl('index') : null;
            }

            if (is_subclass_of($screen, Page::class)) {
                return $screen::canAccess() ? $screen::getUrl() : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public static function label(string $screen): string
    {
        if (is_subclass_of($screen, Resource::class) || is_subclass_of($screen, Page::class)) {
            return $screen::getNavigationLabel();
        }

        return class_basename($screen);
    }
}
