<?php

namespace App\Modules\CreditBureau\Services;

use App\Modules\CreditBureau\DataTransferObjects\CrifSubject;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCall;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCrifResult;
use App\Modules\CreditBureau\Enums\CrifErrorCode;
use Closure;

/**
 * Surepass's CRIF credit report endpoints: POST {base_url}/api/v1/credit-report-crif/…
 * with a JSON body of first_name / last_name / mobile / pan / consent. The wire
 * itself (token, timeouts, no retries) is the shared SurepassClient; this class
 * decides what a CRIF answer means.
 *
 * Every call is PAID: a response that leaves the billing question open (a read
 * timeout, a gateway error, a 200 that is not a readable success) comes back
 * as an *uncertain* failure for reconciliation rather than as something safe
 * to send again. Duplicate prevention itself is RequestCrifReport's job.
 */
class SurepassCrifService
{
    public const REPORT_PATH = 'api/v1/credit-report-crif/fetch-report';

    public const PDF_PATH = 'api/v1/credit-report-crif/fetch-report-pdf';

    public const SCORE_PATH = 'api/v1/credit-report-crif/score';

    private const NOT_FOUND_WORDS = ['not_found', 'no_record', 'record_not_found', 'no_hit', 'nohit'];

    public function __construct(private readonly SurepassClient $client) {}

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
     * Whether the configured token may call each CRIF endpoint, without pulling
     * a report (see SurepassClient::checkAccess()).
     *
     * @return array<string, array{access: string, http_status: ?int}> path => result
     */
    public function checkAccess(): array
    {
        return $this->client->checkAccess([self::REPORT_PATH, self::SCORE_PATH, self::PDF_PATH]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pdfLink(array $data): ?string
    {
        return $this->client->pdfLink($data);
    }

    public function downloadPdf(string $url): ?string
    {
        return $this->client->downloadPdf($url);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(array<string, mixed>): bool  $hasPayload
     */
    private function post(string $path, array $payload, Closure $hasPayload): SurepassCrifResult
    {
        $call = $this->client->post($path, $payload);

        if ($call->response === null) {
            return SurepassCrifResult::failed(match ($call->failure) {
                SurepassCall::NOT_CONFIGURED => CrifErrorCode::NotConfigured,
                SurepassCall::CONNECTION_FAILED => CrifErrorCode::ConnectionFailed,
                SurepassCall::TIMEOUT => CrifErrorCode::Timeout,
                default => CrifErrorCode::UnexpectedError,
            });
        }

        return $this->interpret($call, $hasPayload);
    }

    /**
     * @param  Closure(array<string, mixed>): bool  $hasPayload
     */
    private function interpret(SurepassCall $call, Closure $hasPayload): SurepassCrifResult
    {
        $status = (int) $call->status();
        $body = $call->json();
        $messageCode = $call->messageCode();

        if (! $call->response->successful()) {
            return SurepassCrifResult::failed($this->httpFailure($status, $body !== null, $messageCode, $call->message()), $status, $messageCode);
        }

        if ($body === null) {
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

    private function httpFailure(int $status, bool $isJson, ?string $messageCode, string $message = ''): CrifErrorCode
    {
        return match (true) {
            SurepassClient::isScopeRejection($status, $message) => CrifErrorCode::ApiNotEnabled,
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
}
