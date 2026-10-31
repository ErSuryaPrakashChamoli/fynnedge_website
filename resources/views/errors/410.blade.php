{{--
    An old URL with a Gone rule (Admin → Redirects, type 410): its content was
    removed for good and nothing equivalent exists. Same page as the 404, with
    wording that says so.
--}}
<x-layouts.app :title="__('This page has been removed')" :description="__('The page you asked for has been removed from FynnEdge.')" robots="noindex, follow" handles-faqs handles-social-videos handles-video-testimonials>
    <x-site.missing-page
        code="410"
        :heading="__('This page has been removed')"
        :message="__('The page you asked for was removed and is not coming back. One of these may help instead.')"
    />
</x-layouts.app>
