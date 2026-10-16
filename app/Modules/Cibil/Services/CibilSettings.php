<?php

namespace App\Modules\Cibil\Services;

use App\Models\Setting;
use App\Modules\Cibil\Providers\ExperianCibilScoreProvider;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\Experian\Services\ExperianSettings;

/**
 * The public "Free CIBIL Score" page. FynnEdge has no CIBIL API yet, so while
 * "CIBIL page shows real scores" is on (Admin → CIBIL → CIBIL Settings) the page
 * is answered by a stand-in SOURCE — today Experian, through Surepass — and
 * says so to the visitor. Off (the default), it shows a labelled demo score.
 *
 * Switching to a real CIBIL API later is one provider class plus one setting:
 * add it to SOURCES (e.g. 'cibil' => TransUnionCibilScoreProvider::class),
 * then set CIBIL_PUBLIC_SCORE_SOURCE=cibil. Nothing in the page, the Livewire
 * component or SubmitCreditScoreCheck changes.
 */
final class CibilSettings
{
    public const PUBLIC_SCORE_ENABLED = 'cibil_public_score_enabled';

    /**
     * Where real scores for the CIBIL page come from, by config value.
     *
     * @var array<string, array{label: string, provider: class-string<CreditScoreProvider>}>
     */
    public const SOURCES = [
        'experian' => ['label' => 'Experian', 'provider' => ExperianCibilScoreProvider::class],
    ];

    /** Off by default: every new check is a paid call triggered by a website visitor. */
    public static function publicScoreEnabled(): bool
    {
        return (bool) Setting::get(self::PUBLIC_SCORE_ENABLED, false);
    }

    /** The configured source key ("experian"), or null when it names no known source. */
    public static function source(): ?string
    {
        $source = config('services.cibil.public_score_source');

        return is_string($source) && array_key_exists($source, self::SOURCES) ? $source : null;
    }

    /** The bureau whose score the page shows — "Experian" today. */
    public static function sourceLabel(): ?string
    {
        $source = self::source();

        return $source === null ? null : self::SOURCES[$source]['label'];
    }

    /** Whether the source can be called from this server (its token is set). */
    public static function isConfigured(): bool
    {
        return match (self::source()) {
            'experian' => ExperianSettings::isConfigured(),
            default => false,
        };
    }

    public static function publicScoreLive(): bool
    {
        return self::publicScoreEnabled() && self::isConfigured();
    }

    /** The provider answering the page right now, or null when it shows demo scores. */
    public static function liveProvider(): ?CreditScoreProvider
    {
        $source = self::source();

        return self::publicScoreLive() && $source !== null ? app(self::SOURCES[$source]['provider']) : null;
    }

    /**
     * How long a visitor's stored score is shown again instead of paying for a
     * new one — the source bureau's own score reuse period.
     */
    public static function scoreReuseDays(): int
    {
        return match (self::source()) {
            'experian' => ExperianSettings::scoreReuseDays(),
            default => ExperianSettings::DEFAULT_SCORE_REUSE_DAYS,
        };
    }
}
