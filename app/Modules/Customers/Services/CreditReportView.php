<?php

namespace App\Modules\Customers\Services;

use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilReportDocument;
use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianReportDocument;
use Carbon\CarbonInterface;

/**
 * What a customer sees of one of their own credit reports: the bureau's
 * answer read through that bureau's existing report-document class, flattened
 * into plain labelled rows for the portal.
 *
 * Deliberately left out: the raw provider JSON, the provider envelope fields,
 * sections the document classes cannot label, and each account's unlabelled
 * "extra" fields. Identifiers arrive masked from the document classes. Every
 * bureau reports different fields, any of which may be missing — each method
 * returns an empty list rather than guessing.
 */
final class CreditReportView
{
    private CibilReportDocument|EquifaxReportDocument|ExperianReportDocument|null $document;

    public function __construct(
        public readonly CreditScoreCheck $check,
        public readonly CibilCreditRequest|EquifaxCreditRequest|ExperianCreditRequest|null $request,
    ) {
        $this->document = match (true) {
            $request instanceof CibilCreditRequest && $request->hasReport() => new CibilReportDocument($request->raw_response),
            $request instanceof EquifaxCreditRequest && $request->hasReport() => new EquifaxReportDocument($request->raw_response),
            $request instanceof ExperianCreditRequest && $request->hasReport() => new ExperianReportDocument($request->raw_response),
            default => null,
        };
    }

    public static function for(CreditScoreCheck $check): self
    {
        return new self($check, $check->bureauRequest());
    }

    /** The bureau that actually answered (Experian when it stood in on the CIBIL page). */
    public function sourceBureau(): BureauName
    {
        return $this->check->sourceBureau() ?? $this->check->bureau;
    }

    public function isStandIn(): bool
    {
        return $this->sourceBureau() !== $this->check->bureau;
    }

    /** Whether the full report behind the score is still held (not purged by retention). */
    public function hasFullReport(): bool
    {
        return $this->document !== null;
    }

    public function reportDate(): ?CarbonInterface
    {
        return $this->document?->reportDate() ?? $this->request?->completed_at ?? $this->check->completed_at;
    }

    public function hasPdf(): bool
    {
        return $this->request?->hasStoredPdf() === true;
    }

    /**
     * @return array<string, string>
     */
    public function personalInfo(): array
    {
        return $this->labelled($this->document?->personalInfo() ?? []);
    }

    /**
     * Identity documents the bureau holds, numbers masked.
     *
     * @return array<string, list<string>>
     */
    public function identities(): array
    {
        return collect($this->document?->identities() ?? [])
            ->map(fn (mixed $numbers): array => collect(is_array($numbers) ? $numbers : [$numbers])
                ->map(fn (mixed $number): ?string => is_array($number) ? $this->scalar($number['number'] ?? null) : $this->scalar($number))
                ->filter()
                ->values()
                ->all())
            ->filter()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function addresses(): array
    {
        return collect($this->document?->addresses() ?? [])
            ->map(fn (mixed $row): ?string => is_array($row)
                ? collect([$row['address'] ?? null, $row['state'] ?? null, $row['postal'] ?? null])->map(fn ($part) => $this->scalar($part))->filter()->implode(', ')
                : $this->scalar($row))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function accountsSummary(): array
    {
        return $this->labelled($this->document?->accountsSummary() ?? []);
    }

    /**
     * Every reported account with its labelled fields and monthly payment
     * history (each month marked late or not by the document class).
     *
     * @return list<array{fields: array<string, string>, history: list<array{month: string, status: string, late: bool}>}>
     */
    public function accounts(): array
    {
        return collect($this->document?->accounts() ?? [])
            ->filter(fn (mixed $account): bool => is_array($account))
            ->map(fn (array $account): array => [
                'fields' => $this->labelled(is_array($account['fields'] ?? null) ? $account['fields'] : []),
                'history' => collect(is_array($account['history'] ?? null) ? $account['history'] : [])
                    ->filter(fn (mixed $month): bool => is_array($month) && isset($month['month'], $month['status']))
                    ->map(fn (array $month): array => ['month' => (string) $month['month'], 'status' => (string) $month['status'], 'late' => (bool) ($month['late'] ?? false)])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $account): bool => $account['fields'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function enquirySummary(): array
    {
        return $this->labelled($this->document?->enquirySummary() ?? []);
    }

    /**
     * @return list<array<string, string>>
     */
    public function enquiries(): array
    {
        return collect($this->document?->enquiries() ?? [])
            ->map(fn (mixed $enquiry): array => $this->labelled(is_array($enquiry) && is_array($enquiry['fields'] ?? null) ? $enquiry['fields'] : []))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Label → value rows with empty and non-scalar values dropped.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<string, string>
     */
    private function labelled(array $rows): array
    {
        return collect($rows)
            ->map(fn (mixed $value): ?string => $this->scalar($value))
            ->filter(fn (?string $value, int|string $label): bool => $value !== null && is_string($label))
            ->all();
    }

    private function scalar(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
