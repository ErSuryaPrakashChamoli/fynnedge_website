---
paths:
  - 'app/Providers/Filament/AdminPanelProvider.php,resources/views/filament/admin/sidebar-open-groups.blade.php'
---

# Views Filament Admin

## Fixed-open sidebar headings can stay hidden from old localStorage
Filament hides any sidebar group whose label is in localStorage "collapsedGroups", even when the group is ->collapsible(false). A heading collapsed before the groups were fixed open (2026-11-24: "Operations") showed its label with no items and no arrow to reopen it. The SIDEBAR_NAV_END hook view filament.admin.sidebar-open-groups runs after Filament's inline sidebar script: it removes non-collapsible labels from that list (and from the Alpine sidebar store) and un-hides them. Keep it while any group is non-collapsible, and keep it rendering after Filament's script (pinned by AdminNavigationTest). Renaming a heading is safe; making a group collapsible again leaves user choices alone.
