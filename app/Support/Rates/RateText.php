<?php

namespace App\Support\Rates;

/**
 * Rate figures inside admin-written copy, kept in step with RateBook.
 *
 * Editors write a token instead of a number:
 *
 *   [rate:personal-loan]        → "from 9.99% p.a."
 *   [rate-range:personal-loan]  → "at lenders' published rates of 9.99%–30.50% p.a."
 *
 * The key is the loan product's URL slug or its loan type (e.g.
 * personal-loan). When the product has no live,
 * verified rate, either token reads "at rates set by each lender" instead, so
 * a sentence never shows a stale or unverified figure and never breaks.
 *
 * literalClaims() finds the opposite — a percentage typed into copy as a rate
 * ("starting at 9.99% p.a.") — for the admin forms to reject and the rate
 * dashboard to list.
 */
final class RateText
{
    public const FALLBACK = 'at rates set by each lender';

    /**
     * A token, plus a full stop straight after it — the rendered phrase already
     * ends in "p.a." or "per month.", and "p.a.." is not a sentence.
     */
    private const TOKEN = '/\[(rate|rate-range):([a-z0-9-]+)\](\.(?!\d))?/';

    /**
     * A percentage presented as an interest rate: "10.49% p.a.", "9.99%
     * onwards", "interest from 8.5%", "starting at 9.99%", "loans from 9.99%",
     * "@ 10.5%". Deliberately not a bare "from 1%", which is usually a fee,
     * and never part of a longer number such as "100%".
     */
    private const LITERAL_CLAIM = '/(?<![\d.])\d{1,2}(?:\.\d{1,2})?\s?%\*?\s*(?:p\.?\s?a\b|p\.?\s?m\b|per\s+(?:annum|month|year)|onwards|interest|rate\b|roi\b|apr\b)'
        .'|(?:interest(?:\s+rates?)?|rates?|roi|apr|starting\s+(?:at|from)|loans?\s+from|@)\s*(?:of|at|from|is|:)?\s*\*?\s*(?<![\d.])\d{1,2}(?:\.\d{1,2})?\s?%/i';

    /**
     * Comparison and aggregator sites — never a source, and never something
     * the public copy should cite.
     */
    private const AGGREGATORS = '/paisa\s?bazaar|bank\s?bazaar|policy\s?bazaar|myloancare|wishfin|creditmantri|credit\s?mantri/i';

    /**
     * Admin text with every segment that states a rate or cites an aggregator
     * removed. Used where free text written for one purpose (a fee note) is
     * shown publicly, so it cannot smuggle in an unverified rate.
     */
    public static function withoutRateClaims(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $kept = collect(preg_split('/\s*;\s*/', (string) $text))
            ->reject(fn (string $segment) => self::literalClaims($segment) !== [] || preg_match(self::AGGREGATORS, $segment) === 1)
            ->filter(fn (string $segment) => filled($segment))
            ->implode('; ');

        return $kept === '' ? null : $kept;
    }

    public static function render(?string $text): string
    {
        if ($text === null || ! str_contains($text, '[rate')) {
            return (string) $text;
        }

        return (string) preg_replace_callback(self::TOKEN, function (array $match): string {
            $headline = RateBook::headlineForSlug($match[2]);

            $phrase = match (true) {
                $headline === null => self::FALLBACK,
                $match[1] === 'rate' => $headline->fromLabel(),
                default => "at lenders' published rates of {$headline->rangeLabel()}",
            };

            $fullStop = $match[3] ?? '';

            return str_ends_with($phrase, '.') ? $phrase : $phrase.$fullStop;
        }, $text);
    }

    /**
     * @return list<string> Each rate-like phrase typed as a literal number.
     */
    public static function literalClaims(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $plain = html_entity_decode(strip_tags((string) preg_replace(self::TOKEN, '', $text)), ENT_QUOTES | ENT_HTML5);

        preg_match_all(self::LITERAL_CLAIM, $plain, $matches);

        return array_values(array_unique(array_map('trim', $matches[0])));
    }

    /**
     * A Filament field rule: refuses copy that types a rate as a number.
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $values = is_array($value) ? $value : [$value];

            foreach ($values as $item) {
                if (is_string($item) && ($claims = self::literalClaims($item)) !== []) {
                    $fail('Don\'t type a rate as a number ("'.$claims[0].'"). Use [rate:loan-slug] (e.g. [rate:personal-loan]) so the figure always matches the published, verified rate.');

                    return;
                }
            }
        };
    }
}
