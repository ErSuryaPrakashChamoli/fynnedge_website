<?php

namespace App\Modules\Experian\Services;

use App\Modules\CreditBureau\DataTransferObjects\SurepassCall;
use App\Modules\CreditBureau\Services\SurepassClient;
use App\Modules\Experian\DataTransferObjects\ExperianSubject;
use App\Modules\Experian\DataTransferObjects\SurepassExperianResult;
use App\Modules\Experian\Enums\ExperianErrorCode;
use Closure;

/**
 * Surepass's Experian endpoints (credit-report-experian): score, full report
 * and PDF, each a POST of name / pan / mobile / consent "Y". The wire
 * (timeouts, no retries) is the shared SurepassClient, built with Experian's
 * own token when set (ExperianSettings::surepassClient()); this class decides
 * what an Experian answer means. Kept apart from CRIF's and Equifax's Surepass
 * services on purpose: different endpoints, payload and report.
 *
 * Every call is PAID. HTTP 200 alone is never success — Surepass must say
 * `success: true` and send the payload asked for; anything that leaves the
 * billing question open comes back *uncertain*, for reconciliation, never as
 * something safe to send again.
 */
class SurepassExperianService
{
    public const SCORE_PATH = 'api/v1/credit-report-experian/score';

    public const REPORT_PATH = 'api/v1/credit-report-experian/fetch-report';

    public const PDF_PATH = 'api/v1/credit-report-experian/fetch-report-pdf';

    private const NOT_FOUND_WORDS = ['not_found', 'no_record', 'record_not_found', 'no_hit', 'nohit'];

    public function __construct(private readonly SurepassClient $client) {}

    public function baseUrl(): string
    {
        return $this->client->baseUrl();
    }

    public function fetchScore(ExperianSubject $subject): SurepassExperianResult
    {
        return $this->post(
            self::SCORE_PATH,
            $subject->toPayload(),
            fn (array $data): bool => array_key_exists('credit_score', $data),
            ExperianErrorCode::MissingScore,
        );
    }

    public function fetchReport(ExperianSubject $subject): SurepassExperianResult
    {
        return $this->post(
            self::REPORT_PATH,
            $subject->toPayload(),
            fn (array $data): bool => is_array($data['credit_report'] ?? null) && $data['credit_report'] !== [],
            ExperianErrorCode::MissingReportPayload,
        );
    }

    /**
     * Success only when Surepass sends a usable https link: the PDF endpoint's
     * credit_report object may be empty, so it is never read as the JSON report.
     */
    public function fetchPdfReport(ExperianSubject $subject): SurepassExperianResult
    {
        return $this->post(
            self::PDF_PATH,
            $subject->toPayload(),
            fn (array $data): bool => $this->client->pdfLink($data) !== null,
            ExperianErrorCode::MissingPdfLink,
        );
    }

    /**
     * Which Experian endpoints the token may call — free, pulls nothing.
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
    private function post(string $path, array $payload, Closure $hasPayload, ExperianErrorCode $missing): SurepassExperianResult
    {
        $call = $this->client->post($path, $payload);

        if ($call->response === null) {
            return SurepassExperianResult::failed(match ($call->failure) {
                SurepassCall::NOT_CONFIGURED => ExperianErrorCode::NotConfigured,
                SurepassCall::CONNECTION_FAILED => ExperianErrorCode::ConnectionFailed,
                SurepassCall::TIMEOUT => ExperianErrorCode::Timeout,
                default => ExperianErrorCode::UnexpectedError,
            });
        }

        $status = (int) $call->status();
        $body = $call->json();
        $messageCode = $call->messageCode();

        if (! $call->response->successful()) {
            return SurepassExperianResult::failed($this->httpFailure($status, $body !== null, $messageCode, $call->message()), $status, $messageCode);
        }

        if ($body === null) {
            return SurepassExperianResult::failed(ExperianErrorCode::MalformedResponse, $status);
        }

        if (($body['success'] ?? null) === false) {
            return SurepassExperianResult::failed($this->rejection($messageCode), $status, $messageCode);
        }

        if (($body['success'] ?? null) !== true) {
            return SurepassExperianResult::failed(ExperianErrorCode::MalformedResponse, $status, $messageCode);
        }

        $data = $body['data'] ?? null;

        if (! is_array($data) || ! $hasPayload($data)) {
            return SurepassExperianResult::failed($missing, $status, $messageCode);
        }

        return SurepassExperianResult::success($body, $status, $messageCode);
    }

    private function httpFailure(int $status, bool $isJson, ?string $messageCode, string $message): ExperianErrorCode
    {
        return match (true) {
            SurepassClient::isScopeRejection($status, $message) => ExperianErrorCode::ApiNotEnabled,
            $status === 401, $status === 403 => ExperianErrorCode::AuthenticationFailed,
            $status === 402 => ExperianErrorCode::InsufficientBalance,
            $status === 429 => ExperianErrorCode::RateLimited,
            $status === 503 => ExperianErrorCode::ProviderUnavailable,
            $status === 404 && $isJson => ExperianErrorCode::NoRecordFound,
            $status === 400, $status === 422 => $this->rejection($messageCode, ExperianErrorCode::InvalidRequest),
            $status >= 400 && $status < 500 => ExperianErrorCode::ProviderRejected,
            // 500/502/504 and the rest: the upstream bureau call may have completed.
            default => ExperianErrorCode::ProviderError,
        };
    }

    private function rejection(?string $messageCode, ExperianErrorCode $fallback = ExperianErrorCode::ProviderRejected): ExperianErrorCode
    {
        $code = strtolower((string) $messageCode);

        return match (true) {
            $code === '' => $fallback,
            str_contains($code, 'insufficient') || str_contains($code, 'balance') => ExperianErrorCode::InsufficientBalance,
            collect(self::NOT_FOUND_WORDS)->contains(fn (string $word) => str_contains($code, $word)) => ExperianErrorCode::NoRecordFound,
            default => $fallback,
        };
    }
}
