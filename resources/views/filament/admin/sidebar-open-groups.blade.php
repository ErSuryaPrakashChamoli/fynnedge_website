{{--
    The sidebar headings are always open (AdminPanelProvider: ->collapsible(false)),
    but Filament still hides any group whose name is saved in the browser's
    "collapsedGroups" list, e.g. one collapsed before the headings were fixed open.
    With no arrow left to reopen it, such a group showed its heading and nothing
    else. This runs right after Filament's own sidebar script (SIDEBAR_NAV_END):
    it drops always-open groups from that list and shows them again. Groups that
    are collapsible keep whatever the user chose.
--}}
<script>
    (() => {
        const groups = [...document.querySelectorAll('.fi-sidebar-group:not(.fi-collapsible)[data-group-label]')]

        if (! groups.length) {
            return
        }

        const labels = groups.map((group) => group.dataset.groupLabel)
        const withoutFixedOpen = (saved) => saved.filter((label) => ! labels.includes(label))

        try {
            const saved = JSON.parse(localStorage.getItem('collapsedGroups'))

            if (Array.isArray(saved) && saved.some((label) => labels.includes(label))) {
                localStorage.setItem('collapsedGroups', JSON.stringify(withoutFixedOpen(saved)))
            }
        } catch (error) {
            // Storage blocked or unreadable: nothing was saved, so nothing is hidden.
        }

        const store = window.Alpine?.store('sidebar')

        if (store) {
            // Alpine already running (sidebar re-rendered): let it re-show the groups.
            if (Array.isArray(store.collapsedGroups)) {
                store.collapsedGroups = withoutFixedOpen(store.collapsedGroups)
            }

            return
        }

        // First load: undo Filament's early hide before Alpine boots.
        groups.forEach((group) => {
            group.classList.remove('fi-collapsed')

            const items = group.querySelector('.fi-sidebar-group-items')

            if (items && items.style.display === 'none') {
                items.style.display = ''
            }
        })
    })()
</script>
