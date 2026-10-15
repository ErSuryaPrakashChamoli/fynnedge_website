<?php

namespace App\Modules\CreditScore\Actions;

use App\Modules\CreditBureau\Enums\CreditCheckStatus;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Contracts\CreditScoreProvider;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\CreditScore\Providers\SurepassCrifScoreProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class SubmitCreditScoreCheck
{
    /** A live check whose outcome was uncertain is not re-sent for this long. */
    private const UNCERTAIN_HOLD_MINUTES = 60;

    public function __construct(
        private readonly CreditScoreProvider $provider,
        private readonly SurepassCrifScoreProvider $liveCrif,
    ) {}

    /**
     * @param  array{full_name: string, date_of_birth: string, pan_number: string}  $data
     */
    public function handle(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress = null): CreditScoreCheck
    {
        if ($bureau === BureauName::Crif && CrifSettings::publicScoreLive()) {
            return $this->liveCrif($data, $mobileNumber, $ipAddress);
        }

        return $this->run($this->provider, $data, $bureau, $mobileNumber, $ipAddress);
    }

    /**
     * Every new live CRIF check is a paid Surepass call, so the same person —
     * same PAN AND same verified mobile, so a typed-in PAN never reveals someone
     * else's stored score — checking again inside the reuse period is shown the
     * score already fetched, and a check whose outcome was uncertain is not
     * re-sent for an hour. A per-person lock stops two tabs paying twice.
     *
     * @param  array{full_name: string, date_of_birth: string, pan_number: string}  $data
     */
    private function liveCrif(array $data, string $mobileNumber, ?string $ipAddress): CreditScoreCheck
    {
        $pan = PanNumber::normalize($data['pan_number']);
        $previous = fn () => CreditScoreCheck::query()
            ->where('bureau', BureauName::Crif)
            ->where('provider', $this->liveCrif->name())
            ->where('pan_number', $pan)
            ->where('mobile_number', $mobileNumber);

        try {
            return Cache::lock('crif-public-score:'.PanNumber::hash($pan.'|'.$mobileNumber), 90)->block(10, function () use ($data, $pan, $mobileNumber, $ipAddress, $previous): CreditScoreCheck {
                $reusable = $previous()
                    ->where('status', CreditCheckStatus::Completed)
                    ->where('completed_at', '>=', now()->subDays(CrifSettings::reuseDays()))
                    ->whereNull('raw_response->reused_from')
                    ->latest('completed_at')
                    ->first();

                if ($reusable) {
                    return $this->record([...$data, 'pan_number' => $pan], BureauName::Crif, $mobileNumber, $ipAddress, [
                        'provider' => $this->liveCrif->name(),
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

                return $unresolved ?? $this->run($this->liveCrif, [...$data, 'pan_number' => $pan], BureauName::Crif, $mobileNumber, $ipAddress);
            });
        } catch (LockTimeoutException) {
            // The same person's previous check is still waiting for Surepass.
            return new CreditScoreCheck(['bureau' => BureauName::Crif, 'provider' => $this->liveCrif->name(), 'status' => CreditCheckStatus::Pending]);
        }
    }

    /**
     * @param  array{full_name: string, date_of_birth: string, pan_number: string}  $data
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
     * @param  array{full_name: string, date_of_birth: string, pan_number: string}  $data
     * @param  array<string, mixed>  $outcome
     */
    private function record(array $data, BureauName $bureau, string $mobileNumber, ?string $ipAddress, array $outcome): CreditScoreCheck
    {
        return CreditScoreCheck::query()->create([
            'bureau' => $bureau,
            'mobile_number' => $mobileNumber,
            'mobile_verified_at' => now(),
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'pan_number' => $data['pan_number'],
            'consent_given_at' => now(),
            'ip_address' => $ipAddress,
            'requested_at' => now(),
            ...$outcome,
        ]);
    }
}
