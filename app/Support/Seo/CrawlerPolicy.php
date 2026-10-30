<?php

namespace App\Support\Seo;

use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScoreIndexing;

// stage1
/**
 * The crawler policy /robots.txt is generated from.
 *
 * AI/answer-engine crawlers are controlled SEPARATELY from ordinary search
 * crawlers, because the two decisions are unrelated: a business can want to
 * rank in Google and Bing while opting out of having its content used for
 * model training or answer synthesis, and conflating them means an admin who
 * wants the latter silently loses the former. Turning AI crawlers off never
 * touches Googlebot/Bingbot; turning sitewide indexing off (see
 * SearchEngineIndexing) disallows everything for everyone, which is the only
 * case where both go.
 *
 * Google-Extended and Applebot-Extended are training-only tokens — disallowing
 * them removes the content from AI training WITHOUT affecting Google Search or
 * Siri results, which is exactly why they are listed here and Googlebot is not.
 */
class CrawlerPolicy
{
    /**
     * Answer engines and AI training crawlers, in the order robots.txt lists
     * them. Crawl-delay is only honoured by some, so it is set for the ones
     * that read it rather than emitted blindly for all.
     *
     * @var array<string, int|null> user agent => crawl delay in seconds
     */
    public const AI_CRAWLERS = [
        // Answer engines and the fetchers that act for a user who asked about a page.
        'OAI-SearchBot' => null,
        'ChatGPT-User' => null,
        'Claude-SearchBot' => null,
        'Claude-User' => null,
        'PerplexityBot' => 1,
        'Perplexity-User' => null,
        // Model training.
        'GPTBot' => 1,
        'ClaudeBot' => null,
        'Anthropic-AI' => null,
        'Google-Extended' => null,
        'Applebot-Extended' => null,
    ];

    /**
     * Ordinary search crawlers, always allowed while sitewide indexing is on —
     * blocking these is what the indexing switch is for, not this policy.
     * Applebot is Apple's search crawler (Siri, Spotlight, Safari suggestions);
     * its training opt-out is the separate Applebot-Extended token above.
     *
     * @var array<int, string>
     */
    public const SEARCH_CRAWLERS = ['Googlebot', 'Bingbot', 'Applebot'];

    /**
     * Bulk crawlers kept off the whole site whatever the AI setting says: they
     * collect pages for third-party datasets and bring no search or answer
     * traffic back. Carried over from the hand-written robots.txt production
     * served until October 2026.
     *
     * @var array<int, string>
     */
    public const BLOCKED_CRAWLERS = ['Bytespider', 'CCBot'];

    public static function aiCrawlersAllowed(): bool
    {
        return (bool) Setting::get('robots_ai_crawlers_allowed', true);
    }

    /**
     * Extra robots.txt lines an admin added, filtered to the directives
     * robots.txt actually defines. Anything else is dropped rather than
     * emitted: one malformed line can invalidate the group it sits in, and a
     * silently ignored file is indistinguishable from a working one until
     * traffic disappears.
     */
    public static function extraDirectives(): string
    {
        $value = Setting::get('robots_extra_directives');

        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $allowed = '/^(user-agent|allow|disallow|crawl-delay|sitemap|host|clean-param)\s*:/i';

        return collect(preg_split('/\R/', $value))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '' && (str_starts_with($line, '#') || preg_match($allowed, $line) === 1))
            ->implode("\n");
    }

    /**
     * @return array<int, string>
     */
    public static function disallowedPaths(): array
    {
        return [
            '/admin', '/login', '/register', '/password', '/api',
            '/journey/', '/applications/', '/credit-score/',
            // The customer portal and its sign-in page: personal, never content.
            '/my-profile', '/existing-customer',
            // The header's language switch: a redirect back to the page, not content.
            '/language/',
            // Each loan's Apply button: a GET that opens a new journey session and
            // redirects into it, so every crawl would write a row. `$` anchors it,
            // so a landing page whose slug merely starts with "apply" stays open.
            '/loans/*/apply$',
            '/storage/private', '/up',
            '/*?signature=', '/*&signature=',
        ];
    }

    /**
     * Exceptions to disallowedPaths(): the credit score pages an admin opted
     * in to search (CreditScoreIndexing). The longer, more specific Allow
     * wins over `Disallow: /credit-score/` for crawlers that follow the
     * robots.txt standard (RFC 9309).
     *
     * @return array<int, string>
     */
    public static function allowedPaths(): array
    {
        return array_map(
            fn (BureauName $bureau): string => '/credit-score/'.$bureau->value,
            CreditScoreIndexing::indexedPages(),
        );
    }
}
