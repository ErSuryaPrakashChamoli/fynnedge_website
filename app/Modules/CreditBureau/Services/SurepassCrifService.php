<?php

namespace App\Modules\CreditBureau\Services;

use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCrifResult;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Surepass's CRIF credit report endpoints: POST {base_url}/api/v1/credit-report-crif/…
 * with `Authorization: Bearer {token}` and a JSON body of first_name / last_name /
 * mobile / pan / consent.
 *
 * Every call is PAID, so this class never retries: there is no ->retry(), and a
 * response that leaves the billing question open (a read timeout, a gateway
 * error, a 200 that is not a readable success) comes back as an *uncertain*
 * failure for reconciliation rather than as something safe to send again.
 * Duplicate prevention itself is RequestCrifReport's job; this class is only
 * the wire.
 *
 * Nothing here logs or returns the token, the request body or a failed
 * response's body.
 */
class SurepassCrifService
{
    public const REPORT_PATH = 'api/v1/credit-report-crif/fetch-report';

    public const PDF_PATH = 'api/v1/credit-report-crif/fetch-report-pdf';

    public const SCORE_PATH = 'api/v1/credit-report-crif/score';

    /** A provider PDF larger than this is refused rather than held in memory. */
    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

    private const NOT_FOUND_WORDS = ['not_found', 'no_record', 'record_not_found', 'no_hit', 'nohit'];

    public function fetchReport(CrifSubject $subject): SurepassCrifResult
    {
        return $this->post(self::REPORT_PATH, $subject->toPayload(), fn (array $data): bool => $this->hasReport($data));
    }

    public function fetchReportPdf(CrifSubject $subject): SurepassCrifResult
    {
        return $this->post(self::PDF_PATH, [...$subject->toPayload(), 'raw' => false], fn (array $data): bool => $this->pdfLink($data) !== null);
    }

    public function fetchScore(CrifSubject $subject): SurepassCrifResult
    {
        return $this->post(self::SCORE_PATH, $subject->toPayload(), fn (array $data): bool => array_key_exists('credit_score', $data));
    }

    /**
     * The temporary PDF link out of a fetch-report-pdf response. Surepass has
     * used several key names across products, so the known ones are tried in
     * order; only an https URL is accepted.
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
     * @param  array<string, mixed>  $payload
     * @param  Closure(array<string, mixed>): bool  $hasPayload
     */
    private function post(string $path, array $payload, Closure $hasPayload): SurepassCrifResult
    {
        $config = config('services.surepass');

        if (blank($config['token'] ?? null) || blank($config['base_url'] ?? null)) {
            return SurepassCrifResult::failed(CrifErrorCode::NotConfigured);
        }

        try {
            $response = $this->client($config['base_url'], (string) $config['token'])->post($path, $payload);
        } catch (ConnectionException $exception) {
            return SurepassCrifResult::failed($this->connectionFailure($exception));
        } catch (Throwable) {
            return SurepassCrifResult::failed(CrifErrorCode::UnexpectedError);
        }

        return $this->interpret($response, $hasPayload);
    }

    private function client(string $baseUrl, #[\SensitiveParameter] string $token): PendingRequest
    {
        // No ->retry() and no ->throw(): a second attempt after a read timeout
        // could be a second charge, and a thrown RequestException would carry
        // the response body (and so customer data) into logs.
        return Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout());
    }

    /**
     * @param  Closure(array<string, mixed>): bool  $hasPayload
     */
    private function interpret(Response $response, Closure $hasPayload): SurepassCrifResult
    {
        $status = $response->status();
        $body = $response->json();
        $messageCode = is_array($body) && is_string($body['message_code'] ?? null) ? $body['message_code'] : null;

        if (! $response->successful()) {
            return SurepassCrifResult::failed($this->httpFailure($status, is_array($body), $messageCode), $status, $messageCode);
        }

        if (! is_array($body)) {
            return SurepassCrifResult::failed(CrifErrorCode::MalformedResponse, $status);
        }

        if (($body['success'] ?? null) === false) {
            return SurepassCrifResult::failed($this->rejection($messageCode), $status, $messageCode);
        }

        // An HTTP 200 alone is never taken as success: Surepass marks it explicitly.
        if (($body['success'] ?? null) !== true) {
            return SurepassCrifResult::failed(CrifErrorCode::MalformedResponse, $status, $messageCode);
        }

        $data = $body['data'] ?? null;

        if (! is_array($data) || ! $hasPayload($data)) {
            return SurepassCrifResult::failed(CrifErrorCode::MissingReportPayload, $status, $messageCode);
        }

        return SurepassCrifResult::success($body, $status);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasReport(array $data): bool
    {
        return is_array($data['credit_report'] ?? null) && $data['credit_report'] !== [];
    }

    private function httpFailure(int $status, bool $isJson, ?string $messageCode): CrifErrorCode
    {
        return match (true) {
            $status === 401, $status === 403 => CrifErrorCode::AuthenticationFailed,
            $status === 402 => CrifErrorCode::InsufficientBalance,
            $status === 429 => CrifErrorCode::RateLimited,
            $status === 503 => CrifErrorCode::ProviderUnavailable,
            $status === 404 && $isJson => CrifErrorCode::NoRecordFound,
            $status === 400, $status === 422 => $this->rejection($messageCode, CrifErrorCode::InvalidRequest),
            $status >= 400 && $status < 500 => CrifErrorCode::ProviderRejected,
            // 500/502/504 and the rest: the upstream bureau call may have completed.
            default => CrifErrorCode::ProviderError,
        };
    }

    private function rejection(?string $messageCode, CrifErrorCode $fallback = CrifErrorCode::ProviderRejected): CrifErrorCode
    {
        $code = strtolower((string) $messageCode);

        return match (true) {
            $code === '' => $fallback,
            str_contains($code, 'insufficient') || str_contains($code, 'balance') => CrifErrorCode::InsufficientBalance,
            collect(self::NOT_FOUND_WORDS)->contains(fn (string $word) => str_contains($code, $word)) => CrifErrorCode::NoRecordFound,
            default => $fallback,
        };
    }

    /**
     * cURL 6 (DNS), 7 (refused), 35 (TLS handshake) and a connect-phase 28 fail
     * before the request is sent, so nothing can have been processed. A read
     * timeout or a connection dropped after sending may mean Surepass already
     * did the work.
     */
    private function connectionFailure(ConnectionException $exception): CrifErrorCode
    {
        $message = $exception->getMessage();

        return match (true) {
            preg_match('/cURL error (6|7|35)\b/', $message) === 1 => CrifErrorCode::ConnectionFailed,
            // curl's connect-phase timeout ("Connection timed out after …") also fires
            // before anything is sent; "Operation timed out" is the read phase.
            str_contains($message, 'cURL error 28: Connection timed out') || str_contains($message, 'cURL error 28: Resolving timed out') => CrifErrorCode::ConnectionFailed,
            str_contains($message, 'cURL error 28') || stripos($message, 'timed out') !== false => CrifErrorCode::Timeout,
            default => CrifErrorCode::UnexpectedError,
        };
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
