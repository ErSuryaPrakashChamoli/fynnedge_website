<?php

namespace App\Modules\Equifax\Services;

use App\Modules\CreditBureau\DataTransferObjects\SurepassCall;
use App\Modules\CreditBureau\Services\SurepassClient;
use App\Modules\Equifax\DataTransferObjects\EquifaxSubject;
use App\Modules\Equifax\DataTransferObjects\SurepassEquifaxResult;
use App\Modules\Equifax\Enums\EquifaxErrorCode;
use Closure;

/**
 * Surepass's Equifax endpoints (credit-report-v2): score, full report and PDF,
 * each a POST of name / id_number / id_type "pan" / mobile / consent / gender.
 * The wire (timeouts, no retries) is the shared SurepassClient, built with
 * Equifax's own token when set (EquifaxSettings::surepassClient()); this
 * class decides what an Equifax answer means. Kept apart from CRIF's
 * SurepassCrifService on purpose: different endpoints, payload and report.
 *
 * Every call is PAID. HTTP 200 alone is never success — Surepass must say
 * `success: true` and send the payload asked for; anything that leaves the
 * billing question open comes back *uncertain*, for reconciliation, never as
 * something safe to send again.
 */
class SurepassEquifaxService
{
    public const SCORE_PATH = 'api/v1/credit-report-v2/score';

    public const REPORT_PATH = 'api/v1/credit-report-v2/fetch-report';

    public const PDF_PATH = 'api/v1/credit-report-v2/fetch-pdf-report';

    private const NOT_FOUND_WORDS = ['not_found', 'no_record', 'record_not_found', 'no_hit', 'nohit'];

    public function __construct(private readonly SurepassClient $client) {}

    public function baseUrl(): string
    {
        return $this->client->baseUrl();
    }

    public function fetchScore(EquifaxSubject $subject): SurepassEquifaxResult
    {
        return $this->post(
            self::SCORE_PATH,
            [...$subject->toPayload(), 'report_type' => 'json'],
            fn (array $data): bool => array_key_exists('credit_score', $data),
            EquifaxErrorCode::MissingScore,
        );
    }

    public function fetchReport(EquifaxSubject $subject): SurepassEquifaxResult
    {
        return $this->post(
            self::REPORT_PATH,
            $subject->toPayload(),
            fn (array $data): bool => is_array($data['credit_report'] ?? null) && $data['credit_report'] !== [],
            EquifaxErrorCode::MissingReportPayload,
        );
    }

    public function fetchPdfReport(EquifaxSubject $subject): SurepassEquifaxResult
    {
        return $this->post(
            self::PDF_PATH,
            $subject->toPayload(),
            fn (array $data): bool => $this->client->pdfLink($data) !== null,
            EquifaxErrorCode::MissingPdfLink,
        );
    }

    /**
     * Which Equifax endpoints the token may call — free, pulls nothing.
     *
     * @return array<string, array{access: string, http_status: ?int}>
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
    private function post(string $path, array $payload, Closure $hasPayload, EquifaxErrorCode $missing): SurepassEquifaxResult
    {
        $call = $this->client->post($path, $payload);

        if ($call->response === null) {
            return SurepassEquifaxResult::failed(match ($call->failure) {
                SurepassCall::NOT_CONFIGURED => EquifaxErrorCode::NotConfigured,
                SurepassCall::CONNECTION_FAILED => EquifaxErrorCode::ConnectionFailed,
                SurepassCall::TIMEOUT => EquifaxErrorCode::Timeout,
                default => EquifaxErrorCode::UnexpectedError,
            });
        }

        $status = (int) $call->status();
        $body = $call->json();
        $messageCode = $call->messageCode();

        if (! $call->response->successful()) {
            return SurepassEquifaxResult::failed($this->httpFailure($status, $body !== null, $messageCode, $call->message()), $status, $messageCode);
        }

        if ($body === null) {
            return SurepassEquifaxResult::failed(EquifaxErrorCode::MalformedResponse, $status);
        }

        if (($body['success'] ?? null) === false) {
            return SurepassEquifaxResult::failed($this->rejection($messageCode), $status, $messageCode);
        }

        if (($body['success'] ?? null) !== true) {
            return SurepassEquifaxResult::failed(EquifaxErrorCode::MalformedResponse, $status, $messageCode);
        }

        $data = $body['data'] ?? null;

        if (! is_array($data) || ! $hasPayload($data)) {
            return SurepassEquifaxResult::failed($missing, $status, $messageCode);
        }

        return SurepassEquifaxResult::success($body, $status, $messageCode);
    }

    private function httpFailure(int $status, bool $isJson, ?string $messageCode, string $message): EquifaxErrorCode
    {
        return match (true) {
            SurepassClient::isScopeRejection($status, $message) => EquifaxErrorCode::ApiNotEnabled,
            $status === 401, $status === 403 => EquifaxErrorCode::AuthenticationFailed,
            $status === 402 => EquifaxErrorCode::InsufficientBalance,
            $status === 429 => EquifaxErrorCode::RateLimited,
            $status === 503 => EquifaxErrorCode::ProviderUnavailable,
            $status === 404 && $isJson => EquifaxErrorCode::NoRecordFound,
            $status === 400, $status === 422 => $this->rejection($messageCode, EquifaxErrorCode::InvalidRequest),
            $status >= 400 && $status < 500 => EquifaxErrorCode::ProviderRejected,
            // 500/502/504 and the rest: the upstream bureau call may have completed.
            default => EquifaxErrorCode::ProviderError,
        };
    }

    private function rejection(?string $messageCode, EquifaxErrorCode $fallback = EquifaxErrorCode::ProviderRejected): EquifaxErrorCode
    {
        $code = strtolower((string) $messageCode);

        return match (true) {
            $code === '' => $fallback,
            str_contains($code, 'insufficient') || str_contains($code, 'balance') => EquifaxErrorCode::InsufficientBalance,
            collect(self::NOT_FOUND_WORDS)->contains(fn (string $word) => str_contains($code, $word)) => EquifaxErrorCode::NoRecordFound,
            default => $fallback,
        };
    }
}
