<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Providers\SurepassCrifScoreProvider;
use App\Modules\CreditScore\Providers\SurepassEquifaxScoreProvider;
use App\Modules\CreditScore\Providers\SurepassExperianScoreProvider;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Services\EquifaxSettings;
use App\Modules\Experian\Services\ExperianSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SubmitCreditScoreCheck
{
    /** A live check whose outcome was uncertain is not re-sent for this long. */
    private const UNCERTAIN_HOLD_MINUTES = 60;

    private ?CarbonInterface $reuseSince = null;

    public function __construct(
        private readonly CreditScoreProvider $provider,
        private readonly SurepassCrifScoreProvider $liveCrif,
        private readonly SurepassEquifaxScoreProvider $liveEquifax,
        private readonly SurepassExperianScoreProvider $liveExperian,
    ) {}

    /**
     * Real scores for the bureaus whose public page is switched to live (CRIF:
     * CRIF Settings, Equifax: Equifax Settings, Experian: Experian Settings —
     * each independently; CIBIL: CIBIL Settings → "Real CIBIL score enabled",
     * answered by CIBIL, or by Experian while "Fetch Experian instead of
     * CIBIL" is on — see CibilSettings); the bound demo provider for
     * everything else, which calls no bureau.
     *
     * The name arrives the way the bureau's API takes it: CRIF's page asks for
     * first, middle and last name apart (full_name is the three joined), the
     * other pages for one full name (first/middle/last null).
     *
     * $customer is the portal account the OTP-verified mobile resolved to
     * (ResolveCustomerAccount); the check is recorded against it so the
     * customer finds it in their profile later.
     *
     * $reuseSince widens the reuse period for this one check: a stored score
     * completed since then is shown again instead of a new pull — how the
     * regeneration limit (CreditReportRegenerationLimit) shows a saved score
     * older than the bureau's own reuse period.
     *
     * @param  array{full_name: string, first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    public function handle(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress = null, ?Customer $customer = null, ?CarbonInterface $reuseSince = null): CreditScoreCheck
    {
        $data = [...$data, 'customer_id' => $customer?->id];
        $this->reuseSince = $reuseSince;
        $cibil = $bureau === BureauName::Cibil ? CibilSettings::liveProvider() : null;

        return match (true) {
            $cibil !== null => $this->live($cibil, $bureau, CibilSettings::publicScoreReuseDays(), $data, $mobileNumber, $ipAddress),
            $bureau === BureauName::Crif && CrifSettings::publicScoreLive() => $this->live($this->liveCrif, $bureau, CrifSettings::reuseDays(), $data, $mobileNumber, $ipAddress),
            $bureau === BureauName::Equifax && EquifaxSettings::publicScoreLive() => $this->live($this->liveEquifax, $bureau, EquifaxSettings::scoreReuseDays(), $data, $mobileNumber, $ipAddress),
            $bureau === BureauName::Experian && ExperianSettings::publicScoreLive() => $this->live($this->liveExperian, $bureau, ExperianSettings::scoreReuseDays(), $data, $mobileNumber, $ipAddress),
            default => $this->run($this->provider, $data, $bureau, $mobileNumber, $ipAddress),
        };
    }

    /**
     * Whether a new check for this bureau would be sent to the real bureau
     * (its public page is switched to live and configured) rather than
     * answered with a demo score.
     */
    public static function isLive(BureauName $bureau): bool
    {
        return match ($bureau) {
            BureauName::Cibil => CibilSettings::publicScoreLive(),
            BureauName::Crif => CrifSettings::publicScoreLive(),
            BureauName::Equifax => EquifaxSettings::publicScoreLive(),
            BureauName::Experian => ExperianSettings::publicScoreLive(),
        };
    }

    /**
     * The bureau that answers a page's real checks: the page's own, except the
     * CIBIL page while "Fetch Experian instead of CIBIL" is on. Stored checks
     * are only reused, held or treated as a saved score within this bureau.
     */
    public static function sourceBureau(BureauName $bureau): BureauName
    {
        return $bureau === BureauName::Cibil ? CibilSettings::sourceBureau() : $bureau;
    }

    /**
     * The existing reuse period for a bureau's public score: a stored score
     * younger than this is shown again instead of paying for a new one. The
     * customer portal's monthly refresh policy is this same period.
     */
    public static function reuseDays(BureauName $bureau): int
    {
        return match ($bureau) {
            BureauName::Cibil => CibilSettings::publicScoreReuseDays(),
            BureauName::Crif => CrifSettings::reuseDays(),
            BureauName::Equifax => EquifaxSettings::scoreReuseDays(),
            BureauName::Experian => ExperianSettings::scoreReuseDays(),
        };
    }

    /**
     * Whether a live check by this person (bureau, PAN and verified mobile)
     * would be answered without a new paid pull — a stored score inside the
     * reuse period, or an uncertain check still inside its hold. The same
     * rule live() applies, for callers that must know before running it
     * (customer billing).
     */
    public static function wouldAnswerFromStore(BureauName $bureau, string $pan, string $mobileNumber): bool
    {
        $previous = fn () => self::previousChecks($bureau, CreditScoreCheck::LIVE_PROVIDER, PanNumber::normalize($pan), $mobileNumber);

        return self::reusable($previous(), now()->subDays(self::reuseDays($bureau)))->exists()
            || self::unresolved($previous())->exists();
    }

    /**
     * @return Builder<CreditScoreCheck>
     */
    private static function previousChecks(BureauName $bureau, string $provider, string $pan, string $mobileNumber): Builder
    {
        return CreditScoreCheck::query()
            ->answeredBy($bureau, self::sourceBureau($bureau))
            ->where('provider', $provider)
            ->where('pan_number', $pan)
            ->where('mobile_number', $mobileNumber);
    }

    /**
     * @param  Builder<CreditScoreCheck>  $query
     * @return Builder<CreditScoreCheck>
     */
    private static function reusable(Builder $query, CarbonInterface $since): Builder
    {
        return $query->where('status', CreditCheckStatus::Completed)
            ->where('completed_at', '>=', $since)
            ->whereNull('raw_response->reused_from');
    }

    /**
     * @param  Builder<CreditScoreCheck>  $query
     * @return Builder<CreditScoreCheck>
     */
    private static function unresolved(Builder $query): Builder
    {
        return $query->where('status', CreditCheckStatus::Pending)
            ->where('created_at', '>=', now()->subMinutes(self::UNCERTAIN_HOLD_MINUTES));
    }

    /** How long an uncertain live check blocks a new paid one for the same person. */
    public static function uncertainHoldMinutes(): int
    {
        return self::UNCERTAIN_HOLD_MINUTES;
    }

    /**
     * Every new live check is a paid Surepass call, so the same person — same
     * bureau, PAN AND verified mobile, so a typed-in PAN never reveals someone
     * else's stored score — checking again inside the reuse period is shown the
     * score already fetched FROM THE SAME SOURCE BUREAU (never a CIBIL score
     * as an Experian one, or the other way round), and a check whose outcome
     * was uncertain is not re-sent for an hour. A per-person lock stops two
     * tabs paying twice.
     *
     * @param  array{full_name: string, first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    private function live(CreditScoreProvider $provider, BureauName $bureau, int $reuseDays, array $data, string $mobileNumber, ?string $ipAddress): CreditScoreCheck
    {
        $pan = PanNumber::normalize($data['pan_number']);
        $data = [...$data, 'pan_number' => $pan];
        $previous = fn () => self::previousChecks($bureau, $provider->name(), $pan, $mobileNumber);

        try {
            return Cache::lock($bureau->value.'-public-score:'.PanNumber::hash($pan.'|'.$mobileNumber), 90)->block(10, function () use ($provider, $bureau, $reuseDays, $data, $mobileNumber, $ipAddress, $previous): CreditScoreCheck {
                $since = now()->subDays($reuseDays);
                $since = $this->reuseSince !== null && $this->reuseSince->lessThan($since) ? $this->reuseSince : $since;

                $reusable = self::reusable($previous(), $since)
                    ->latest('completed_at')
                    ->first();

                if ($reusable) {
                    return $this->record($data, $bureau, $mobileNumber, $ipAddress, [
                        'provider' => $provider->name(),
                        'status' => CreditCheckStatus::Completed,
                        'score' => $reusable->score,
                        'raw_response' => array_filter([
                            'reused_from' => $reusable->public_id,
                            'source_bureau' => $reusable->raw_response['source_bureau'] ?? null,
                        ]),
                        'report_insights' => $reusable->report_insights,
                        'completed_at' => now(),
                    ]);
                }

                $unresolved = self::unresolved($previous())
                    ->latest('id')
                    ->first();

                return $unresolved ?? $this->run($provider, $data, $bureau, $mobileNumber, $ipAddress, self::sourceBureau($bureau));
            });
        } catch (LockTimeoutException) {
            // The same person's previous check is still waiting for Surepass.
            return new CreditScoreCheck(['bureau' => $bureau, 'provider' => $provider->name(), 'status' => CreditCheckStatus::Pending]);
        }
    }

    /**
     * @param  array{full_name: string, first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    private function run(CreditScoreProvider $provider, array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress, ?BureauName $source = null): CreditScoreCheck
    {
        $check = $this->record($data, $bureau, $mobileNumber, $ipAddress, [
            'provider' => $provider->name(),
            'status' => CreditCheckStatus::Pending,
            // Tagged before the call, so an uncertain check holds only its own bureau.
            'raw_response' => $source !== null && $source !== $bureau ? ['source_bureau' => $source->value] : null,
        ]);

        $result = $provider->check($check);

        $check->forceFill([
            'status' => $result->status,
            'score' => $result->score,
            'raw_response' => $result->rawResponse,
            'report_insights' => $this->reportInsights($result->insights),
            'completed_at' => now(),
        ])->save();

        return $check;
    }

    /**
     * Null when the answer held no report to read (the score-only endpoint,
     * a demo score) rather than a row of nulls.
     *
     * @param  array<string, int|null>|null  $insights
     * @return array<string, int|null>|null
     */
    private function reportInsights(?array $insights): ?array
    {
        return $insights !== null && array_filter($insights, fn (?int $value) => $value !== null) !== [] ? $insights : null;
    }

    /**
     * @param  array{full_name: string, first_name: ?string, middle_name: ?string, last_name: ?string, date_of_birth: string, pan_number: string, gender?: ?string, customer_id?: ?int}  $data
     * @param  array<string, mixed>  $outcome
     */
    private function record(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress, array $outcome): CreditScoreCheck
    {
        return CreditScoreCheck::query()->create([
            'customer_id' => $data['customer_id'] ?? null,
            'bureau' => $bureau,
            'mobile_number' => $mobileNumber,
            'mobile_verified_at' => now(),
            'full_name' => Str::squish($data['full_name']),
            'first_name' => $data['first_name'] ?: null,
            'middle_name' => $data['middle_name'] ?: null,
            'last_name' => $data['last_name'] ?: null,
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'] ?? null,
            'pan_number' => $data['pan_number'],
            'consent_given_at' => now(),
            'ip_address' => $ipAddress,
            'requested_at' => now(),
            ...$outcome,
        ]);
    }
}
