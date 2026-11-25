<?php

namespace App\Modules\FynnOn\Services;

use App\Modules\FynnOn\DataTransferObjects\FynnOnDelivery;
use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one HTTP call to FynnOn LMS: POST {FYNNON_LMS_URL} with the lead as JSON.
 *
 * Headers: `Authorization: Bearer {token}`, `Idempotency-Key: {reference}`
 * (the same key every time the same lead is sent), and — when a signing secret
 * is configured — `X-FynnEdge-Timestamp` + `X-FynnEdge-Signature:
 * sha256=HMAC(timestamp + "." + body)` so the LMS can reject forged posts.
 *
 * A lead counts as delivered only on a 2xx reply whose JSON body acknowledges
 * it (a `reference`, `id`, `lead_id` or `success: true`); a 409 means the LMS
 * already holds that reference (also delivered). A 2xx without an
 * acknowledgement is treated as a failure, so a misconfigured URL that answers
 * 200 with an HTML page is never mistaken for success.
 */
final class FynnOnClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload, string $idempotencyKey): FynnOnDelivery
    {
        if (! FynnOnSettings::isConfigured()) {
            return new FynnOnDelivery(FynnOnSyncStatus::Failed, error: 'FynnOn LMS is not configured (FYNNON_LMS_URL / FYNNON_LMS_TOKEN).');
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $headers = [
            'Idempotency-Key' => $idempotencyKey,
            'X-FynnEdge-Event' => (string) ($payload['event'] ?? 'lead.created'),
            'X-FynnEdge-Timestamp' => $timestamp,
        ];

        if ($secret = FynnOnSettings::signingSecret()) {
            $headers['X-FynnEdge-Signature'] = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        }

        $started = microtime(true);

        try {
            $response = Http::withToken((string) FynnOnSettings::token())
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout(FynnOnSettings::timeout())
                ->connectTimeout(min(10, FynnOnSettings::timeout()))
                ->withBody($body, 'application/json')
                ->post((string) FynnOnSettings::url());
        } catch (ConnectionException $exception) {
            return new FynnOnDelivery(FynnOnSyncStatus::Failed, error: 'Could not reach FynnOn LMS (timeout or connection error).', retryable: true, durationMs: $this->elapsed($started));
        } catch (Throwable $exception) {
            return new FynnOnDelivery(FynnOnSyncStatus::Failed, error: 'Unexpected error sending to FynnOn LMS: '.class_basename($exception), retryable: true, durationMs: $this->elapsed($started));
        }

        return $this->interpret($response, $this->elapsed($started));
    }

    private function interpret(Response $response, int $durationMs): FynnOnDelivery
    {
        $status = $response->status();
        $json = $response->json();
        $reference = is_array($json) ? $this->lmsReference($json) : null;

        if ($status === 409) {
            return new FynnOnDelivery(FynnOnSyncStatus::Duplicate, $status, $reference, durationMs: $durationMs);
        }

        if ($response->successful()) {
            $acknowledged = is_array($json) && ($reference !== null || ($json['success'] ?? null) === true);

            if ($acknowledged) {
                $duplicate = is_array($json) && (($json['duplicate'] ?? false) === true || ($json['status'] ?? null) === 'duplicate');

                return new FynnOnDelivery($duplicate ? FynnOnSyncStatus::Duplicate : FynnOnSyncStatus::Sent, $status, $reference, durationMs: $durationMs);
            }

            return new FynnOnDelivery(FynnOnSyncStatus::Failed, $status, error: 'FynnOn LMS answered '.$status.' without acknowledging the lead (no reference or success flag in the reply).', durationMs: $durationMs);
        }

        $retryable = $status === 408 || $status === 429 || $status >= 500;
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        return new FynnOnDelivery(
            FynnOnSyncStatus::Failed,
            $status,
            error: 'FynnOn LMS answered '.$status.(is_string($message) && $message !== '' ? ': '.Str::limit(strip_tags($message), 160) : '.'),
            retryable: $retryable,
            durationMs: $durationMs,
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function lmsReference(array $json): ?string
    {
        foreach (['reference', 'lead_id', 'id'] as $key) {
            $value = $json[$key] ?? ($json['data'][$key] ?? null);

            if (is_scalar($value) && (string) $value !== '') {
                return Str::limit((string) $value, 120, '');
            }
        }

        return null;
    }

    private function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
