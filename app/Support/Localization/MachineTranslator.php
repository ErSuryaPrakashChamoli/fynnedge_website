<?php

namespace App\Support\Localization;

use App\Models\Lender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * English → Hindi through Google Cloud Translation (v2), for content saved in
 * the admin. Everything is sent in Google's HTML mode, so markup survives and
 * the words that must stay English are fenced off with translate="no":
 * the FynnEdge brand, every lender name, the credit bureaus and social
 * networks, [rate:…] content tokens, URLs and e-mail addresses.
 *
 * Google does not follow the site glossary, so its word choices are pulled
 * back to it afterwards (ऋण → लोन, ऋणदाता → लेंडर), and any English that
 * lang/hi.json already translates (a button label, a built-in default) uses
 * that wording instead of calling Google at all.
 */
final class MachineTranslator
{
    /** Google's recommended ceiling per request is 30k code points; stay below it. */
    private const MAX_REQUEST_CHARACTERS = 25000;

    private const MAX_SEGMENTS_PER_REQUEST = 100;

    /**
     * The site's own words for what Google renders in formal Hindi, matched as
     * whole words only (ऋणी stays ऋणी).
     *
     * @var array<string, string>
     */
    private const GLOSSARY = [
        'ऋणदाताओं' => 'लेंडरों',
        'ऋणदाता' => 'लेंडर',
        'ऋण' => 'लोन',
    ];

    private const ALWAYS_ENGLISH = [
        'FynnEdge Advisory (OPC) Private Limited',
        'FynnEdge Advisory (OPC) Pvt Ltd',
        'FynnEdge Advisory',
        'FynnEdge',
        'CRIF High Mark',
        'CIBIL',
        'Experian',
        'Equifax',
        'CRIF',
        'WhatsApp',
        'Instagram',
        'Facebook',
        'LinkedIn',
        'YouTube',
    ];

    /**
     * JSON keys whose values are machine data, never words.
     */
    private const MACHINE_KEYS = ['value', 'key', 'slug', 'icon', 'id', 'url', 'href', 'route', 'type', 'cta_url', 'link'];

    private ?string $protectedPattern = null;

    public static function isConfigured(): bool
    {
        return filled(config('services.google_translate.key'));
    }

    /**
     * Translates every human-readable string inside each value — plain text,
     * HTML, or a list/array of either (machine keys and URLs untouched) —
     * keeping the keys and shape of $values.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function translateValues(array $values): array
    {
        $leaves = [];
        $this->collectLeaves($values, [], $leaves);

        $translated = $this->translateStrings(array_column($leaves, 'text'));

        foreach ($leaves as $index => $leaf) {
            data_set($values, $leaf['path'], $translated[$index]);
        }

        return $values;
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    public function translateStrings(array $texts): array
    {
        $results = [];
        $pending = [];

        foreach (array_values($texts) as $index => $text) {
            $known = Phrasebook::known($text);

            if ($known !== null) {
                $results[$index] = $known;
            } elseif (mb_strlen($text) > self::MAX_REQUEST_CHARACTERS) {
                $results[$index] = implode('', $this->translateStrings($this->split($text)));
            } else {
                $pending[$index] = $text;
            }
        }

        foreach ($this->batches($pending) as $batch) {
            $outputs = $this->send(array_map(fn (string $text): string => $this->prepare($text), array_values($batch)));

            foreach (array_keys($batch) as $position => $index) {
                $results[$index] = $this->finish($outputs[$position], self::isHtml($batch[$index]));
            }
        }

        ksort($results);

        return array_values($results);
    }

    public static function isHtml(string $text): bool
    {
        return preg_match('/<[a-z][a-z0-9]*[\s>\/]/i', $text) === 1;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  list<string|int>  $path
     * @param  list<array{path: string, text: string}>  $leaves
     */
    private function collectLeaves(mixed $value, array $path, array &$leaves): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array($key, self::MACHINE_KEYS, true)) {
                    continue;
                }

                $this->collectLeaves($item, [...$path, $key], $leaves);
            }

            return;
        }

        if (is_string($value) && preg_match('/\p{L}{2,}/u', $value) === 1 && preg_match('#^(https?://|/|mailto:|tel:)\S*$#i', trim($value)) !== 1) {
            $leaves[] = ['path' => implode('.', $path), 'text' => $value];
        }
    }

    /**
     * Groups the texts into requests under Google's size and count limits.
     *
     * @param  array<int, string>  $texts
     * @return list<array<int, string>>
     */
    private function batches(array $texts): array
    {
        $batches = [];
        $current = [];
        $size = 0;

        foreach ($texts as $index => $text) {
            if ($current !== [] && ($size + mb_strlen($text) > self::MAX_REQUEST_CHARACTERS || count($current) >= self::MAX_SEGMENTS_PER_REQUEST)) {
                $batches[] = $current;
                $current = [];
                $size = 0;
            }

            $current[$index] = $text;
            $size += mb_strlen($text);
        }

        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * A text too long for one request (a full legal page), cut after block
     * elements or blank lines into pieces that fit; they are rejoined in order.
     *
     * @return list<string>
     */
    private function split(string $text): array
    {
        $pieces = preg_split('#(?<=</p>|</div>|</section>|</details>|</li>|</ul>|</ol>|</h1>|</h2>|</h3>|</h4>|</table>|</blockquote>|\n\n)#i', $text) ?: [$text];
        $chunks = [];
        $chunk = '';

        foreach ($pieces as $piece) {
            if ($chunk !== '' && mb_strlen($chunk) + mb_strlen($piece) > self::MAX_REQUEST_CHARACTERS) {
                $chunks[] = $chunk;
                $chunk = '';
            }

            $chunk .= $piece;
        }

        $chunks[] = $chunk;

        return $chunks;
    }

    /**
     * @param  list<string>  $html
     * @return list<string>
     */
    private function send(array $html): array
    {
        $response = Http::retry([500, 2000, 5000])
            ->timeout(config('services.google_translate.timeout', 30))
            ->asJson()
            ->post(config('services.google_translate.endpoint').'?key='.urlencode((string) config('services.google_translate.key')), [
                'q' => $html,
                'source' => 'en',
                'target' => 'hi',
                'format' => 'html',
            ]);

        $translations = $response->json('data.translations');

        if (! is_array($translations) || count($translations) !== count($html)) {
            throw new RuntimeException('Google Translate returned an unexpected response.');
        }

        return array_map(fn (array $item): string => (string) ($item['translatedText'] ?? ''), $translations);
    }

    /**
     * Plain text becomes escaped HTML (line breaks kept as <br>), and every
     * always-English term in the text — never inside a tag — is fenced off.
     */
    private function prepare(string $text): string
    {
        $html = self::isHtml($text) ? $text : nl2br(e($text), false);

        return (string) preg_replace_callback('/(<[^>]*>)|([^<]+)/u', function (array $match): string {
            if ($match[1] !== '') {
                return $match[1];
            }

            return (string) preg_replace($this->protectedPattern(), '<span translate="no">$0</span>', $match[2]);
        }, $html);
    }

    private function finish(string $output, bool $isHtml): string
    {
        $output = (string) preg_replace('#<span translate="no">(.*?)</span>#us', '$1', $output);

        foreach (self::GLOSSARY as $formal => $site) {
            $output = (string) preg_replace('/(?<![\p{L}\p{M}])'.preg_quote($formal, '/').'(?![\p{L}\p{M}])/u', $site, $output);
        }

        if ($isHtml) {
            return $output;
        }

        return html_entity_decode((string) preg_replace('#<br\s*/?>\s*#i', "\n", $output), ENT_QUOTES | ENT_HTML5);
    }

    private function protectedPattern(): string
    {
        if ($this->protectedPattern !== null) {
            return $this->protectedPattern;
        }

        $terms = collect(self::ALWAYS_ENGLISH)
            ->merge(Lender::withTrashed()->pluck('name'))
            ->filter()
            ->map(fn (string $term): string => e($term))
            ->unique()
            ->sortByDesc(fn (string $term): int => mb_strlen($term))
            ->map(fn (string $term): string => preg_quote($term, '/'));

        return $this->protectedPattern = '/\[[a-z-]+:[a-z0-9-]+\]|https?:\/\/[^\s<]+|[\w.+-]+@[\w-]+\.[\w.]+|(?<![\p{L}\p{N}])(?:'.$terms->implode('|').')(?![\p{L}\p{N}])/u';
    }
}
