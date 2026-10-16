<?php

namespace App\Modules\Cibil\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * A read-only view over one stored Surepass CIBIL (credit-report-cibil)
 * response, for the report screen, the API, the public score page and the
 * indexed columns.
 *
 * CIBIL's report arrives as data.credit_report — a LIST of TransUnion records,
 * each with control_number, names, ids, telephones, emails, employment, scores,
 * addresses, accounts, enquiries and response.consumerSummaryresp
 * (accountSummary, inquirySummary). Lists are read across every record (a
 * report may carry more than one), the summary from the first record that has
 * one. Any block may be missing, null, empty or a single object instead of a
 * list; accessors return null for what is absent (never an invented default),
 * and fields without a dedicated layout come back through otherSections() /
 * each account's extra fields, with identifiers masked. The stored response
 * is never changed.
 */
final class CibilReportDocument
{
    /** Shown masked to the last four characters; the raw JSON (ViewRaw) keeps them whole. */
    private const SENSITIVE_KEYS = [
        'pan', 'idnumber', 'id_number', 'idvalue', 'telephonenumber', 'telephone_number', 'mobile', 'phonenumber',
        'accountnumber', 'account_number', 'aadhaar', 'uid', 'passportnumber', 'voterid',
    ];

    /** TransUnion ID types (ids[].idType). */
    private const ID_TYPES = [
        '01' => 'PAN', '02' => 'Passport', '03' => 'Voter ID', '04' => 'Driving licence',
        '05' => 'Ration card', '06' => 'Universal ID (Aadhaar)', '07' => 'Additional ID 1', '08' => 'Additional ID 2',
    ];

    /** TransUnion telephone types (telephones[].telephoneType). */
    private const TELEPHONE_TYPES = ['00' => 'Not classified', '01' => 'Mobile', '02' => 'Home', '03' => 'Office'];

    /** TransUnion address categories (addresses[].addressCategory). */
    private const ADDRESS_CATEGORIES = ['01' => 'Permanent', '02' => 'Residence', '03' => 'Office', '04' => 'Not categorised'];

    /** TransUnion ownership indicators (accounts[].ownershipIndicator). */
    private const OWNERSHIP = ['1' => 'Individual', '2' => 'Authorised user', '3' => 'Guarantor', '4' => 'Joint'];

    /**
     * Account-type codes (shared by the Indian bureaus); an unknown code is
     * shown as sent, and a known one always with its code.
     */
    private const ACCOUNT_TYPES = [
        '1' => 'Auto loan', '2' => 'Housing loan', '3' => 'Property loan', '4' => 'Loan against shares / securities',
        '5' => 'Personal loan', '6' => 'Consumer loan', '7' => 'Gold loan', '8' => 'Education loan',
        '9' => 'Loan to professional', '10' => 'Credit card', '11' => 'Leasing', '12' => 'Overdraft',
        '13' => 'Two-wheeler loan', '14' => 'Non-funded credit facility', '15' => 'Loan against bank deposits',
        '16' => 'Fleet card', '17' => 'Commercial vehicle loan', '31' => 'Secured credit card', '32' => 'Used car loan',
        '33' => 'Construction equipment loan', '34' => 'Tractor loan', '35' => 'Corporate credit card',
        '36' => 'Kisan credit card', '37' => 'Loan on credit card', '51' => 'Business loan – general',
        '61' => 'Business loan – unsecured',
    ];

    /** Account types that are personal credit cards (credit card, secured credit card). */
    private const CARD_TYPES = ['10', '31'];

    /** suitFiledWilfulDefault codes: suit filed, wilful default, or both. */
    private const SUIT_FILED_CODES = ['01', '02', '03'];

    private const ACCOUNT_FIELDS = [
        'Lender' => ['memberShortName', 'memberName'],
        'Account type' => ['accountType'],
        'Account number' => ['accountNumber'],
        'Ownership' => ['ownershipIndicator'],
        'Sanctioned / high credit' => ['highCreditAmount', 'sanctionAmount'],
        'Credit limit' => ['creditLimit'],
        'Cash limit' => ['cashLimit'],
        'Current balance' => ['currentBalance'],
        'Amount overdue' => ['amountOverdue'],
        'EMI' => ['emiAmount'],
        'Interest rate' => ['interestRate', 'rateOfInterest'],
        'Repayment tenure' => ['paymentTenure', 'repaymentTenure'],
        'Opened on' => ['dateOpened'],
        'Last payment' => ['lastPaymentDate'],
        'Closed on' => ['dateClosed'],
        'Date reported' => ['dateReported'],
        'Written-off amount' => ['woAmountTotal', 'writtenOffAmountTotal'],
        'Settlement amount' => ['settlementAmount'],
        'Credit facility status' => ['creditFacilityStatus'],
        'Suit filed / wilful default' => ['suitFiledWilfulDefault', 'suitFiledWillfulDefault'],
        'Collateral value' => ['collateralValue', 'valueOfCollateral'],
        'Collateral type' => ['collateralType', 'typeOfCollateral'],
    ];

    private const AMOUNT_LABELS = [
        'Sanctioned / high credit', 'Credit limit', 'Cash limit', 'Current balance', 'Amount overdue', 'EMI',
        'Written-off amount', 'Settlement amount', 'Collateral value',
    ];

    private const DATE_LABELS = ['Opened on', 'Last payment', 'Closed on', 'Date reported'];

    /** Record keys with a dedicated layout; everything else is shown as-is. */
    private const HANDLED_KEYS = [
        'control_number', 'names', 'ids', 'telephones', 'emails', 'employment', 'scores', 'addresses', 'accounts', 'enquiries', 'response',
    ];

    /** A Base64 PDF larger than this is refused rather than held in memory. */
    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

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
     * Every TransUnion record in data.credit_report.
     *
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        return $this->items($this->data()['credit_report'] ?? null);
    }

    public function rawScore(): ?string
    {
        return $this->text($this->data()['credit_score'] ?? null)
            ?? collect($this->scores())->pluck('score')->first(fn (?string $score) => $score !== null);
    }

    /**
     * CIBIL scores run from 300 to 900. Anything else — "-1" or "000-1" for no
     * history, "1"–"5" for too little history to score, blank or text — is not
     * a score and is stored as null, never coerced. Zero padding ("00750") is
     * accepted.
     */
    public function score(): ?int
    {
        $raw = $this->rawScore();

        if ($raw === null || preg_match('/^\d{1,5}$/', $raw) !== 1) {
            return null;
        }

        $score = (int) $raw;

        return $score >= 300 && $score <= 900 ? $score : null;
    }

    /**
     * The score blocks across all records (scoreName, version, date, value, reason codes).
     *
     * @return list<array{name: ?string, card: ?string, version: ?string, date: ?string, score: ?string, reasons: list<string>}>
     */
    public function scores(): array
    {
        return collect($this->records())
            ->flatMap(fn (array $record) => $this->items($record['scores'] ?? null))
            ->map(fn (array $score): array => [
                'name' => $this->text($score['scoreName'] ?? null),
                'card' => $this->text($score['scoreCardName'] ?? null),
                'version' => $this->text($score['scoreCardVersion'] ?? null),
                'date' => $this->displayDate($this->text($score['scoreDate'] ?? null)),
                'score' => $this->text($score['score'] ?? null),
                'reasons' => collect($score['reasonCodes'] ?? [])
                    ->map(fn ($reason) => is_array($reason) ? $this->text($reason['reasonCodeValue'] ?? $reason['reasonCodeName'] ?? null) : $this->text($reason))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{control_number: ?string, control_numbers: list<string>, records: int, score_date: ?string}
     */
    public function header(): array
    {
        $controlNumbers = collect($this->records())
            ->map(fn (array $record) => $this->text($record['control_number'] ?? $record['controlNumber'] ?? null))
            ->filter()
            ->values()
            ->all();
        $firstScore = $this->items($this->records()[0]['scores'] ?? null)[0] ?? [];

        return [
            'control_number' => $controlNumbers[0] ?? null,
            'control_numbers' => $controlNumbers,
            'records' => count($this->records()),
            'score_date' => $this->text($firstScore['scoreDate'] ?? null),
        ];
    }

    public function clientId(): ?string
    {
        return $this->text($this->data()['client_id'] ?? null);
    }

    /** The report's date: the first score block's date, when it carries one. */
    public function reportDate(): ?CarbonImmutable
    {
        return $this->date($this->header()['score_date']);
    }

    /** A usable https PDF link Surepass sent (an empty string or null means none). */
    public function pdfLink(): ?string
    {
        $link = $this->text($this->data()['credit_report_link'] ?? null);

        return $link !== null && str_starts_with(strtolower($link), 'https://') && filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
    }

    /** The PDF Surepass sent inline as data.credit_report_base64, when it decodes to a PDF. */
    public function inlinePdf(): ?string
    {
        return self::decodePdf($this->data()['credit_report_base64'] ?? null);
    }

    /**
     * The response as it should be stored: identical, except that an inline
     * Base64 PDF is replaced by a marker — the PDF itself is kept as an
     * encrypted file, not a second time inside the JSON.
     *
     * @return array<string, mixed>
     */
    public function withoutInlinePdf(): array
    {
        $body = $this->body;

        if (is_array($body['data'] ?? null) && filled($body['data']['credit_report_base64'] ?? null)) {
            $body['data']['credit_report_base64'] = '[stored as an encrypted PDF file]';
        }

        return $body;
    }

    /** Base64 (optionally a data: URI) that decodes to a PDF of a sane size, else null. */
    public static function decodePdf(mixed $base64): ?string
    {
        if (! is_string($base64) || trim($base64) === '') {
            return null;
        }

        $base64 = preg_replace('#^data:application/pdf;base64,#i', '', trim($base64)) ?? '';
        $bytes = base64_decode($base64, true);

        return is_string($bytes) && str_starts_with($bytes, '%PDF') && strlen($bytes) <= self::MAX_PDF_BYTES ? $bytes : null;
    }

    /**
     * @return array<string, string>
     */
    public function personalInfo(): array
    {
        $name = $this->items($this->records()[0]['names'] ?? null)[0] ?? [];

        return array_filter([
            'Name' => $this->text($name['name'] ?? null),
            'Date of birth' => $this->displayDate($this->text($name['birthDate'] ?? $name['dateOfBirth'] ?? null)),
            'Gender (as reported)' => $this->text($name['gender'] ?? null),
            'Other names reported' => collect($this->records())
                ->flatMap(fn (array $record) => $this->items($record['names'] ?? null))
                ->map(fn (array $entry) => $this->text($entry['name'] ?? null))
                ->filter()
                ->unique()
                ->slice(1)
                ->implode(', ') ?: null,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Identity documents by type, numbers masked and de-duplicated.
     *
     * @return array<string, list<string>>
     */
    public function identities(): array
    {
        $result = [];

        foreach ($this->across('ids') as $id) {
            $number = $this->text($id['idNumber'] ?? $id['idValue'] ?? null);

            if ($number === null) {
                continue;
            }

            $type = $this->codeLabel($this->text($id['idType'] ?? null), self::ID_TYPES, 'Other ID');
            $result[$type] = array_values(array_unique([...($result[$type] ?? []), $this->maskTail($number)]));
        }

        return $result;
    }

    /**
     * @return list<array{number: string, type: ?string}>
     */
    public function phones(): array
    {
        return collect($this->across('telephones'))
            ->map(fn (array $phone): ?array => ($number = $this->text($phone['telephoneNumber'] ?? $phone['number'] ?? null)) === null ? null : [
                'number' => $number,
                'type' => $this->codeLabel($this->text($phone['telephoneType'] ?? null), self::TELEPHONE_TYPES, null),
            ])
            ->filter()
            ->unique(fn (array $phone) => preg_replace('/\D+/', '', $phone['number']))
            ->map(fn (array $phone): array => [...$phone, 'number' => $this->maskTail($phone['number'])])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        return collect($this->across('emails'))
            ->map(fn (array $email) => $this->text($email['emailID'] ?? $email['emailId'] ?? $email['email'] ?? null))
            ->filter(fn (?string $email) => $email !== null && str_contains($email, '@'))
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values()
            ->all();
    }

    /**
     * @return list<array{address: string, state: ?string, postal: ?string, category: ?string, reported_on: ?string}>
     */
    public function addresses(): array
    {
        return collect($this->across('addresses'))
            ->map(function (array $address): ?array {
                $line = collect(['line1', 'line2', 'line3', 'line4', 'line5'])
                    ->map(fn (string $key) => $this->text($address[$key] ?? null))
                    ->filter()
                    ->implode(', ');

                return $line === '' ? null : [
                    'address' => $line,
                    'state' => $this->text($address['stateCode'] ?? $address['state'] ?? null),
                    'postal' => $this->text($address['pincode'] ?? $address['pinCode'] ?? null),
                    'category' => $this->codeLabel($this->text($address['addressCategory'] ?? null), self::ADDRESS_CATEGORIES, null),
                    'reported_on' => $this->displayDate($this->text($address['dateReported'] ?? null)),
                ];
            })
            ->filter()
            ->unique(fn (array $row) => mb_strtolower($row['address'].'|'.$row['postal']))
            ->values()
            ->all();
    }

    /**
     * Employment blocks (occupation, income…), labelled from their field names.
     *
     * @return list<array<string, string>>
     */
    public function employment(): array
    {
        return collect($this->across('employment'))
            ->map(fn (array $block) => $this->labelled($block, ['income']))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * CIBIL's own account totals (consumerSummaryresp.accountSummary).
     *
     * @return array<string, string>
     */
    public function accountsSummary(): array
    {
        return $this->labelled($this->summaryBlock('accountSummary'), ['highCreditAmount', 'currentBalance', 'overdueBalance']);
    }

    /**
     * @return array<string, string>
     */
    public function enquirySummary(): array
    {
        return $this->labelled($this->summaryBlock('inquirySummary'));
    }

    /**
     * Every reported account across the records: recognised fields by label
     * (null when absent), the month-by-month payment history, and every other
     * field CIBIL sent, identifiers masked.
     *
     * @return list<array{fields: array<string, string|null>, history: list<array{month: string, status: string, late: bool}>, extra: array<string, mixed>}>
     */
    public function accounts(): array
    {
        return collect($this->across('accounts'))
            ->map(function (array $account): array {
                $fields = [];
                $consumed = ['paymentHistory', 'paymentStartDate', 'paymentEndDate', 'index'];

                foreach (self::ACCOUNT_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $account));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($account[$key]) : null;

                    $fields[$label] = match (true) {
                        $value === null => null,
                        $label === 'Account number' => $this->maskTail($value),
                        $label === 'Account type' => $this->accountType($value),
                        $label === 'Ownership' => $this->codeLabel($value, self::OWNERSHIP, null) ?? $value,
                        in_array($label, self::AMOUNT_LABELS, true) => $this->rupees($value),
                        in_array($label, self::DATE_LABELS, true) => $this->displayDate($value),
                        default => $value,
                    };
                }

                $fields = ['Open / closed' => $this->text($account['dateClosed'] ?? null) !== null ? 'Closed' : 'Open', ...$fields];

                return [
                    'fields' => $fields,
                    'history' => $this->history($account),
                    'extra' => $this->masked(collect($account)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    public function enquiries(): array
    {
        return collect($this->across('enquiries'))
            ->map(fn (array $enquiry): array => [
                'fields' => [
                    'Lender' => $this->text($enquiry['memberShortName'] ?? $enquiry['memberName'] ?? null),
                    'Date' => $this->displayDate($this->text($enquiry['enquiryDate'] ?? null)),
                    'Purpose code' => $this->text($enquiry['enquiryPurpose'] ?? null),
                    'Amount' => $this->rupees($this->text($enquiry['enquiryAmount'] ?? null)),
                ],
                'extra' => $this->masked(collect($enquiry)->except(['memberShortName', 'memberName', 'enquiryDate', 'enquiryPurpose', 'enquiryAmount', 'index'])->reject(fn ($value) => $this->isEmpty($value))->all()),
            ])
            ->values()
            ->all();
    }

    /**
     * Record keys without a dedicated layout (and any response section beside
     * consumerSummaryresp), per record, identifiers masked.
     *
     * @return array<string, mixed>
     */
    public function otherSections(): array
    {
        $sections = [];

        foreach ($this->records() as $index => $record) {
            $response = is_array($record['response'] ?? null) ? $record['response'] : [];
            $other = [
                ...collect($record)->except(self::HANDLED_KEYS)->all(),
                ...collect($response)->except(['consumerSummaryresp'])->all(),
            ];
            $other = collect($other)->reject(fn ($value) => $this->isEmpty($value))->all();

            if ($other !== []) {
                $sections['Record '.($index + 1)] = $this->masked($other);
            }
        }

        return $sections;
    }

    /**
     * Surepass envelope fields beside the report (client_id, echoed name…),
     * identifiers masked; never the temporary PDF link or the inline PDF.
     *
     * @return array<string, mixed>
     */
    public function providerFields(): array
    {
        return $this->masked(collect($this->data())
            ->except(['credit_report', 'credit_score', 'credit_report_link', 'credit_report_base64'])
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
        $accounts = $this->summaryBlock('accountSummary');
        $enquiries = $this->summaryBlock('inquirySummary');

        return [
            'score' => $this->score(),
            'control_number' => $this->header()['control_number'],
            'records' => count($this->records()),
            'accounts_total' => $this->text($accounts['totalAccounts'] ?? null) ?? ($this->across('accounts') === [] ? null : (string) count($this->across('accounts'))),
            'accounts_overdue' => $this->text($accounts['overdueAccounts'] ?? null),
            'accounts_zero_balance' => $this->text($accounts['zeroBalanceAccounts'] ?? null),
            'total_balance' => $this->text($accounts['currentBalance'] ?? null),
            'total_overdue' => $this->text($accounts['overdueBalance'] ?? null),
            'total_high_credit' => $this->text($accounts['highCreditAmount'] ?? null),
            'enquiries_total' => $this->text($enquiries['totalInquiry'] ?? null),
            'enquiries_past_30_days' => $this->text($enquiries['inquiryPast30Days'] ?? null),
            'enquiries_past_12_months' => $this->text($enquiries['inquiryPast12Months'] ?? null),
        ];
    }

    /**
     * The headline figures behind the score, for the public result page's
     * report highlights and advice — the same keys every bureau document
     * gives: counts and rupee totals only, no lender, account number or
     * identity data. CIBIL's own totals come first; the rest are counted from
     * the account rows. Each is null when the report does not say.
     *
     * @return array{accounts_total: ?int, accounts_active: ?int, accounts_overdue: ?int, overdue_amount: ?int, accounts_written_off: ?int, late_payments_last_12_months: ?int, card_limit: ?int, card_balance: ?int, enquiries_last_6_months: ?int, credit_age_months: ?int, outstanding_balance: ?int}
     */
    public function insights(): array
    {
        $summary = $this->summaryBlock('accountSummary');
        $asOf = $this->reportDate() ?? CarbonImmutable::now();
        $accounts = $this->across('accounts');
        $open = array_values(array_filter($accounts, fn (array $account) => $this->text($account['dateClosed'] ?? null) === null));
        $cards = array_values(array_filter($open, fn (array $account) => in_array(ltrim((string) $this->text($account['accountType'] ?? null), '0'), self::CARD_TYPES, true)));
        $since = $asOf->startOfMonth()->subMonths(11);
        $histories = collect($accounts)->map(fn (array $account) => $this->history($account))->reject(fn (array $history) => $history === []);
        $enquiryDates = collect($this->across('enquiries'))->map(fn (array $enquiry) => $this->date($this->text($enquiry['enquiryDate'] ?? null)))->filter();
        $openedDates = collect($accounts)->map(fn (array $account) => $this->date($this->text($account['dateOpened'] ?? null)))->filter();

        return [
            'accounts_total' => $this->whole($summary['totalAccounts'] ?? null) ?? ($accounts === [] ? null : count($accounts)),
            'accounts_active' => $accounts === [] ? null : count($open),
            'accounts_overdue' => $this->whole($summary['overdueAccounts'] ?? null)
                ?? ($accounts === [] ? null : count(array_filter($accounts, fn (array $account) => ($this->whole($account['amountOverdue'] ?? null) ?? 0) > 0))),
            'overdue_amount' => $this->whole($summary['overdueBalance'] ?? null) ?? $this->total($accounts, ['amountOverdue']),
            'accounts_written_off' => $accounts === [] ? null : count(array_filter($accounts, fn (array $account) => $this->isWrittenOff($account))),
            'late_payments_last_12_months' => $histories->isEmpty() ? null : $histories->flatten(1)
                ->filter(fn (array $month) => $month['late'] && ($this->monthOf($month['month'])?->gte($since) ?? false))
                ->count(),
            'card_limit' => $this->total($cards, ['creditLimit', 'highCreditAmount']),
            'card_balance' => $this->total($cards, ['currentBalance']),
            'enquiries_last_6_months' => $enquiryDates->isEmpty() ? null : $enquiryDates->filter(fn (CarbonImmutable $date) => $date->gte($asOf->subMonths(6)))->count(),
            'credit_age_months' => $openedDates->isEmpty() ? null : (int) $openedDates->min()->diffInMonths($asOf),
            'outstanding_balance' => $this->whole($summary['currentBalance'] ?? null) ?? $this->total($open, ['currentBalance']),
        ];
    }

    /**
     * Every item of $key across all records (each block may be a list, one object or empty).
     *
     * @return list<array<string, mixed>>
     */
    private function across(string $key): array
    {
        return collect($this->records())
            ->flatMap(fn (array $record) => $this->items($record[$key] ?? null))
            ->values()
            ->all();
    }

    /**
     * consumerSummaryresp.{accountSummary|inquirySummary} from the first record that has it.
     *
     * @return array<string, mixed>
     */
    private function summaryBlock(string $block): array
    {
        foreach ($this->records() as $record) {
            $summary = $record['response']['consumerSummaryresp'][$block] ?? null;

            if (is_array($summary) && $summary !== []) {
                return $this->items($summary)[0] ?? [];
            }
        }

        return [];
    }

    /**
     * TransUnion's paymentHistory: three characters per month, most recent
     * first, starting at paymentStartDate — days past due ("000", "030") or an
     * asset class ("STD", "SMA", "SUB", "DBT", "LSS"; "XXX" = not reported).
     * A month is late when days past due > 0 or the class is SMA/SUB/DBT/LSS.
     *
     * @param  array<string, mixed>  $account
     * @return list<array{month: string, status: string, late: bool}>
     */
    private function history(array $account): array
    {
        $history = preg_replace('/\s+/', '', (string) $this->text($account['paymentHistory'] ?? null)) ?? '';

        if ($history === '' || strlen($history) % 3 !== 0) {
            return [];
        }

        $start = $this->date($this->text($account['paymentStartDate'] ?? null));
        $months = [];

        foreach (str_split($history, 3) as $index => $status) {
            $code = strtoupper($status);

            if ($code === 'XXX') {
                continue;
            }

            $months[] = [
                'month' => $start?->startOfMonth()->subMonths($index)->format('m-y') ?? '#'.($index + 1),
                'status' => $code,
                'late' => (ctype_digit($code) && (int) $code > 0) || in_array($code, ['SMA', 'SUB', 'DBT', 'LSS'], true),
            ];
        }

        return $months;
    }

    /**
     * Written off, settled, or a suit filed / wilful default — the marks lenders read as a default.
     *
     * @param  array<string, mixed>  $account
     */
    private function isWrittenOff(array $account): bool
    {
        $suitFiled = str_pad((string) $this->text($account['suitFiledWilfulDefault'] ?? $account['suitFiledWillfulDefault'] ?? null), 2, '0', STR_PAD_LEFT);

        return in_array($suitFiled, self::SUIT_FILED_CODES, true)
            || ($this->whole($account['woAmountTotal'] ?? $account['writtenOffAmountTotal'] ?? null) ?? 0) > 0
            || ($this->whole($account['settlementAmount'] ?? null) ?? 0) > 0;
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

    /** "45210" or "45,210" → 45210; null for anything that is not a number. */
    private function whole(mixed $value): ?int
    {
        $plain = str_replace(',', '', (string) $this->text($value));

        return is_numeric($plain) ? (int) round((float) $plain) : null;
    }

    /** A history month as history() writes it ("09-26") as that month's first day. */
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
     * An object-or-list-or-null block as a list of arrays.
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
     * "Label (code)" from a code table; the code alone when unknown; $fallback when blank.
     *
     * @param  array<string, string>  $labels
     */
    private function codeLabel(?string $code, array $labels, ?string $fallback): ?string
    {
        if ($code === null) {
            return $fallback;
        }

        $label = $labels[$code] ?? $labels[str_pad($code, 2, '0', STR_PAD_LEFT)] ?? $labels[ltrim($code, '0') ?: '0'] ?? null;

        return $label === null ? $code : "{$label} ({$code})";
    }

    private function accountType(string $code): string
    {
        $label = self::ACCOUNT_TYPES[ltrim($code, '0') ?: '0'] ?? null;

        return $label === null ? $code : "{$label} ({$code})";
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
        $whole = ltrim($whole, '0') ?: '0';
        $grouped = strlen($whole) <= 3 ? $whole : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)).','.substr($whole, -3);

        return ($negative ? '-' : '').'₹'.$grouped.($fraction !== null ? '.'.$fraction : '');
    }

    private function maskTail(string $value): string
    {
        $length = mb_strlen($value);

        return $length <= 4 ? str_repeat('X', $length) : str_repeat('X', $length - 4).mb_substr($value, -4);
    }

    /** "totalAccounts" → "Total accounts", "inquiryPast12Months" → "Inquiry past 12 months". */
    private function humanize(string $key): string
    {
        $words = Str::of($key)
            ->replaceMatches('/([a-z])([A-Z])/', '$1 $2')
            ->replaceMatches('/([A-Z]+)([A-Z][a-z])/', '$1 $2')
            ->replaceMatches('/([A-Za-z])(\d)/', '$1 $2')
            ->replaceMatches('/(\d)([A-Za-z])/', '$1 $2')
            ->replace(['_', '-'], ' ')
            ->squish();

        return $words->explode(' ')
            ->map(fn (string $word, int $index) => ctype_upper($word) && strlen($word) > 1 ? $word : ($index === 0 ? ucfirst(strtolower($word)) : strtolower($word)))
            ->implode(' ');
    }

    /** TransUnion's "15012022" (or ISO "2022-01-15") → "15 Jan 2022"; anything else is returned as sent. */
    private function displayDate(?string $value): ?string
    {
        return $value === null ? null : ($this->date($value)?->format('d M Y') ?? $value);
    }

    /**
     * Dates arrive as DDMMYYYY (TransUnion's own format), YYYYMMDD, or ISO /
     * dashed / slashed forms. An eight-digit value is read as DDMMYYYY first
     * and as YYYYMMDD only when that is not a real date.
     */
    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['dmY', 'Ymd', 'Y-m-d', 'd-m-Y', 'd/m/Y', 'Y-m-d\TH:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value && $date->year >= 1900 && $date->year <= 2100) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
