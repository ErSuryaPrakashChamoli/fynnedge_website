<?php

namespace App\Modules\Experian\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * A read-only view over one stored Surepass Experian (credit-report-experian)
 * response, for the report screen, the API summary and the indexed columns.
 *
 * Experian's report is its "INProfileResponse": CreditProfileHeader,
 * Current_Application, CAIS_Account (CAIS_Summary + CAIS_Account_DETAILS with
 * each account's monthly history and holder details), Match_result,
 * TotalCAPS_Summary, CAPS / NonCreditCAPS (enquiries) and SCORE. Converted
 * from XML, a block may be an object, a list or "" — so every section is found
 * BY NAME anywhere in the response rather than by a fixed path, and accessors
 * return null for what is absent (never an invented default). Fields without a
 * dedicated layout come back through otherSections() / each account's extra
 * fields, with identifiers masked. The stored response is never changed.
 */
final class ExperianReportDocument
{
    /** Shown masked to the last four characters; the raw JSON (ViewRaw) keeps them whole. */
    private const SENSITIVE_KEYS = [
        'pan', 'incometaxpan', 'income_tax_pan', 'passport_number', 'passportnumber', 'voter_s_identity_card',
        'voter_id_number', 'driver_license_number', 'ration_card_number', 'universal_id_number',
        'mobile', 'mobilephonenumber', 'mobile_telephone_number', 'telephone_number', 'telephone_number_applicant_1st',
        'telephone_extension', 'account_number',
    ];

    /** Identity documents as Experian names them, in the applicant and account-holder blocks. */
    private const IDENTITY_KEYS = [
        'PAN' => ['IncomeTaxPan', 'Income_TAX_PAN'],
        'Passport' => ['Passport_number', 'Passport_Number'],
        'Voter ID' => ['Voter_s_Identity_Card', 'Voter_ID_Number'],
        'Driving licence' => ['Driver_License_Number'],
        'Ration card' => ['Ration_Card_Number'],
        'Universal ID' => ['Universal_ID_Number'],
    ];

    private const ACCOUNT_FIELDS = [
        'Lender' => ['Subscriber_Name'],
        'Account type' => ['Account_Type'],
        'Account number' => ['Account_Number'],
        'Portfolio type' => ['Portfolio_Type'],
        'Account status code' => ['Account_Status'],
        'Sanctioned / original amount' => ['Highest_Credit_or_Original_Loan_Amount'],
        'Credit limit' => ['Credit_Limit_Amount'],
        'Current balance' => ['Current_Balance'],
        'Amount past due' => ['Amount_Past_Due'],
        'Instalment' => ['Scheduled_Monthly_Payment_Amount'],
        'Interest rate' => ['Rate_of_Interest'],
        'Repayment tenure' => ['Repayment_Tenure'],
        'Opened on' => ['Open_Date'],
        'Date reported' => ['Date_Reported'],
        'Last payment' => ['Date_of_Last_Payment'],
        'Closed on' => ['Date_Closed'],
        'Written-off amount' => ['Written_Off_Amt_Total'],
        'Settlement amount' => ['Settlement_Amount'],
        'Written-off / settled status' => ['Written_off_Settled_Status'],
        'Suit filed / wilful default' => ['SuitFiled_WilfulDefault'],
        'Collateral type' => ['Type_of_Collateral'],
        'Collateral value' => ['Value_of_Collateral'],
    ];

    private const AMOUNT_LABELS = [
        'Sanctioned / original amount', 'Credit limit', 'Current balance', 'Amount past due', 'Instalment',
        'Written-off amount', 'Settlement amount', 'Collateral value',
    ];

    private const DATE_LABELS = ['Opened on', 'Date reported', 'Last payment', 'Closed on'];

    private const ENQUIRY_FIELDS = [
        'Lender' => ['Subscriber_Name'],
        'Date' => ['Date_of_Request'],
        'Reason code' => ['Enquiry_Reason'],
        'Purpose code' => ['Finance_Purpose'],
        'Amount' => ['Amount_Financed'],
        'Duration' => ['Duration_Of_Agreement'],
    ];

    /**
     * Experian's account-type codes (shared with the other Indian bureaus); an
     * unknown code is shown as sent, and a known one always with its code.
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

    /**
     * Written_off_Settled_Status codes that mark a default: written off,
     * settled, post-write-off settled, and their sold / purchased variants.
     * (Restructured and "clear existing status" codes are left out.)
     */
    private const WRITTEN_OFF_CODES = ['02', '03', '04', '06', '08', '09'];

    /** SuitFiled_WilfulDefault codes: suit filed, wilful default, or both. */
    private const SUIT_FILED_CODES = ['01', '02', '03'];

    /** Report sections with a dedicated layout; everything else is shown as-is. */
    private const HANDLED_SECTIONS = [
        'Header', 'UserMessage', 'CreditProfileHeader', 'Current_Application', 'CAIS_Account', 'Match_result',
        'TotalCAPS_Summary', 'CAPS', 'NonCreditCAPS', 'SCORE',
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
     * The first value stored under $key anywhere in the response (breadth first).
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
     * The report proper: data.credit_report, unwrapped from INProfileResponse
     * when Surepass passes Experian's envelope through.
     *
     * @return array<string, mixed>
     */
    public function reportData(): array
    {
        $wrapped = $this->find('INProfileResponse');

        if (is_array($wrapped)) {
            return $this->first($wrapped);
        }

        return $this->first($this->data()['credit_report'] ?? null);
    }

    public function rawScore(): ?string
    {
        return $this->text($this->data()['credit_score'] ?? null)
            ?? $this->text($this->first($this->find('SCORE'))['BureauScore'] ?? null);
    }

    /**
     * Experian scores are whole numbers (300–900 in India). Blank, zero,
     * negative or non-numeric values (e.g. "-1" for no history) are not a score
     * and are stored as null — never coerced.
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
     * @return array{value: ?string, confidence: ?string}
     */
    public function scoreDetails(): array
    {
        $score = $this->first($this->find('SCORE'));

        return [
            'value' => $this->text($score['BureauScore'] ?? null),
            'confidence' => $this->text($score['BureauScoreConfidLevel'] ?? null),
        ];
    }

    /**
     * @return array{report_number: ?string, report_date: ?string, report_time: ?string, version: ?string, subscriber: ?string, enquiry_username: ?string, system_code: ?string, message: ?string}
     */
    public function header(): array
    {
        $profile = $this->first($this->find('CreditProfileHeader'));
        $header = $this->first($this->find('Header'));
        $message = $this->first($this->find('UserMessage'));

        return [
            'report_number' => $this->text($profile['ReportNumber'] ?? null),
            'report_date' => $this->text($profile['ReportDate'] ?? $header['ReportDate'] ?? null),
            'report_time' => $this->text($profile['ReportTime'] ?? $header['ReportTime'] ?? null),
            'version' => $this->text($profile['Version'] ?? null),
            'subscriber' => $this->text($profile['Subscriber_Name'] ?? $profile['Subscriber'] ?? null),
            'enquiry_username' => $this->text($profile['Enquiry_Username'] ?? null),
            'system_code' => $this->text($header['SystemCode'] ?? null),
            'message' => $this->text($message['UserMessageText'] ?? $header['MessageText'] ?? null),
        ];
    }

    public function clientId(): ?string
    {
        return $this->text($this->data()['client_id'] ?? null);
    }

    public function reportDate(): ?CarbonImmutable
    {
        return $this->date($this->header()['report_date']);
    }

    /** A usable https PDF link Surepass sent (an empty string means none). */
    public function pdfLink(): ?string
    {
        $link = $this->text($this->data()['credit_report_link'] ?? null) ?? $this->text($this->find('credit_report_link'));

        return $link !== null && str_starts_with(strtolower($link), 'https://') && filter_var($link, FILTER_VALIDATE_URL) ? $link : null;
    }

    /**
     * Whether Experian matched the enquiry exactly (Match_result.Exact_match).
     */
    public function exactMatch(): ?string
    {
        return $this->text($this->first($this->find('Match_result'))['Exact_match'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    public function personalInfo(): array
    {
        $applicant = $this->applicant();

        return array_filter([
            'First name' => $this->text($applicant['First_Name'] ?? null),
            'Middle name' => $this->text(implode(' ', array_filter(array_map(fn ($key) => $this->text($applicant[$key] ?? null), ['Middle_Name1', 'Middle_Name2', 'Middle_Name3'])))),
            'Last name' => $this->text($applicant['Last_Name'] ?? null),
            'Date of birth' => $this->displayDate($this->text($applicant['Date_Of_Birth_Applicant'] ?? null)),
            'Gender' => $this->gender($this->text($applicant['Gender_Code'] ?? null)),
        ], fn (?string $value) => $value !== null);
    }

    /**
     * The application Experian scored (Current_Application_Details), without
     * the applicant's identifiers.
     *
     * @return array<string, string>
     */
    public function currentApplication(): array
    {
        $details = $this->first($this->find('Current_Application_Details'));

        return array_filter([
            'Enquiry reason code' => $this->text($details['Enquiry_Reason'] ?? null),
            'Finance purpose code' => $this->text($details['Finance_Purpose'] ?? null),
            'Amount financed' => $this->rupees($this->text($details['Amount_Financed'] ?? null)),
            'Duration of agreement' => $this->text($details['Duration_Of_Agreement'] ?? null),
        ], fn (?string $value) => $value !== null);
    }

    /**
     * Identity documents found in the applicant and account-holder blocks,
     * numbers masked and de-duplicated.
     *
     * @return array<string, list<string>>
     */
    public function identities(): array
    {
        $blocks = [$this->applicant(), ...$this->holderBlocks('CAIS_Holder_Details'), ...$this->holderBlocks('CAIS_Holder_ID_Details')];
        $result = [];

        foreach (self::IDENTITY_KEYS as $type => $keys) {
            $numbers = collect($blocks)
                ->flatMap(fn (array $block) => collect($keys)->map(fn (string $key) => $this->text($block[$key] ?? null)))
                ->filter()
                ->map(fn (string $number) => $this->maskTail($number))
                ->unique()
                ->values()
                ->all();

            if ($numbers !== []) {
                $result[$type] = $numbers;
            }
        }

        return $result;
    }

    /**
     * Addresses from the application and every account, de-duplicated.
     *
     * @return list<array{address: string, state: ?string, postal: ?string, source: string}>
     */
    public function addresses(): array
    {
        $rows = [];

        foreach ($this->items($this->find('Current_Applicant_Address_Details')) as $address) {
            $rows[] = $this->address($address, 'Application');
        }

        foreach ($this->accountItems() as $account) {
            foreach ($this->items($account['CAIS_Holder_Address_Details'] ?? null) as $address) {
                $rows[] = $this->address($address, $this->text($account['Subscriber_Name'] ?? null) ?? 'Account');
            }
        }

        return collect($rows)
            ->filter(fn (?array $row) => $row !== null)
            ->unique(fn (array $row) => mb_strtolower($row['address'].'|'.$row['postal']))
            ->values()
            ->all();
    }

    /**
     * @return list<array{number: string, type: string}>
     */
    public function phones(): array
    {
        $found = [];
        $applicant = $this->applicant();

        foreach (['MobilePhoneNumber' => 'Mobile', 'Telephone_Number_Applicant_1st' => 'Telephone'] as $key => $type) {
            $found[] = [$this->text($applicant[$key] ?? null), $type];
        }

        foreach ($this->holderBlocks('CAIS_Holder_Phone_Details') as $phone) {
            $found[] = [$this->text($phone['Mobile_Telephone_Number'] ?? null), 'Mobile'];
            $found[] = [$this->text($phone['Telephone_Number'] ?? null), $this->text($phone['Telephone_Type'] ?? null) !== null ? 'Telephone (type '.$this->text($phone['Telephone_Type']).')' : 'Telephone'];
        }

        return collect($found)
            ->filter(fn (array $pair) => $pair[0] !== null && preg_match('/\d/', $pair[0]) === 1)
            ->unique(fn (array $pair) => preg_replace('/\D+/', '', $pair[0]))
            ->map(fn (array $pair): array => ['number' => $this->maskTail($pair[0]), 'type' => $pair[1]])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        return collect([$this->applicant(), ...$this->holderBlocks('CAIS_Holder_Phone_Details')])
            ->map(fn (array $block) => $this->text($block['EMailId'] ?? null))
            ->filter(fn (?string $email) => $email !== null && str_contains($email, '@'))
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values()
            ->all();
    }

    /**
     * Experian's own account totals (CAIS_Summary), labelled from its field names.
     *
     * @return array<string, string>
     */
    public function accountsSummary(): array
    {
        $summary = $this->first($this->find('CAIS_Summary'));

        return [
            ...$this->labelled($this->first($summary['Credit_Account'] ?? null)),
            ...$this->labelled($this->first($summary['Total_Outstanding_Balance'] ?? null), ['Outstanding_Balance_Secured', 'Outstanding_Balance_UnSecured', 'Outstanding_Balance_All']),
        ];
    }

    /**
     * Every reported account (CAIS_Account_DETAILS): recognised fields by label
     * (null when absent), the monthly history, and every other field Experian
     * sent — holder details included, identifiers masked.
     *
     * @return list<array{fields: array<string, string|null>, history: list<array{month: string, status: string, late: bool}>, extra: array<string, mixed>}>
     */
    public function accounts(): array
    {
        return collect($this->accountItems())
            ->map(function (array $account): array {
                $fields = [];
                $consumed = ['CAIS_Account_History'];

                foreach (self::ACCOUNT_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $account));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($account[$key]) : null;

                    $fields[$label] = match (true) {
                        $value === null => null,
                        $label === 'Account number' => $this->maskTail($value),
                        $label === 'Account type' => $this->accountType($value),
                        $label === 'Portfolio type' => $this->portfolioType($value),
                        in_array($label, self::AMOUNT_LABELS, true) => $this->rupees($value),
                        in_array($label, self::DATE_LABELS, true) => $this->displayDate($value),
                        default => $value,
                    };
                }

                $fields = ['Open / closed' => $this->text($account['Date_Closed'] ?? null) !== null ? 'Closed' : 'Open', ...$fields];

                return [
                    'fields' => $fields,
                    'history' => $this->history($account['CAIS_Account_History'] ?? null),
                    'extra' => $this->masked(collect($account)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Enquiry counts: TotalCAPS_Summary, CAPS_Summary and NonCreditCAPS_Summary.
     *
     * @return array<string, string>
     */
    public function enquirySummary(): array
    {
        return [
            ...$this->labelled($this->first($this->find('TotalCAPS_Summary'))),
            ...$this->labelled($this->first($this->find('CAPS_Summary'))),
            ...$this->labelled($this->first($this->find('NonCreditCAPS_Summary'))),
        ];
    }

    /**
     * Credit enquiries (CAPS → CAPS_Application_Details).
     *
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    public function enquiries(): array
    {
        return $this->enquiryRows($this->find('CAPS_Application_Details', $this->first($this->find('CAPS'))));
    }

    /**
     * Non-credit enquiries (NonCreditCAPS → CAPS_Application_Details).
     *
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    public function nonCreditEnquiries(): array
    {
        return $this->enquiryRows($this->find('CAPS_Application_Details', $this->first($this->find('NonCreditCAPS'))));
    }

    /**
     * Report sections without a dedicated layout (anything Experian adds
     * later), with identifiers masked.
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
     * Surepass envelope fields beside the report (client_id, echoed name…),
     * identifiers masked; never the temporary PDF link.
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
        $summary = $this->first($this->find('CAIS_Summary'));
        $accounts = $this->first($summary['Credit_Account'] ?? null);
        $balances = $this->first($summary['Total_Outstanding_Balance'] ?? null);
        $enquiries = $this->first($this->find('TotalCAPS_Summary'));

        return [
            'score' => $this->score(),
            'report_number' => $this->header()['report_number'],
            'accounts_total' => $this->text($accounts['CreditAccountTotal'] ?? null) ?? ($this->accountItems() === [] ? null : (string) count($this->accountItems())),
            'accounts_active' => $this->text($accounts['CreditAccountActive'] ?? null),
            'accounts_closed' => $this->text($accounts['CreditAccountClosed'] ?? null),
            'accounts_default' => $this->text($accounts['CreditAccountDefault'] ?? null),
            'total_outstanding' => $this->text($balances['Outstanding_Balance_All'] ?? null),
            'outstanding_secured' => $this->text($balances['Outstanding_Balance_Secured'] ?? null),
            'outstanding_unsecured' => $this->text($balances['Outstanding_Balance_UnSecured'] ?? null),
            'enquiries_last_30_days' => $this->text($enquiries['TotalCAPSLast30Days'] ?? null),
            'enquiries_last_180_days' => $this->text($enquiries['TotalCAPSLast180Days'] ?? null),
        ];
    }

    /**
     * The headline figures behind the score, for the public result page's
     * report highlights and advice: counts and rupee totals only — no lender,
     * account number or identity data. Experian's own totals (CAIS_Summary,
     * TotalCAPS_Summary) come first; the rest are counted from the account
     * rows. Each is null when the report does not say.
     *
     * @return array{accounts_total: ?int, accounts_active: ?int, accounts_overdue: ?int, overdue_amount: ?int, accounts_written_off: ?int, late_payments_last_12_months: ?int, card_limit: ?int, card_balance: ?int, enquiries_last_6_months: ?int, credit_age_months: ?int, outstanding_balance: ?int}
     */
    public function insights(): array
    {
        $summary = $this->first($this->find('CAIS_Summary'));
        $counts = $this->first($summary['Credit_Account'] ?? null);
        $balances = $this->first($summary['Total_Outstanding_Balance'] ?? null);
        $asOf = $this->reportDate() ?? CarbonImmutable::now();
        $accounts = $this->accountItems();
        $open = array_values(array_filter($accounts, fn (array $account) => $this->text($account['Date_Closed'] ?? null) === null));
        $cards = array_values(array_filter($open, fn (array $account) => in_array(ltrim((string) $this->text($account['Account_Type'] ?? null), '0'), self::CARD_TYPES, true)));
        $histories = collect($accounts)->map(fn (array $account) => $this->history($account['CAIS_Account_History'] ?? null))->reject(fn (array $history) => $history === []);
        $since = $asOf->startOfMonth()->subMonths(11);
        $enquiryDates = collect($this->items($this->find('CAPS_Application_Details', $this->first($this->find('CAPS')))))
            ->map(fn (array $enquiry) => $this->date($this->text($enquiry['Date_of_Request'] ?? null)))
            ->filter();
        $openedDates = collect($accounts)->map(fn (array $account) => $this->date($this->text($account['Open_Date'] ?? null)))->filter();

        return [
            'accounts_total' => $this->whole($counts['CreditAccountTotal'] ?? null) ?? ($accounts === [] ? null : count($accounts)),
            'accounts_active' => $this->whole($counts['CreditAccountActive'] ?? null) ?? ($accounts === [] ? null : count($open)),
            'accounts_overdue' => $accounts === [] ? null : count(array_filter($accounts, fn (array $account) => ($this->whole($account['Amount_Past_Due'] ?? null) ?? 0) > 0)),
            'overdue_amount' => $this->total($accounts, ['Amount_Past_Due']),
            'accounts_written_off' => $accounts === []
                ? $this->whole($counts['CreditAccountDefault'] ?? null)
                : count(array_filter($accounts, fn (array $account) => $this->isWrittenOff($account))),
            'late_payments_last_12_months' => $histories->isEmpty() ? null : $histories->flatten(1)
                ->filter(fn (array $month) => $month['late'] && ($this->monthOf($month['month'])?->gte($since) ?? false))
                ->count(),
            'card_limit' => $this->total($cards, ['Credit_Limit_Amount', 'Highest_Credit_or_Original_Loan_Amount']),
            'card_balance' => $this->total($cards, ['Current_Balance']),
            'enquiries_last_6_months' => $this->whole($this->first($this->find('TotalCAPS_Summary'))['TotalCAPSLast180Days'] ?? null)
                ?? ($enquiryDates->isEmpty() ? null : $enquiryDates->filter(fn (CarbonImmutable $date) => $date->gte($asOf->subMonths(6)))->count()),
            'credit_age_months' => $openedDates->isEmpty() ? null : (int) $openedDates->min()->diffInMonths($asOf),
            'outstanding_balance' => $this->whole($balances['Outstanding_Balance_All'] ?? null) ?? $this->total($open, ['Current_Balance']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applicant(): array
    {
        return $this->first($this->find('Current_Applicant_Details'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountItems(): array
    {
        return $this->items($this->find('CAIS_Account_DETAILS'));
    }

    /**
     * Every $block (e.g. CAIS_Holder_Phone_Details) across all accounts.
     *
     * @return list<array<string, mixed>>
     */
    private function holderBlocks(string $block): array
    {
        return collect($this->accountItems())
            ->flatMap(fn (array $account) => $this->items($account[$block] ?? null))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array{address: string, state: ?string, postal: ?string, source: string}|null
     */
    private function address(array $address, string $source): ?array
    {
        $line = collect([
            'FlatNoPlotNoHouseNo', 'BldgNoSocietyName', 'RoadNoNameAreaLocality', 'Landmark', 'City',
            'First_Line_Of_Address_non_normalized', 'Second_Line_Of_Address_non_normalized', 'Third_Line_Of_Address_non_normalized',
            'Fifth_Line_Of_Address_non_normalized', 'City_non_normalized',
        ])
            ->map(fn (string $key) => $this->text($address[$key] ?? null))
            ->filter()
            ->unique()
            ->implode(', ');

        if ($line === '') {
            return null;
        }

        return [
            'address' => $line,
            'state' => $this->text($address['State'] ?? $address['State_non_normalized'] ?? null),
            'postal' => $this->text($address['PINCode'] ?? $address['ZIP_Postal_Code_non_normalized'] ?? null),
            'source' => $source,
        ];
    }

    /**
     * @return list<array{fields: array<string, string|null>, extra: array<string, mixed>}>
     */
    private function enquiryRows(mixed $details): array
    {
        return collect($this->items($details))
            ->map(function (array $enquiry): array {
                $fields = [];
                $consumed = [];

                foreach (self::ENQUIRY_FIELDS as $label => $keys) {
                    $key = collect($keys)->first(fn (string $key) => array_key_exists($key, $enquiry));
                    $consumed = [...$consumed, ...$keys];
                    $value = $key !== null ? $this->text($enquiry[$key]) : null;

                    $fields[$label] = match ($label) {
                        'Amount' => $this->rupees($value),
                        'Date' => $this->displayDate($value),
                        default => $value,
                    };
                }

                return [
                    'fields' => $fields,
                    'extra' => $this->masked(collect($enquiry)->except($consumed)->reject(fn ($value) => $this->isEmpty($value))->all()),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * CAIS_Account_History: [{Year: "2026", Month: "09", Days_Past_Due: "0", Asset_Classification: "?"}].
     * A month is late when days past due > 0 or the asset class is SMA/SUB/DBT/LSS.
     *
     * @return list<array{month: string, status: string, late: bool}>
     */
    private function history(mixed $history): array
    {
        return collect($this->items($history))
            ->map(function (array $month): ?array {
                $year = $this->text($month['Year'] ?? null);
                $monthNumber = $this->text($month['Month'] ?? null);

                if ($year === null || $monthNumber === null) {
                    return null;
                }

                $daysPastDue = $this->text($month['Days_Past_Due'] ?? null) ?? '';
                $asset = strtoupper((string) $this->text($month['Asset_Classification'] ?? null));
                $late = (ctype_digit($daysPastDue) && (int) $daysPastDue > 0) || in_array($asset, ['SMA', 'SUB', 'DBT', 'LSS'], true);

                return [
                    'month' => str_pad($monthNumber, 2, '0', STR_PAD_LEFT).'-'.substr($year, -2),
                    'status' => trim($daysPastDue.($asset !== '' && $asset !== '?' ? '/'.$asset : '')),
                    'late' => $late,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Written off, settled, suit filed or a wilful default — the marks lenders read as a default.
     *
     * @param  array<string, mixed>  $account
     */
    private function isWrittenOff(array $account): bool
    {
        $code = fn (string $key): string => str_pad((string) $this->text($account[$key] ?? null), 2, '0', STR_PAD_LEFT);

        return in_array($code('Written_off_Settled_Status'), self::WRITTEN_OFF_CODES, true)
            || in_array($code('SuitFiled_WilfulDefault'), self::SUIT_FILED_CODES, true)
            || ($this->whole($account['Written_Off_Amt_Total'] ?? null) ?? 0) > 0
            || ($this->whole($account['Settlement_Amount'] ?? null) ?? 0) > 0;
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

    private function accountType(string $code): string
    {
        $label = self::ACCOUNT_TYPES[ltrim($code, '0') ?: '0'] ?? null;

        return $label === null ? $code : "{$label} ({$code})";
    }

    private function portfolioType(string $code): string
    {
        return match (strtoupper($code)) {
            'I' => 'Instalment (I)',
            'R' => 'Revolving (R)',
            'M' => 'Mortgage (M)',
            'O' => 'Open (O)',
            default => $code,
        };
    }

    private function gender(?string $code): ?string
    {
        return match ($code) {
            null => null,
            '1' => 'Male',
            '2' => 'Female',
            default => $code,
        };
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

    /** "CreditAccountTotal" → "Credit account total", "TotalCAPSLast30Days" → "Total CAPS last 30 days". */
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

    /** Experian's "20261012" → "12 Oct 2026"; anything else is returned as sent. */
    private function displayDate(?string $value): ?string
    {
        return $value === null ? null : ($this->date($value)?->format('d M Y') ?? $value);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        foreach (['Ymd', 'Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
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
