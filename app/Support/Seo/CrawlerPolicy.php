<?php

namespace App\Support\Seo;

use App\Models\Setting;

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
        'OAI-SearchBot' => null,
        'GPTBot' => 1,
        'PerplexityBot' => 1,
        'Google-Extended' => null,
        'Anthropic-AI' => null,
        'ClaudeBot' => null,
        'Applebot-Extended' => null,
    ];

    /**
     * Ordinary search crawlers, always allowed while sitewide indexing is on —
     * blocking these is what the indexing switch is for, not this policy.
     *
     * @var array<int, string>
     */
    public const SEARCH_CRAWLERS = ['Googlebot', 'Bingbot'];

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
            '/storage/private', '/up',
            '/*?signature=', '/*&signature=',
        ];
    }
}
