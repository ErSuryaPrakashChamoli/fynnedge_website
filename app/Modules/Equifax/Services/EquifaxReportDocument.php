<?php

namespace App\Modules\Equifax\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * A read-only view over one stored Surepass Equifax (credit-report-v2)
 * response, for the report screen, the API summary and the indexed columns.
 *
 * Equifax's JSON arrives wrapped in Surepass envelopes whose nesting has
 * varied (data.credit_report may be a list, or an object holding a list and
 * its own status fields), so every section is found BY NAME anywhere in the
 * response — InquiryResponseHeader, CCRResponse → CIRReportDataLst →
 * CIRReportData → IDAndContactInfo / RetailAccountDetails / ScoreDetails /
 * Enquiries… — rather than by a fixed path. Blocks may be an object, a list or
 * empty; values may be missing. Accessors return null for what is absent
 * (never an invented default), and whatever is not recognised comes back
 * through otherSections() / extra fields. The stored response is never changed.
 */
final class EquifaxReportDocument
{
    /** Shown masked to the last four characters; the raw JSON (ViewRaw) keeps them whole. */
    private const SENSITIVE_KEYS = [
        'idnumber', 'idvalue', 'id_number', 'number', 'pan', 'panid', 'mobile', 'mobilephone', 'homephone',
        'phone', 'phonenumber', 'aadhaar', 'aadhaarnumber', 'uid', 'voterid', 'passportnumber', 'accountnumber',
    ];

    private const ACCOUNT_FIELDS = [
        'Lender' => ['Institution'],
        'Account type' => ['AccountType'],
        'Account number' => ['AccountNumber'],
        'Status' => ['AccountStatus'],
        'Ownership' => ['OwnershipType'],
        'Sanctioned amount' => ['SanctionAmount'],
        'Credit limit' => ['CreditLimit'],
        'High credit' => ['HighCredit'],
        'Current balance' => ['Balance'],
        'Past due amount' => ['PastDueAmount'],
        'Write-off amount' => ['WriteOffAmount'],
        'Instalment' => ['InstallmentAmount'],
        'Instalment frequency' => ['TermFrequency'],
        'Interest rate' => ['InterestRate'],
        'Repayment tenure' => ['RepaymentTenure'],
        'Opened on' => ['DateOpened'],
        'Date reported' => ['DateReported'],
        'Last payment' => ['LastPaymentDate', 'LastPayment'],
        'Closed on' => ['DateClosed'],
        'Asset classification' => ['AssetClassification'],
        'Suit filed' => ['SuitFiledStatus'],
        'Collateral' => ['CollateralType'],
        'Open' => ['Open'],
    ];

    private const AMOUNT_LABELS = [
        'Sanctioned amount', 'Credit limit', 'High credit', 'Current balance', 'Past due amount', 'Write-off amount', 'Instalment',
    ];

    private const ENQUIRY_FIELDS = [
        'Institution' => ['Institution'],
        'Date' => ['Date'],
        'Time' => ['Time'],
        'Purpose' => ['RequestPurpose'],
        'Amount' => ['Amount'],
    ];

    /** CIRReportData sections with a dedicated layout; everything else is shown as-is. */
    private const HANDLED_SECTIONS = [
        'IDAndContactInfo', 'RetailAccountDetails', 'RetailAccountsSummary', 'ScoreDetails', 'EnquirySummary', 'Enquiries',
    ];

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
     * The first value stored under $key anywhere in the response (depth first).
     */
    public function find(string $key, ?array $within = null): mixed
    {
        $stack = [$within ?? $this->data()];

        while ($stack !== []) {
            $node = array_shift($stack);

            if (! is_array($node)) {
                continue;
            }

            if (array_key_exists($key, $node)) {
                return $node[$key];
            }

            foreach ($node as $child) {
                if (is_array($child)) {
                    $stack[] = $child;
                }
            }
        }

        return null;
    }

    /**
     * The first CIRReportData block — the report proper.
     *
     * @return array<string, mixed>
     */
    public function reportData(): array
    {
        return $this->first($this->find('CIRReportData'));
    }

    public function rawScore(): ?string
    {
        $score = $this->text($this->data()['credit_score'] ?? null);

        if ($score === null) {
            $score = $this->scores()[0]['value'] ?? null;
        }

        return $score;
    }

    /**
     * Equifax scores are positive whole numbers (the Equifax Risk Score is
     * 1–999; most Indian reports fall in 300–900). Blank, zero, negative or
     * non-numeric values (e.g. "-1" for no history) are not a score and are
     * stored as null — never coerced.
     */
    public function score(): ?int
    {
        $raw = $this->rawScore();

        if ($raw === null || preg_match('/^\d{1,3}$/', $raw) !== 1) {
            return null;
        }

        $score = (int) $raw;

        return $score >= 1 ? $score : null;
    }

    /**
     * @return array{order_number: ?string, client_id: ?string, customer_reference: ?string, product_code: ?string, success_code: ?string, date: ?string, time: ?string}
     */
    public function header(): array
    {
        $header = $this->first($this->find('InquiryResponseHeader'));
        $productCode = $header['ProductCode'] ?? null;

        return [
            'order_number' => $this->text($header['ReportOrderNO'] ?? $header['ReportOrderNo'] ?? null),
            'client_id' => $this->text($header['ClientID'] ?? null),
            'customer_reference' => $this->text($header['CustRefField'] ?? null),
            'product_code' => is_array($productCode) ? implode(', ', array_filter(array_map(fn ($code) => $this->text($code), $productCode))) ?: null : $this->text($productCode),
            'success_code' => $this->text($header['SuccessCode'] ?? null),
            'date' => $this->text($header['Date'] ?? null),
            'time' => $this->text($header['Time'] ?? null),
        ];
    }

    public function clientId(): ?string
    {
        return $this->text($this->data()['client_id'] ?? null);
    }

    public function reportDate(): ?CarbonImmutable
    {
        return $this->date($this->header()['date']);
    }

    /** A usable https PDF link Surepass put in the report itself (an empty string means none). */
    public function pdfLink(): ?string
    {
        $link = $this->text($this->data()['credit_report_link'] ?? null) ?? $this->text($this->find('credit_report_link'));

        return $link !== null && str_starts_with(strtolower($link), 'https://') && filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
    }

    /**
     * @return list<array{type: ?string, version: ?string, name: ?string, value: ?string, elements: list<array<string, mixed>>}>
     */
    public function scores(): array
    {
        $details = $this->find('ScoreDetails');

        return collect($this->items($details))
            ->map(fn (array $score): array => [
                'type' => $this->text($score['Type'] ?? null),
                'version' => $this->text($score['Version'] ?? null),
                'name' => $this->text($score['Name'] ?? null),
                'value' => $this->text($score['Value'] ?? null),
                'elements' => collect($this->items($score['ScoringElements'] ?? null))
                    ->map(fn (array $element) => collect($element)->reject(fn ($value) => $this->isEmpty($value))->all())
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    public function personalInfo(): array
    {
        $info = $this->first($this->find('PersonalInfo'));
        $name = is_array($info['Name'] ?? null) ? $info['Name'] : [];
        $age = $info['Age'] ?? null;

        return array_filter([
            'Full name' => $this->text($name['FullName'] ?? null),
            'First name' => $this->text($name['FirstName'] ?? null),
            'Middle name' => $this->text($name['MiddleName'] ?? null),
            'Last name' => $this->text($name['LastName'] ?? null),
            'Date of birth' => $this->text($info['DateOfBirth'] ?? null),
            'Gender' => $this->text($info['Gender'] ?? null),
            'Age' => $this->text(is_array($age) ? ($age['Age'] ?? null) : $age),
            'Total income' => $this->text($info['TotalIncome'] ?? null),
            'Occupation' => $this->text($info['Occupation'] ?? null),
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Identity documents by type (PANId, VoterID, PassportID…), numbers masked.
     *
     * @return array<string, list<array{number: ?string, reported_on: ?string}>>
     */
    public function identities(): array
    {
        $identity = $this->first($this->find('IdentityInfo'));
        $result = [];

        foreach ($identity as $type => $entries) {
            $rows = collect($this->items($entries))
                ->map(fn (array $entry): array => [
                    'number' => ($number = $this->text($entry['IdNumber'] ?? $entry['IDValue'] ?? null)) !== null ? $this->maskTail($number) : null,
                    'reported_on' => $this->text($entry['ReportedDate'] ?? null),
                ])
                ->filter(fn (array $row) => $row['number'] !== null)
                ->values()
                ->all();

            if ($rows !== []) {
                $result[$this->humanize((string) $type)] = $rows;
            }
        }

        return $result;
    }

    /**
     * @return list<array{address: ?string, state: ?string, postal: ?string, type: ?string, reported_on: ?string}>
     */
    public function addresses(): array
    {
        return collect($this->items($this->find('AddressInfo')))
            ->map(fn (array $address): array => [
                'address' => $this->text($address['Address'] ?? null),
                'state' => $this->text($address['State'] ?? null),
                'postal' => $this->text($address['Postal'] ?? null),
                'type' => $this->text($address['Type'] ?? null),
                'reported_on' => $this->text($address['ReportedDate'] ?? null),
            ])
            ->filter(fn (array $row) => $row['address'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, type: ?string, reported_on: ?string}>
     */
    public function phones(): array
    {
        return collect($this->items($this->find('PhoneInfo')))
            ->map(fn (array $phone): ?array => ($number = $this->text($phone['Number'] ?? null)) === null ? null : [
                'number' => $this->maskTail($number),
                'type' => $this->phoneType($this->text($phone['typeCode'] ?? $phone['TypeCode'] ?? null)),
                'reported_on' => $this->text($phone['ReportedDate'] ?? null),
            ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{email: string, reported_on: ?string}>
     */
    public function emails(): array
    {
        return collect($this->items($this->find('EmailAddressInfo')))
            ->map(fn (array $email): ?array => ($address = $this->text($email['EmailAddress'] ?? null)) === null ? null : [
                'email' => $address,
                'reported_on' => $this->text($email['ReportedDate'] ?? null),
            ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Every reported account: recognised fields by label (null when absent),
     * the 48-month history, and every other field Equifax sent.
     *
     * @return list<array{fields: array<string, string|null>, history: list<array{month: string, status: string, late: bool}>, extra: array<string, mixed>}>
     */
    public function accounts(): array
    {
        return collect($this->items($this->find('RetailAccountDetails')))
            ->map(function (array $account): array {
                $fields = [];
                $consumed = ['History48Months', 'seq'];

                foreach (self::ACCOUNT_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $account));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($account[$key]) : null;

                    $fields[$label] = match (true) {
                        $value === null => null,
                        $label === 'Account number' => $this->maskTail($value),
                        in_array($label, self::AMOUNT_LABELS, true) => $this->rupees($value),
                        default => $value,
                    };
                }

                return [
                    'fields' => $fields,
                    'history' => $this->history($account['History48Months'] ?? null),
                    'extra' => collect($account)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Equifax's own account totals, labelled from its field names.
     *
     * @return array<string, string>
     */
    public function accountsSummary(): array
    {
        return $this->labelled($this->first($this->find('RetailAccountsSummary')), ['TotalPastDue', 'SingleHighestCredit', 'SingleHighestSanctionAmount', 'TotalHighCredit', 'AverageOpenBalance', 'SingleHighestBalance', 'TotalBalanceAmount', 'TotalSanctionAmount', 'TotalCreditLimit', 'TotalMonthlyPaymentAmount']);
    }

    /**
     * @return array<string, string>
     */
    public function enquirySummary(): array
    {
        return $this->labelled($this->first($this->find('EnquirySummary')));
    }

    /**
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    public function enquiries(): array
    {
        return collect($this->items($this->find('Enquiries')))
            ->map(function (array $enquiry): array {
                $fields = [];
                $consumed = ['seq'];

                foreach (self::ENQUIRY_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $enquiry));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($enquiry[$key]) : null;
                    $fields[$label] = $label === 'Amount' ? $this->rupees($value) : $value;
                }

                return [
                    'fields' => $fields,
                    'extra' => collect($enquiry)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * CIRReportData sections without a dedicated layout (OtherKeyInd,
     * RecentActivities, anything Equifax adds later), with identifiers masked.
     *
     * @return array<string, mixed>
     */
    public function otherSections(): array
    {
        return $this->masked(collect($this->reportData())
            ->except(self::HANDLED_SECTIONS)
            ->reject(fn ($value) => $this->isEmpty($value))
            ->all());
    }

    /**
     * What Equifax was asked (InquiryRequestInfo), identifiers masked.
     *
     * @return array<string, mixed>
     */
    public function requestInfo(): array
    {
        return $this->masked($this->first($this->find('InquiryRequestInfo')));
    }

    /**
     * Surepass envelope fields beside the report (client_id, echoed name…).
     *
     * @return array<string, mixed>
     */
    public function providerFields(): array
    {
        return $this->masked(collect($this->data())
            ->except(['credit_report', 'credit_score', 'credit_report_link'])
            ->reject(fn ($value) => $this->isEmpty($value))
            ->all());
    }

    /**
     * The derived, structured summary stored beside the original response (in
     * its own encrypted column): headline figures only, each null when the
     * report does not state it.
     *
     * @return array<string, int|string|null>
     */
    public function summary(): array
    {
        $accounts = $this->first($this->find('RetailAccountsSummary'));
        $enquiries = $this->first($this->find('EnquirySummary'));

        return [
            'score' => $this->score(),
            'report_order_number' => $this->header()['order_number'],
            'accounts_total' => $this->text($accounts['NoOfAccounts'] ?? null) ?? ($this->accounts() === [] ? null : (string) count($this->accounts())),
            'accounts_active' => $this->text($accounts['NoOfActiveAccounts'] ?? null),
            'accounts_past_due' => $this->text($accounts['NoOfPastDueAccounts'] ?? null),
            'accounts_written_off' => $this->text($accounts['NoOfWriteOffs'] ?? null),
            'total_balance' => $this->text($accounts['TotalBalanceAmount'] ?? null),
            'total_past_due' => $this->text($accounts['TotalPastDue'] ?? null),
            'total_sanctioned' => $this->text($accounts['TotalSanctionAmount'] ?? null),
            'enquiries_total' => $this->text($enquiries['Total'] ?? null),
            'enquiries_past_12_months' => $this->text($enquiries['Past12Months'] ?? null),
        ];
    }

    /**
     * The headline figures behind the score, for the public result page's
     * report highlights and advice: counts and rupee totals only — no lender,
     * account number or identity data. Equifax's own totals
     * (RetailAccountsSummary, OtherKeyInd) come first; the rest are counted
     * from the account rows. Each is null when the report does not say.
     *
     * @return array{accounts_total: ?int, accounts_active: ?int, accounts_overdue: ?int, overdue_amount: ?int, accounts_written_off: ?int, late_payments_last_12_months: ?int, card_limit: ?int, card_balance: ?int, enquiries_last_6_months: ?int, credit_age_months: ?int, outstanding_balance: ?int}
     */
    public function insights(): array
    {
        $summary = $this->first($this->find('RetailAccountsSummary'));
        $enquirySummary = $this->first($this->find('EnquirySummary'));
        $asOf = $this->reportDate() ?? CarbonImmutable::now();
        $accounts = $this->items($this->find('RetailAccountDetails'));
        $open = array_values(array_filter($accounts, fn (array $account) => $this->isOpen($account)));
        $cards = array_values(array_filter($open, fn (array $account) => str_contains(strtolower((string) $this->text($account['AccountType'] ?? null)), 'credit card')));
        $histories = collect($accounts)->map(fn (array $account) => $this->history($account['History48Months'] ?? null))->reject(fn (array $history) => $history === []);
        $since = $asOf->startOfMonth()->subMonths(11);
        $enquiries = $this->items($this->find('Enquiries'));
        $openedDates = collect($accounts)->map(fn (array $account) => $this->date($this->text($account['DateOpened'] ?? null)))->filter();

        return [
            'accounts_total' => $this->whole($summary['NoOfAccounts'] ?? null) ?? ($accounts === [] ? null : count($accounts)),
            'accounts_active' => $this->whole($summary['NoOfActiveAccounts'] ?? null) ?? ($accounts === [] ? null : count($open)),
            'accounts_overdue' => $this->whole($summary['NoOfPastDueAccounts'] ?? null)
                ?? ($accounts === [] ? null : count(array_filter($accounts, fn (array $account) => ($this->whole($account['PastDueAmount'] ?? null) ?? 0) > 0))),
            'overdue_amount' => $this->whole($summary['TotalPastDue'] ?? null) ?? $this->total($accounts, ['PastDueAmount']),
            'accounts_written_off' => $accounts === []
                ? $this->whole($summary['NoOfWriteOffs'] ?? null)
                : count(array_filter($accounts, fn (array $account) => $this->isWrittenOff($account))),
            'late_payments_last_12_months' => $histories->isEmpty() ? null : $histories->flatten(1)
                ->filter(fn (array $month) => $month['late'] && ($this->monthOf($month['month'])?->gte($since) ?? false))
                ->count(),
            'card_limit' => $this->total($cards, ['CreditLimit', 'HighCredit', 'SanctionAmount']),
            'card_balance' => $this->total($cards, ['Balance']),
            'enquiries_last_6_months' => match (true) {
                $enquiries !== [] => collect($enquiries)
                    ->map(fn (array $enquiry) => $this->date($this->text($enquiry['Date'] ?? null)))
                    ->filter(fn (?CarbonImmutable $date) => $date?->gte($asOf->subMonths(6)) ?? false)
                    ->count(),
                $this->whole($enquirySummary['Total'] ?? null) === 0 => 0,
                default => null,
            },
            'credit_age_months' => $this->whole($this->first($this->find('OtherKeyInd'))['AgeOfOldestTrade'] ?? null)
                ?? ($openedDates->isEmpty() ? null : (int) $openedDates->min()->diffInMonths($asOf)),
            'outstanding_balance' => $this->whole($summary['TotalBalanceAmount'] ?? null) ?? $this->total($open, ['Balance']),
        ];
    }

    /**
     * Equifax's "History48Months": [{key: "09-26", PaymentStatus: "000", …}].
     * A month is late when days past due > 0 or the asset class is SMA/SUB/DBT/LSS.
     *
     * @return list<array{month: string, status: string, late: bool}>
     */
    private function history(mixed $history): array
    {
        return collect($this->items($history))
            ->map(function (array $month): ?array {
                $key = $this->text($month['key'] ?? $month['Key'] ?? null);
                $status = $this->text($month['PaymentStatus'] ?? null) ?? '';
                $asset = strtoupper((string) $this->text($month['AssetClassificationStatus'] ?? null));

                if ($key === null) {
                    return null;
                }

                $late = (ctype_digit($status) && (int) $status > 0) || in_array($asset, ['SMA', 'SUB', 'DBT', 'LSS'], true);

                return ['month' => $key, 'status' => trim($status.($asset !== '' && $asset !== '*' ? '/'.$asset : '')), 'late' => $late];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function isOpen(array $account): bool
    {
        $open = strtolower((string) $this->text($account['Open'] ?? null));

        return $open !== '' ? $open === 'yes' : $this->text($account['DateClosed'] ?? null) === null;
    }

    /**
     * Written off, settled or taken to court — the marks lenders read as a default.
     *
     * @param  array<string, mixed>  $account
     */
    private function isWrittenOff(array $account): bool
    {
        $status = strtolower(($this->text($account['AccountStatus'] ?? null) ?? '').' '.($this->text($account['SuitFiledStatus'] ?? null) ?? ''));

        return preg_match('/written|write-off|settled|suit filed|wilful|willful/', $status) === 1
            || ($this->whole($account['WriteOffAmount'] ?? null) ?? 0) > 0;
    }

    /**
     * The sum of the first of $keys each row carries; null when no row carries any.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $keys
     */
    private function total(array $rows, array $keys): ?int
    {
        $amounts = collect($rows)
            ->map(fn (array $row) => collect($keys)->map(fn (string $key) => $this->whole($row[$key] ?? null))->first(fn (?int $amount) => $amount !== null))
            ->reject(fn (?int $amount) => $amount === null);

        return $amounts->isEmpty() ? null : (int) $amounts->sum();
    }

    /** "268889" or "2,68,889" → 268889; null for anything that is not a number. */
    private function whole(mixed $value): ?int
    {
        $plain = str_replace(',', '', (string) $this->text($value));

        return is_numeric($plain) ? (int) round((float) $plain) : null;
    }

    /** A 48-month history key ("09-26") as that month's first day. */
    private function monthOf(string $month): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!m-y', $month);

            return $date === false ? null : $date;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $amountKeys
     * @return array<string, string>
     */
    private function labelled(array $node, array $amountKeys = []): array
    {
        $result = [];

        foreach ($node as $key => $value) {
            $text = $this->text($value);

            if ($text !== null) {
                $result[$this->humanize((string) $key)] = in_array($key, $amountKeys, true) ? (string) $this->rupees($text) : $text;
            }
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function masked(array $node): array
    {
        $result = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->masked($value);
            } elseif (is_scalar($value) && is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true) && trim((string) $value) !== '') {
                $result[$key] = $this->maskTail((string) $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * An object-or-list-or-"" block as a list of arrays.
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
     * @return array<string, mixed>
     */
    private function first(mixed $node): array
    {
        return $this->items($node)[0] ?? [];
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

    /** "268889" or "2,68,889" → "₹2,68,889"; anything that is not an amount is returned as sent. */
    private function rupees(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = str_replace(',', '', $value);

        if (! is_numeric($plain)) {
            return $value;
        }

        if (str_contains($value, ',')) {
            return '₹'.$value;
        }

        $negative = str_starts_with($plain, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($plain, '-'), 2), 2, null);
        $grouped = strlen($whole) <= 3 ? $whole : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)).','.substr($whole, -3);

        return ($negative ? '-' : '').'₹'.$grouped.($fraction !== null ? '.'.$fraction : '');
    }

    private function maskTail(string $value): string
    {
        $length = mb_strlen($value);

        return $length <= 4 ? str_repeat('X', $length) : str_repeat('X', $length - 4).mb_substr($value, -4);
    }

    private function phoneType(?string $code): ?string
    {
        return match (strtoupper((string) $code)) {
            'M' => 'Mobile',
            'H' => 'Home',
            'T' => 'Office',
            '' => null,
            default => $code,
        };
    }

    /** "NoOfActiveAccounts" → "No of active accounts", "PANId" → "PAN id", "Past12Months" → "Past 12 months". */
    private function humanize(string $key): string
    {
        $words = Str::of($key)
            ->replaceMatches('/([a-z])([A-Z])/', '$1 $2')
            ->replaceMatches('/([A-Z]+)([A-Z][a-z])/', '$1 $2')
            ->replaceMatches('/([A-Za-z])(\d)/', '$1 $2')
            ->replaceMatches('/(\d)([A-Za-z])/', '$1 $2')
            ->replace(['_', '-'], ' ')
            ->squish();

        $text = $words->explode(' ')
            ->map(fn (string $word, int $index) => ctype_upper($word) && strlen($word) > 1 ? $word : ($index === 0 ? ucfirst(strtolower($word)) : strtolower($word)))
            ->implode(' ');

        return $text;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-M-Y', 'Y-m-d\TH:i:s'] as $format) {
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
}
