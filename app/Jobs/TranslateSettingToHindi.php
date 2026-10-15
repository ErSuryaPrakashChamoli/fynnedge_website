<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Support\Localization\AutoTranslation;
use App\Support\Localization\MachineTranslator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes Google's Hindi for the given pieces of one Settings value into its
 * `{key}_hi` sibling. Queued by AutoTranslation once a request has saved it.
 */
class TranslateSettingToHindi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $paths  dot paths of the texts to translate ('' = the whole setting is one text)
     */
    public function __construct(
        public string $key,
        public array $paths,
        public string $englishFingerprint,
        public string $hindiFingerprint,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MachineTranslator $translator): void
    {
        $hindiKey = $this->key.'_'.AutoTranslation::LOCALE;
        $english = Setting::get($this->key);
        $hindi = Setting::get($hindiKey);

        // Saved again since: that save queued its own translation.
        if (AutoTranslation::fingerprint(json_encode($english)) !== $this->englishFingerprint
            || AutoTranslation::fingerprint(json_encode($hindi)) !== $this->hindiFingerprint) {
            return;
        }

        $texts = array_map(fn (string $path): string => (string) AutoTranslation::at($english, $path), $this->paths);
        $translated = $translator->translateStrings($texts);

        if (in_array('', $this->paths, true)) {
            $hindi = $translated[array_search('', $this->paths, true)];
        } else {
            $hindi = is_array($hindi) ? $hindi : [];

            foreach ($this->paths as $index => $path) {
                data_set($hindi, $path, $translated[$index]);
            }

            $hindi = self::trimLists($english, $hindi);
        }

        AutoTranslation::paused(fn () => Setting::set($hindiKey, $hindi));
    }

    /**
     * A Hindi list never keeps more items than its English one: an item the
     * editor removed in English must not live on in Hindi.
     */
    private static function trimLists(mixed $english, mixed $hindi): mixed
    {
        if (! is_array($english) || ! is_array($hindi)) {
            return $hindi;
        }

        if (array_is_list($english) && array_is_list($hindi)) {
            $hindi = array_slice($hindi, 0, count($english));
        }

        foreach ($hindi as $key => $value) {
            if (array_key_exists($key, $english)) {
                $hindi[$key] = self::trimLists($english[$key], $value);
            }
        }

        return $hindi;
    }
}
