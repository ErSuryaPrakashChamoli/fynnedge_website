---
paths:
  - 'resources/views/robots.blade.php,app/Support/Seo/CrawlerPolicy.php,app/Http/Controllers/RobotsController.php'
---

# Seo Http Controllers

## Every robots.txt group must repeat the private paths
A crawler obeys ONLY the group naming it (RFC 9309); "*" is ignored by any bot with its own group. The Disallow list used to sit under "*" only, while Googlebot/Bingbot/AI bots had "Allow: /" groups, so none of the disallows applied to them. robots.blade.php now emits $sharedRules in every allowing group; Googlebot+Bingbot share the "*" group. Assert with robotsRulesFor($crawler, $robots) (tests/Pest.php), never assertSee('Disallow: ...'). Production (2026-10-30) still served a hand-written static robots.txt that shadows this route; it must be removed on the server (docs/seo/url-architecture.md).
