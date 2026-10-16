<?php

namespace App\Modules\Cibil\Services;

use App\Modules\Cibil\DataTransferObjects\CibilSubject;
use App\Modules\Cibil\DataTransferObjects\SurepassCibilResult;
use App\Modules\Cibil\Enums\CibilErrorCode;
use App\Modules\CreditBureau\DataTransferObjects\SurepassCall;
use App\Modules\CreditBureau\Services\SurepassClient;
use Closure;

/**
 * Surepass's CIBIL endpoints (credit-report-cibil): score, full report
 * and PDF, each a POST of mobile / pan / name / gender / consent "Y". The
 * wire (timeouts, no retries) is the shared SurepassClient, built with
 * CIBIL's own token and no fallback (CibilSettings::surepassClient()); this
 * class decides what a CIBIL answer means. Kept apart from the CRIF, Equifax and Experian Surepass
 * services on purpose: different endpoints, payload and report.
 *
 * Every call is PAID. HTTP 200 alone is never success — Surepass must say
 * `success: true` and send the payload asked for; anything that leaves the
 * billing question open comes back *uncertain*, for reconciliation, never as
 * something safe to send again.
 */
class SurepassCibilService
{
    public const SCORE_PATH = 'api/v1/credit-report-cibil/score';

    public const REPORT_PATH = 'api/v1/credit-report-cibil/fetch-report';

    public const PDF_PATH = 'api/v1/credit-report-cibil/fetch-report-pdf';

    private const NOT_FOUND_WORDS = ['not_found', 'no_record', 'record_not_found', 'no_hit', 'nohit'];

    public function __construct(private readonly SurepassClient $client) {}

    public function baseUrl(): string
    {
        return $this->client->baseUrl();
    }

    public function fetchScore(CibilSubject $subject): SurepassCibilResult
    {
        return $this->post(
            self::SCORE_PATH,
            $subject->toPayload(),
            fn (array $data): bool => array_key_exists('credit_score', $data),
            CibilErrorCode::MissingScore,
        );
    }

    public function fetchReport(CibilSubject $subject): SurepassCibilResult
    {
        return $this->post(
            self::REPORT_PATH,
            $subject->toPayload(),
            fn (array $data): bool => is_array($data['credit_report'] ?? null) && $data['credit_report'] !== [],
            CibilErrorCode::MissingReportPayload,
        );
    }

    /**
     * Success only when Surepass sends a usable https link or a Base64 PDF:
     * the PDF endpoint's credit_report may be null, so it is never read as the
     * JSON report.
     */
    public function fetchPdfReport(CibilSubject $subject): SurepassCibilResult
    {
        return $this->post(
            self::PDF_PATH,
            $subject->toPayload(),
            fn (array $data): bool => $this->client->pdfLink($data) !== null || CibilReportDocument::decodePdf($data['credit_report_base64'] ?? null) !== null,
            CibilErrorCode::MissingPdfLink,
        );
    }

    /**
     * Which CIBIL endpoints the token may call — free, pulls nothing.
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
    private function post(string $path, array $payload, Closure $hasPayload, CibilErrorCode $missing): SurepassCibilResult
    {
        $call = $this->client->post($path, $payload);

        if ($call->response === null) {
            return SurepassCibilResult::failed(match ($call->failure) {
                SurepassCall::NOT_CONFIGURED => CibilErrorCode::NotConfigured,
                SurepassCall::CONNECTION_FAILED => CibilErrorCode::ConnectionFailed,
                SurepassCall::TIMEOUT => CibilErrorCode::Timeout,
                default => CibilErrorCode::UnexpectedError,
            });
        }

        $status = (int) $call->status();
        $body = $call->json();
        $messageCode = $call->messageCode();

        if (! $call->response->successful()) {
            return SurepassCibilResult::failed($this->httpFailure($status, $body !== null, $messageCode, $call->message()), $status, $messageCode);
        }

        if ($body === null) {
            return SurepassCibilResult::failed(CibilErrorCode::MalformedResponse, $status);
        }

        if (($body['success'] ?? null) === false) {
            return SurepassCibilResult::failed($this->rejection($messageCode), $status, $messageCode);
        }

        if (($body['success'] ?? null) !== true) {
            return SurepassCibilResult::failed(CibilErrorCode::MalformedResponse, $status, $messageCode);
        }

        $data = $body['data'] ?? null;

        if (! is_array($data) || ! $hasPayload($data)) {
            return SurepassCibilResult::failed($missing, $status, $messageCode);
        }

        return SurepassCibilResult::success($body, $status, $messageCode);
    }

    private function httpFailure(int $status, bool $isJson, ?string $messageCode, string $message): CibilErrorCode
    {
        return match (true) {
            SurepassClient::isScopeRejection($status, $message) => CibilErrorCode::ApiNotEnabled,
            $status === 401, $status === 403 => CibilErrorCode::AuthenticationFailed,
            $status === 402 => CibilErrorCode::InsufficientBalance,
            $status === 429 => CibilErrorCode::RateLimited,
            $status === 503 => CibilErrorCode::ProviderUnavailable,
            $status === 404 && $isJson => CibilErrorCode::NoRecordFound,
            $status === 400, $status === 422 => $this->rejection($messageCode, CibilErrorCode::InvalidRequest),
            $status >= 400 && $status < 500 => CibilErrorCode::ProviderRejected,
            // 500/502/504 and the rest: the upstream bureau call may have completed.
            default => CibilErrorCode::ProviderError,
        };
    }

    private function rejection(?string $messageCode, CibilErrorCode $fallback = CibilErrorCode::ProviderRejected): CibilErrorCode
    {
        $code = strtolower((string) $messageCode);

        return match (true) {
            $code === '' => $fallback,
            str_contains($code, 'insufficient') || str_contains($code, 'balance') => CibilErrorCode::InsufficientBalance,
            collect(self::NOT_FOUND_WORDS)->contains(fn (string $word) => str_contains($code, $word)) => CibilErrorCode::NoRecordFound,
            default => $fallback,
        };
    }
}
