{{--
    Every missing page except scanner probes and stale asset URLs, which get
    errors/bare-404 (bootstrap/app.php). The status stays 404: the page never
    redirects, so a deleted URL is reported as deleted, not as a soft 404.
    noindex because it is the same page at every missing URL.
--}}
<x-layouts.app :title="__('Page not found')" :description="__('The page you asked for is not on FynnEdge.')" robots="noindex, follow" handles-faqs handles-social-videos handles-video-testimonials>
    <x-site.missing-page
        code="404"
        :heading="__('Page not found')"
        :message="__('The page you asked for is not here. It may have moved when we rebuilt the site, or the address may be mistyped.')"
    />
</x-layouts.app>
