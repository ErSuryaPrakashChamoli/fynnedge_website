<?php

namespace App\Modules\CreditBureau\Services;

use App\Modules\CreditBureau\DataTransferObjects\SurepassCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The wire to Surepass's KYC API, shared by every bureau that goes through it
 * (CRIF: SurepassCrifService; Equifax: App\Modules\Equifax\Services\SurepassEquifaxService).
 * One token, one base URL, one set of timeouts — config('services.surepass').
 *
 * Every bureau call is PAID, so this class never retries: there is no ->retry()
 * and no ->throw() (a thrown RequestException would carry the response body —
 * and so customer data — into logs). It only sends and reports what happened at
 * the transport level; deciding what an answer MEANS is each bureau's job.
 * Nothing here logs or returns the token, a request body or a failed body.
 */
class SurepassClient
{
    /** A provider PDF larger than this is refused rather than held in memory. */
    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

    public function isConfigured(): bool
    {
        return filled(config('services.surepass.token')) && filled(config('services.surepass.base_url'));
    }

    /**
     * @param  array<string, mixed>|object  $payload
     */
    public function post(string $path, array|object $payload): SurepassCall
    {
        if (! $this->isConfigured()) {
            return SurepassCall::failed(SurepassCall::NOT_CONFIGURED);
        }

        try {
            return SurepassCall::answered($this->request()->post($path, $payload));
        } catch (ConnectionException $exception) {
            return SurepassCall::failed(self::connectionFailure($exception));
        } catch (Throwable) {
            return SurepassCall::failed(SurepassCall::UNEXPECTED);
        }
    }

    /**
     * Whether the configured token may call each endpoint, WITHOUT pulling a
     * report: the body is empty, so Surepass rejects it at input validation
     * (HTTP 400/422 = the plan includes this API) or at the token check
     * (HTTP 401) — no PAN is sent and no bureau lookup can happen.
     *
     * @param  list<string>  $paths
     * @return array<string, array{access: string, http_status: ?int}> path => result
     */
    public function checkAccess(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $call = $this->post($path, (object) []);

            if ($call->response === null) {
                $results[$path] = [
                    'access' => $call->failure === SurepassCall::NOT_CONFIGURED ? 'not_configured' : 'unreachable',
                    'http_status' => null,
                ];

                continue;
            }

            $status = $call->status();

            $results[$path] = ['access' => match (true) {
                in_array($status, [400, 422], true) => 'allowed',
                self::isScopeRejection($status, $call->message()) => 'not_enabled',
                in_array($status, [401, 403], true) => 'token_rejected',
                $status === 404 => 'not_found',
                default => 'unexpected',
            }, 'http_status' => $status];
        }

        return $results;
    }

    /**
     * The temporary PDF link in a Surepass response. Surepass has used several
     * key names across products, so the known ones are tried in order; only an
     * https URL is accepted (an empty "credit_report_link" means none).
     *
     * @param  array<string, mixed>  $data
     */
    public function pdfLink(array $data): ?string
    {
        foreach (['credit_report_link', 'pdf_link', 'report_link', 'pdf_url', 'link', 'url'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && str_starts_with(strtolower($value), 'https://') && filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Downloads the PDF behind a provider link, server side. The bearer token is
     * deliberately NOT sent: the link may point at a storage host, and the token
     * must never leave for anywhere but the Surepass API.
     */
    public function downloadPdf(string $url): ?string
    {
        try {
            $response = Http::timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        $bytes = $response->body();

        if (! $response->successful() || ! str_starts_with($bytes, '%PDF') || strlen($bytes) > self::MAX_PDF_BYTES) {
            return null;
        }

        return $bytes;
    }

    /**
     * Surepass answers 401 `invalid_token` both for a bad token and for a good
     * token whose plan lacks the API ("…not valid for this API/Scope…"). The
     * message is read only to tell the two apart — it is never stored or shown.
     */
    public static function isScopeRejection(int $status, string $message): bool
    {
        return in_array($status, [401, 403], true) && stripos($message, 'scope') !== false;
    }

    /**
     * cURL 6 (DNS), 7 (refused), 35 (TLS handshake) and a connect-phase 28 fail
     * before the request is sent, so nothing can have been processed. A read
     * timeout or a connection dropped after sending may mean Surepass already
     * did the work.
     */
    public static function connectionFailure(ConnectionException $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            preg_match('/cURL error (6|7|35)\b/', $message) === 1 => SurepassCall::CONNECTION_FAILED,
            // curl's connect-phase timeout ("Connection timed out after …") also fires
            // before anything is sent; "Operation timed out" is the read phase.
            str_contains($message, 'cURL error 28: Connection timed out') || str_contains($message, 'cURL error 28: Resolving timed out') => SurepassCall::CONNECTION_FAILED,
            str_contains($message, 'cURL error 28') || stripos($message, 'timed out') !== false => SurepassCall::TIMEOUT,
            default => SurepassCall::UNEXPECTED,
        };
    }

    private function request(): PendingRequest
    {
        // No ->retry() and no ->throw(): see the class docblock.
        return Http::baseUrl((string) config('services.surepass.base_url'))
            ->withToken((string) config('services.surepass.token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout());
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.surepass.timeout', 60));
    }

    private function connectTimeout(): int
    {
        return max(1, min((int) config('services.surepass.connect_timeout', 10), $this->timeout()));
    }
}
