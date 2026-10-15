<?php

namespace App\Modules\CreditBureau\Services;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A read-only view over one stored Surepass CRIF response, for the report
 * screen and for pulling the header fields into indexed columns.
 *
 * CRIF's JSON is converted from XML, so it is irregular: a block holding one
 * item is an object while two items are a list, an empty block is "" rather
 * than null or [], amounts are Indian-grouped strings ("2,68,889") and dates
 * are dd-mm-yyyy. Every accessor here tolerates all of that, returns null for
 * a value that is absent rather than inventing a default, and nothing is
 * dropped: whatever this class does not recognise comes back through
 * otherSections() / extra fields, and the complete response stays available as
 * raw JSON. The original response is never modified.
 */
final class CrifReportDocument
{
    private const ACCOUNT_FIELDS = [
        'Lender' => ['CREDIT-GUARANTOR', 'MEMBER-NAME'],
        'Account type' => ['ACCT-TYPE', 'ACCOUNT-TYPE'],
        'Account number' => ['ACCT-NUMBER', 'ACCOUNT-NUMBER'],
        'Status' => ['ACCOUNT-STATUS'],
        'Ownership' => ['OWNERSHIP-IND', 'OWNERSHIP-TYPE'],
        'Sanctioned amount' => ['SANCTIONED-AMT', 'SANCTION-AMT', 'SANCTIONED-AMOUNT'],
        'Credit limit' => ['CREDIT-LIMIT'],
        'Disbursed amount' => ['DISBURSED-AMT'],
        'Current balance' => ['CURRENT-BAL'],
        'Overdue amount' => ['OVERDUE-AMT'],
        'Write-off amount' => ['WRITE-OFF-AMT'],
        'Principal write-off' => ['PRINCIPAL-WRITE-OFF-AMT'],
        'Settlement amount' => ['SETTLEMENT-AMT'],
        'Instalment' => ['INSTALLMENT-AMT'],
        'Interest rate' => ['INTEREST-RATE'],
        'Repayment tenure' => ['REPAYMENT-TENURE'],
        'Date reported' => ['DATE-REPORTED'],
        'Disbursed on' => ['DISBURSED-DT'],
        'Last payment' => ['LAST-PAYMENT-DATE'],
        'Closed on' => ['CLOSED-DATE'],
        'Account remarks' => ['ACCOUNT-REMARKS'],
        'Security status' => ['SECURITY-STATUS'],
        'Matched type' => ['MATCHED-TYPE'],
    ];

    private const AMOUNT_LABELS = [
        'Sanctioned amount', 'Credit limit', 'Disbursed amount', 'Current balance', 'Overdue amount',
        'Write-off amount', 'Principal write-off', 'Settlement amount', 'Instalment',
    ];

    private const INQUIRY_FIELDS = [
        'Institution' => ['MEMBER-NAME', 'LENDER-NAME'],
        'Inquiry date' => ['INQUIRY-DATE', 'DATE-OF-INQUIRY'],
        'Purpose' => ['PURPOSE', 'CREDIT-PURPOSE'],
        'Amount' => ['AMOUNT'],
        'Ownership' => ['OWNERSHIP-TYPE'],
        'Remark' => ['REMARK'],
    ];

    private const VARIATION_LABELS = [
        'NAME-VARIATIONS' => 'Name',
        'ADDRESS-VARIATIONS' => 'Address',
        'PAN-VARIATIONS' => 'PAN',
        'UID-VARIATIONS' => 'Aadhaar (UID)',
        'OTHER-ID-VARIATIONS' => 'Other ID',
        'DRIVING-LICENSE-VARIATIONS' => 'Driving licence',
        'DATE-OF-BIRTH-VARIATIONS' => 'Date of birth',
        'VOTER-ID-VARIATIONS' => 'Voter ID',
        'PASSPORT-VARIATIONS' => 'Passport',
        'PHONE-NUMBER-VARIATIONS' => 'Phone',
        'RATION-CARD-VARIATIONS' => 'Ration card',
        'EMAIL-VARIATIONS' => 'Email',
    ];

    /** Identity numbers are masked to their last four characters on screen (full values stay in the raw JSON). */
    private const MASKED_VARIATIONS = [
        'PAN-VARIATIONS', 'UID-VARIATIONS', 'OTHER-ID-VARIATIONS', 'DRIVING-LICENSE-VARIATIONS',
        'VOTER-ID-VARIATIONS', 'PASSPORT-VARIATIONS', 'PHONE-NUMBER-VARIATIONS', 'RATION-CARD-VARIATIONS',
    ];

    /** CRIF nests the report under one of these wrappers in some response versions. */
    private const WRAPPERS = ['B2C-REPORT', 'INDV-REPORT-FILE', 'INDV-REPORTS', 'INDV-REPORT', 'CIR-REPORT-FILE'];

    private const HANDLED_SECTIONS = [
        'HEADER', 'PERSONAL-INFO-VARIATION', 'ACCOUNTS-SUMMARY', 'RESPONSES', 'INQUIRY-HISTORY', 'SCORES', 'EMPLOYMENT-DETAILS',
    ];

    /** Envelope keys whose values are identity data and are masked on screen. */
    private const SENSITIVE_DATA_KEYS = ['pan', 'pan_number', 'mobile', 'phone', 'aadhaar', 'aadhaar_number', 'uid'];

    /**
     * @param  array<string, mixed>  $body  The complete stored provider response.
     */
    public function __construct(private readonly array $body) {}

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return is_array($this->body['data'] ?? null) ? $this->body['data'] : [];
    }

    /**
     * The CRIF report itself, unwrapped from whichever envelope it came in.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $report = $this->data()['credit_report'] ?? null;

        if (is_array($report) && array_is_list($report)) {
            $report = collect($report)->first(fn ($item) => is_array($item));
        }

        for ($depth = 0; is_array($report) && $depth < 4; $depth++) {
            $wrapper = collect(self::WRAPPERS)->first(fn (string $key) => is_array($report[$key] ?? null));

            if ($wrapper === null) {
                break;
            }

            $report = $report[$wrapper];

            if (array_is_list($report)) {
                $report = collect($report)->first(fn ($item) => is_array($item)) ?? [];
            }
        }

        return is_array($report) ? $report : [];
    }

    /**
     * The score as Surepass sent it, before validation (may be "", "-1", "NH"…).
     */
    public function rawScore(): ?string
    {
        $score = $this->data()['credit_score'] ?? null;

        if ($this->text($score) === null) {
            $score = $this->scores()[0]['value'] ?? null;
        }

        return $this->text($score);
    }

    /**
     * A CRIF score is 300–900. Anything else (blank, "-1", "NH" for no history,
     * a decimal) is not a score and is stored as null — never coerced to 0.
     */
    public function score(): ?int
    {
        $raw = $this->rawScore();

        if ($raw === null || preg_match('/^\d{3}$/', $raw) !== 1) {
            return null;
        }

        $score = (int) $raw;

        return $score >= 300 && $score <= 900 ? $score : null;
    }

    /**
     * @return array{report_id: ?string, inquiry_id: ?string, batch_id: ?string, status: ?string, requested_on: ?string, issued_on: ?string}
     */
    public function header(): array
    {
        $header = $this->node($this->report(), 'HEADER');

        return [
            'report_id' => $this->text($header['REPORT-ID'] ?? null),
            'inquiry_id' => $this->text($header['inquiryId_'] ?? $header['INQUIRY-ID'] ?? null),
            'batch_id' => $this->text($header['BATCH-ID'] ?? null),
            'status' => $this->text($header['STATUS'] ?? null),
            'requested_on' => $this->text($header['DATE-OF-REQUEST'] ?? null),
            'issued_on' => $this->text($header['DATE-OF-ISSUE'] ?? null),
        ];
    }

    public function clientId(): ?string
    {
        return $this->text($this->data()['client_id'] ?? null);
    }

    public function issuedOn(): ?CarbonImmutable
    {
        return $this->date($this->header()['issued_on']);
    }

    public function requestedOn(): ?CarbonImmutable
    {
        return $this->date($this->header()['requested_on']);
    }

    /**
     * The headline credit summary. Bureau-supplied figures come from
     * ACCOUNTS-SUMMARY; the rest are derived ONLY from account rows that carry
     * the field, and are null when no account does.
     *
     * @return array<string, string|null>
     */
    public function summary(): array
    {
        $summary = $this->node($this->report(), 'ACCOUNTS-SUMMARY');
        $primary = $this->node($summary, 'PRIMARY-ACCOUNTS-SUMMARY');
        $derived = $this->node($summary, 'DERIVED-ATTRIBUTES');
        $accounts = $this->accounts();

        return [
            'Total accounts' => $this->text($primary['PRIMARY-NUMBER-OF-ACCOUNTS'] ?? null),
            'Active accounts' => $this->text($primary['PRIMARY-ACTIVE-NUMBER-OF-ACCOUNTS'] ?? null),
            'Closed accounts' => $this->countAccounts($accounts, fn (array $account) => str_contains(strtolower((string) $account['fields']['Status']), 'closed')),
            'Overdue accounts' => $this->text($primary['PRIMARY-OVERDUE-NUMBER-OF-ACCOUNTS'] ?? null),
            'Secured accounts' => $this->text($primary['PRIMARY-SECURED-NUMBER-OF-ACCOUNTS'] ?? null),
            'Unsecured accounts' => $this->text($primary['PRIMARY-UNSECURED-NUMBER-OF-ACCOUNTS'] ?? null),
            'Current outstanding balance' => $this->rupees($primary['PRIMARY-CURRENT-BALANCE'] ?? null),
            'Total sanctioned amount' => $this->rupees($primary['PRIMARY-SANCTIONED-AMOUNT'] ?? null),
            'Total disbursed amount' => $this->rupees($primary['PRIMARY-DISBURSED-AMOUNT'] ?? null),
            'Total overdue amount' => $this->sumAccountAmounts($accounts, 'Overdue amount'),
            'Inquiries in last 6 months' => $this->text($derived['INQURIES-IN-LAST-SIX-MONTHS'] ?? $derived['INQUIRIES-IN-LAST-SIX-MONTHS'] ?? null),
            'Inquiries listed in report' => $this->inquiries() === [] ? null : (string) count($this->inquiries()),
            'Credit history length' => $this->yearsMonths($derived['LENGTH-OF-CREDIT-HISTORY-YEAR'] ?? null, $derived['LENGTH-OF-CREDIT-HISTORY-MONTH'] ?? null),
            'Average account age' => $this->yearsMonths($derived['AVERAGE-ACCOUNT-AGE-YEAR'] ?? null, $derived['AVERAGE-ACCOUNT-AGE-MONTH'] ?? null),
            'New accounts in last 6 months' => $this->text($derived['NEW-ACCOUNTS-IN-LAST-SIX-MONTHS'] ?? null),
            'New delinquent accounts in last 6 months' => $this->text($derived['NEW-DELINQ-ACCOUNT-IN-LAST-SIX-MONTHS'] ?? null),
            'Accounts with late payments in history' => $this->countAccounts(
                collect($accounts)->filter(fn (array $account) => $account['payment_history'] !== [])->all(),
                fn (array $account) => collect($account['payment_history'])->contains(fn (array $month) => $month['late']),
            ),
        ];
    }

    /**
     * Secondary (guarantor / joint) accounts, only when CRIF reports any.
     *
     * @return array<string, string|null>
     */
    public function secondarySummary(): array
    {
        $secondary = $this->node($this->node($this->report(), 'ACCOUNTS-SUMMARY'), 'SECONDARY-ACCOUNTS-SUMMARY');

        if ($secondary === []) {
            return [];
        }

        return [
            'Total accounts' => $this->text($secondary['SECONDARY-NUMBER-OF-ACCOUNTS'] ?? null),
            'Active accounts' => $this->text($secondary['SECONDARY-ACTIVE-NUMBER-OF-ACCOUNTS'] ?? null),
            'Overdue accounts' => $this->text($secondary['SECONDARY-OVERDUE-NUMBER-OF-ACCOUNTS'] ?? null),
            'Current outstanding balance' => $this->rupees($secondary['SECONDARY-CURRENT-BALANCE'] ?? null),
            'Total sanctioned amount' => $this->rupees($secondary['SECONDARY-SANCTIONED-AMOUNT'] ?? null),
            'Total disbursed amount' => $this->rupees($secondary['SECONDARY-DISBURSED-AMOUNT'] ?? null),
        ];
    }

    /**
     * Every reported account: the recognised fields by label (null when absent),
     * the parsed payment history, and every other field CRIF sent, untouched.
     *
     * @return list<array{fields: array<string, string|null>, payment_history: list<array{month: string, value: string, late: bool}>, extra: array<string, mixed>}>
     */
    public function accounts(): array
    {
        $responses = $this->node($this->report(), 'RESPONSES');

        return collect($this->items($responses['RESPONSE'] ?? null))
            ->map(fn (array $response) => is_array($response['LOAN-DETAILS'] ?? null) ? $response['LOAN-DETAILS'] : $response)
            ->map(function (array $loan): array {
                $fields = [];
                $consumed = ['COMBINED-PAYMENT-HISTORY'];

                foreach (self::ACCOUNT_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $loan));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($loan[$key]) : null;

                    $fields[$label] = match (true) {
                        $value === null => null,
                        $label === 'Account number' => $this->maskTail($value),
                        in_array($label, self::AMOUNT_LABELS, true) => $this->rupees($value),
                        default => $value,
                    };
                }

                return [
                    'fields' => $fields,
                    'payment_history' => $this->paymentHistory($loan['COMBINED-PAYMENT-HISTORY'] ?? null),
                    'extra' => collect($loan)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<array{value: string, reported_on: ?string}>>
     */
    public function variations(): array
    {
        $info = $this->node($this->report(), 'PERSONAL-INFO-VARIATION');
        $variations = [];

        foreach ($info as $key => $block) {
            $label = self::VARIATION_LABELS[$key] ?? ucfirst(strtolower(str_replace(['-VARIATIONS', '-'], ['', ' '], (string) $key)));
            $mask = in_array($key, self::MASKED_VARIATIONS, true) || ! array_key_exists($key, self::VARIATION_LABELS);

            $rows = collect($this->items(is_array($block) ? ($block['VARIATION'] ?? $block) : null))
                ->map(function (array $variation) use ($mask): ?array {
                    $value = $this->text($variation['VALUE'] ?? null);

                    return $value === null ? null : [
                        'value' => $mask ? $this->maskTail($value) : $value,
                        'reported_on' => $this->text($variation['REPORTED-DATE'] ?? null),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($rows !== []) {
                $variations[$label] = $rows;
            }
        }

        return $variations;
    }

    /**
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    public function inquiries(): array
    {
        $history = $this->node($this->report(), 'INQUIRY-HISTORY');
        $items = $this->items($history['HISTORY'] ?? $history['INQUIRY'] ?? null);

        return collect($items)->map(function (array $inquiry): array {
            $fields = [];
            $consumed = [];

            foreach (self::INQUIRY_FIELDS as $label => $keys) {
                $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $inquiry));
                $consumed = [...$consumed, ...$keys];
                $value = $key !== null ? $this->text($inquiry[$key]) : null;
                $fields[$label] = $label === 'Amount' ? $this->rupees($value) : $value;
            }

            return [
                'fields' => $fields,
                'extra' => collect($inquiry)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all(),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function employment(): array
    {
        $details = $this->node($this->report(), 'EMPLOYMENT-DETAILS');

        return collect($this->items($details['EMPLOYMENT-DETAIL'] ?? null))
            ->map(fn (array $detail) => collect($detail)->reject(fn ($value) => $this->isEmpty($value))->all())
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Score factors are CRIF reason codes (e.g. "SF05"). They are listed as
     * sent; this class does not guess at their meaning.
     *
     * @return list<array{type: ?string, value: ?string, comments: ?string, factors: list<string>}>
     */
    public function scores(): array
    {
        $scores = $this->node($this->report(), 'SCORES');

        return collect($this->items($scores['SCORE'] ?? null))
            ->map(function (array $score): array {
                $factors = $score['SCORE-FACTORS'] ?? null;
                $factors = is_array($factors) ? $factors : explode('|', (string) $this->text($factors));

                return [
                    'type' => $this->text($score['SCORE-TYPE'] ?? null),
                    'value' => $this->text($score['SCORE-VALUE'] ?? null),
                    'comments' => $this->text($score['SCORE-COMMENTS'] ?? null),
                    'factors' => collect($factors)
                        ->map(fn ($factor) => is_scalar($factor) ? trim((string) $factor) : null)
                        ->filter(fn (?string $factor) => $factor !== null && $factor !== '')
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Report sections this class has no dedicated layout for (SECONDARY-MATCHES,
     * PRINTABLE-REPORT, flags, anything CRIF adds later), shown as-is.
     *
     * @return array<string, mixed>
     */
    public function otherSections(): array
    {
        return collect($this->report())
            ->except(self::HANDLED_SECTIONS)
            ->reject(fn ($value) => $this->isEmpty($value))
            ->all();
    }

    /**
     * Surepass envelope fields beside the report (client_id, echoed name…),
     * with identity numbers masked.
     *
     * @return array<string, mixed>
     */
    public function providerFields(): array
    {
        return collect($this->data())
            ->except(['credit_report', 'credit_score'])
            ->reject(fn ($value) => $this->isEmpty($value))
            ->map(fn ($value, $key) => in_array(strtolower((string) $key), self::SENSITIVE_DATA_KEYS, true) && is_scalar($value)
                ? $this->maskTail((string) $value)
                : $value)
            ->all();
    }

    /**
     * CRIF's object-or-list-or-"" block as a list of arrays.
     *
     * @return list<array<string, mixed>>
     */
    private function items(mixed $node): array
    {
        if (! is_array($node) || $node === []) {
            return [];
        }

        $list = array_is_list($node) ? $node : [$node];

        return array_values(array_filter($list, fn ($item) => is_array($item) && $item !== []));
    }

    /**
     * @param  array<string, mixed>  $parent
     * @return array<string, mixed>
     */
    private function node(array $parent, string $key): array
    {
        $node = $parent[$key] ?? null;

        if (is_array($node) && array_is_list($node)) {
            $node = collect($node)->first(fn ($item) => is_array($item));
        }

        return is_array($node) ? $node : [];
    }

    private function text(mixed $value): ?string
    {
        if (is_bool($value) || ! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * "2,68,889" → "₹2,68,889"; "4,554/Monthly" → "₹4,554/Monthly". A value that
     * is not an amount is returned as sent rather than dropped.
     */
    private function rupees(mixed $value): ?string
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        return preg_match('/^-?[\d,]+(\.\d+)?(\/.*)?$/', $text) === 1 ? '₹'.$text : $text;
    }

    private function amount(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $number = preg_replace('/[₹,\s]/u', '', explode('/', $value)[0]) ?? '';

        return is_numeric($number) ? (float) $number : null;
    }

    /**
     * @param  list<array{fields: array<string, string|null>}>  $accounts
     */
    private function sumAccountAmounts(array $accounts, string $label): ?string
    {
        $amounts = collect($accounts)
            ->map(fn (array $account) => $this->amount($account['fields'][$label] ?? null))
            ->reject(fn (?float $amount) => $amount === null);

        return $amounts->isEmpty() ? null : '₹'.$this->indianGrouping((int) round($amounts->sum()));
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    private function countAccounts(array $accounts, callable $matches): ?string
    {
        return $accounts === [] ? null : (string) collect($accounts)->filter($matches)->count();
    }

    private function yearsMonths(mixed $years, mixed $months): ?string
    {
        $years = $this->text($years);
        $months = $this->text($months);

        if ($years === null && $months === null) {
            return null;
        }

        return trim(($years !== null ? "{$years} yrs " : '').($months !== null ? "{$months} mths" : ''));
    }

    /**
     * "Oct:2025,000/STD|Sep:2025,030/SMA|" → month rows. The value before the
     * slash is days past due ("XXX" = not reported) and after it the asset
     * class; a month counts as late when DPD > 0 or the class is SMA/SUB/DBT/LSS.
     *
     * @return list<array{month: string, value: string, late: bool}>
     */
    private function paymentHistory(mixed $history): array
    {
        $text = $this->text($history);

        if ($text === null) {
            return [];
        }

        return collect(explode('|', $text))
            ->map(fn (string $entry) => trim($entry))
            ->filter()
            ->map(function (string $entry): array {
                [$month, $value] = array_pad(explode(',', $entry, 2), 2, '');
                [$dpd, $class] = array_pad(explode('/', $value, 2), 2, '');

                $late = (ctype_digit($dpd) && (int) $dpd > 0)
                    || in_array(strtoupper(trim($class)), ['SMA', 'SUB', 'DBT', 'LSS'], true);

                return ['month' => trim($month), 'value' => trim($value), 'late' => $late];
            })
            ->values()
            ->all();
    }

    private function maskTail(string $value): string
    {
        $length = mb_strlen($value);

        return $length <= 4 ? str_repeat('X', $length) : str_repeat('X', $length - 4).mb_substr($value, -4);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'd-m-Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function indianGrouping(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $digits = (string) abs($amount);

        if (strlen($digits) <= 3) {
            return $sign.$digits;
        }

        $lastThree = substr($digits, -3);
        $rest = substr($digits, 0, -3);

        return $sign.preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;
    }
}
