<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Providers\SurepassCrifScoreProvider;
use App\Modules\CreditScore\Providers\SurepassEquifaxScoreProvider;
use App\Modules\Equifax\Services\EquifaxSettings;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SubmitCreditScoreCheck
{
    /** A live check whose outcome was uncertain is not re-sent for this long. */
    private const UNCERTAIN_HOLD_MINUTES = 60;

    public function __construct(
        private readonly CreditScoreProvider $provider,
        private readonly SurepassCrifScoreProvider $liveCrif,
        private readonly SurepassEquifaxScoreProvider $liveEquifax,
    ) {}

    /**
     * Real scores for the bureaus whose public page is switched to live (CRIF:
     * CRIF Settings, Equifax: Equifax Settings — each independently); the bound
     * demo provider for everything else.
     *
     * @param  array{first_name: string, middle_name: string, last_name: string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    public function handle(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress = null): CreditScoreCheck
    {
        return match (true) {
            $bureau === BureauName::Crif && CrifSettings::publicScoreLive() => $this->live($this->liveCrif, $bureau, CrifSettings::reuseDays(), $data, $mobileNumber, $ipAddress),
            $bureau === BureauName::Equifax && EquifaxSettings::publicScoreLive() => $this->live($this->liveEquifax, $bureau, EquifaxSettings::scoreReuseDays(), $data, $mobileNumber, $ipAddress),
            default => $this->run($this->provider, $data, $bureau, $mobileNumber, $ipAddress),
        };
    }

    /**
     * Every new live check is a paid Surepass call, so the same person — same
     * bureau, PAN AND verified mobile, so a typed-in PAN never reveals someone
     * else's stored score — checking again inside the reuse period is shown the
     * score already fetched, and a check whose outcome was uncertain is not
     * re-sent for an hour. A per-person lock stops two tabs paying twice.
     *
     * @param  array{first_name: string, middle_name: string, last_name: string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    private function live(CreditScoreProvider $provider, BureauName $bureau, int $reuseDays, array $data, string $mobileNumber, ?string $ipAddress): CreditScoreCheck
    {
        $pan = PanNumber::normalize($data['pan_number']);
        $data = [...$data, 'pan_number' => $pan];
        $previous = fn () => CreditScoreCheck::query()
            ->where('bureau', $bureau)
            ->where('provider', $provider->name())
            ->where('pan_number', $pan)
            ->where('mobile_number', $mobileNumber);

        try {
            return Cache::lock($bureau->value.'-public-score:'.PanNumber::hash($pan.'|'.$mobileNumber), 90)->block(10, function () use ($provider, $bureau, $reuseDays, $data, $mobileNumber, $ipAddress, $previous): CreditScoreCheck {
                $reusable = $previous()
                    ->where('status', CreditCheckStatus::Completed)
                    ->where('completed_at', '>=', now()->subDays($reuseDays))
                    ->whereNull('raw_response->reused_from')
                    ->latest('completed_at')
                    ->first();

                if ($reusable) {
                    return $this->record($data, $bureau, $mobileNumber, $ipAddress, [
                        'provider' => $provider->name(),
                        'status' => CreditCheckStatus::Completed,
                        'score' => $reusable->score,
                        'raw_response' => ['reused_from' => $reusable->public_id],
                        'completed_at' => now(),
                    ]);
                }

                $unresolved = $previous()
                    ->where('status', CreditCheckStatus::Pending)
                    ->where('created_at', '>=', now()->subMinutes(self::UNCERTAIN_HOLD_MINUTES))
                    ->latest('id')
                    ->first();

                return $unresolved ?? $this->run($provider, $data, $bureau, $mobileNumber, $ipAddress);
            });
        } catch (LockTimeoutException) {
            // The same person's previous check is still waiting for Surepass.
            return new CreditScoreCheck(['bureau' => $bureau, 'provider' => $provider->name(), 'status' => CreditCheckStatus::Pending]);
        }
    }

    /**
     * @param  array{first_name: string, middle_name: string, last_name: string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     */
    private function run(CreditScoreProvider $provider, array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress): CreditScoreCheck
    {
        $check = $this->record($data, $bureau, $mobileNumber, $ipAddress, [
            'provider' => $provider->name(),
            'status' => CreditCheckStatus::Pending,
        ]);

        $result = $provider->check($check);

        $check->forceFill([
            'status' => $result->status,
            'score' => $result->score,
            'raw_response' => $result->rawResponse,
            'completed_at' => now(),
        ])->save();

        return $check;
    }

    /**
     * @param  array{first_name: string, middle_name: string, last_name: string, date_of_birth: string, pan_number: string, gender?: ?string}  $data
     * @param  array<string, mixed>  $outcome
     */
    private function record(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress, array $outcome): CreditScoreCheck
    {
        return CreditScoreCheck::query()->create([
            'bureau' => $bureau,
            'mobile_number' => $mobileNumber,
            'mobile_verified_at' => now(),
            'full_name' => Str::squish("{$data['first_name']} {$data['middle_name']} {$data['last_name']}"),
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?: null,
            'last_name' => $data['last_name'],
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
