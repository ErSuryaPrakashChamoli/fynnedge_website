<?php

namespace App\Filament\Resources\CrifReports;

use App\Models\User;
use App\Modules\CreditBureau\Actions\FetchCrifReportPdf;
use App\Modules\CreditBureau\Actions\ReconcileCrifReport;
use App\Modules\CreditBureau\Actions\RequestCrifReport;
use App\Modules\CreditBureau\DataTransferObjects\CrifConsentInput;
use App\Modules\CreditBureau\DataTransferObjects\CrifReportRequest;
use App\Modules\CreditBureau\DataTransferObjects\CrifRequestOutcome;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Enums\CrifReconciliationResolution;
use App\Modules\CreditBureau\Enums\CrifReportStatus;
use App\Modules\CreditBureau\Enums\CrifRequestType;
use App\Modules\CreditBureau\Exceptions\CrifRequestRefused;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\CreditBureau\Models\CrifReport;
use App\Modules\CreditBureau\Services\CrifSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Services\MobileNumber;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CRIF buttons, shared by the CRIF list and report pages and by the
 * Customer and Application screens so every entry point offers the same
 * workflow. Visibility only tidies the UI: RequestCrifReport, ReconcileCrifReport
 * and FetchCrifReportPdf re-check the permission, the consent and the state,
 * and their refusals are shown to the employee.
 */
final class CrifReportActions
{
    private const RECORD_NEW_CONSENT = 'record';

    /**
     * "Fetch CRIF Report" on a customer or application with no usable report.
     */
    public static function fetch(): Action
    {
        return self::requestAction('fetchCrifReport', CrifRequestType::Standard)
            ->label('Fetch CRIF Report')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null
                && self::latestRelevant(self::customerOf($record)) === null
                && (bool) self::user()?->can('create', CrifReport::class));
    }

    /**
     * "View Existing CRIF Report" / "View CRIF Request in Progress" when the
     * customer has a reusable report or an unresolved request.
     */
    public static function viewExisting(): Action
    {
        return Action::make('viewExistingCrifReport')
            ->label(fn (Model $record): string => self::latestRelevant(self::customerOf($record))?->status === CrifReportStatus::Success
                ? 'View Existing CRIF Report'
                : 'View CRIF Request in Progress')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null
                && self::latestRelevant(self::customerOf($record)) !== null
                && (bool) self::user()?->can('viewAny', CrifReport::class))
            ->url(fn (Model $record): string => CrifReportResource::getUrl('view', ['record' => self::latestRelevant(self::customerOf($record))]));
    }

    /**
     * An authorised second paid pull while a reusable report exists.
     */
    public static function fetchFresh(): Action
    {
        return self::requestAction('fetchFreshCrifReport', CrifRequestType::Fresh)
            ->label('Fetch Fresh CRIF Report')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null
                && self::latestRelevant(self::customerOf($record))?->status === CrifReportStatus::Success
                && (bool) self::user()?->can('fetchFresh', CrifReport::class));
    }

    /**
     * The same fresh override, from the report screen itself.
     */
    public static function fetchFreshFromReport(): Action
    {
        return self::requestAction('fetchFreshCrifReport', CrifRequestType::Fresh)
            ->label('Fetch fresh report')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (CrifReport $record): bool => $record->isReusable()
                && $record->customer !== null
                && (bool) self::user()?->can('fetchFresh', CrifReport::class));
    }

    /**
     * The CRIF list's button: pick the customer, then the same form.
     */
    public static function fetchForCustomer(): Action
    {
        return self::requestAction('fetchCrifReport', CrifRequestType::Standard, pickCustomer: true)
            ->label('Fetch CRIF report')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->visible(fn (): bool => (bool) self::user()?->can('create', CrifReport::class));
    }

    public static function retry(): Action
    {
        return Action::make('retryCrifReport')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (CrifReport $record): bool => $record->status === CrifReportStatus::Failed
                && $record->retryable
                && ! CrifReport::query()->where('previous_report_id', $record->id)->exists()
                && (bool) self::user()?->can('create', CrifReport::class))
            ->requiresConfirmation()
            ->modalHeading('Retry this CRIF request?')
            ->modalDescription(fn (CrifReport $record): string => 'Surepass did not process the previous attempt ('.($record->reconciliation_resolution?->getLabel() ?? $record->error_code?->getLabel() ?? 'failed').'), so it was not charged. This sends the same details again as a new paid request — unless a report for this PAN has arrived in the meantime, which is shown instead.')
            ->action(fn (CrifReport $record, Action $action) => self::submit(
                $action,
                fn () => app(RequestCrifReport::class)->handle(CrifReportRequest::retryOf($record), self::actor()),
            ));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcileCrifReport')
            ->label('Reconcile')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (CrifReport $record): bool => $record->status === CrifReportStatus::ReconciliationRequired
                && (bool) self::user()?->can('reconcile', $record))
            ->modalHeading('Reconcile this CRIF request')
            ->schema(fn (CrifReport $record): array => [
                Callout::make('Check Surepass before deciding')
                    ->description('This request ended without a usable answer ('.($record->error_code?->getLabel() ?? 'unknown').'), so Surepass may have charged for it. Look it up in the Surepass dashboard usage log around '.$record->requested_at?->format('d M Y, H:i').' UTC (or ask Surepass support) and record what you found. Nothing is sent to Surepass from here.')
                    ->warning(),
                Radio::make('resolution')
                    ->label('What did Surepass confirm?')
                    ->options(CrifReconciliationResolution::class)
                    ->required(),
                Textarea::make('note')
                    ->label('How was this confirmed?')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000),
            ])
            ->action(fn (CrifReport $record, array $data, Action $action) => self::run($action, function () use ($record, $data): void {
                $resolution = $data['resolution'] instanceof CrifReconciliationResolution
                    ? $data['resolution']
                    : CrifReconciliationResolution::from($data['resolution']);

                app(ReconcileCrifReport::class)->handle($record, $resolution, (string) $data['note'], self::actor());

                Notification::make()->title('Request reconciled')->success()->send();
            }));
    }

    public static function downloadPdf(): Action
    {
        return Action::make('downloadCrifPdf')
            ->label(fn (CrifReport $record): string => $record->hasStoredPdf() ? 'Download PDF' : 'Get PDF from Surepass')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (CrifReport $record): bool => $record->hasReport() && (bool) self::user()?->can('downloadPdf', $record))
            ->requiresConfirmation(fn (CrifReport $record): bool => ! $record->hasStoredPdf())
            ->modalDescription('Surepass\'s PDF is fetched once with the customer\'s details (another provider call), stored encrypted on this server, and every later download is served from that copy.')
            ->action(function (CrifReport $record, Action $action): ?StreamedResponse {
                try {
                    $pdf = app(FetchCrifReportPdf::class)->handle($record, self::actor());
                } catch (CrifRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return null;
                }

                return response()->streamDownload(
                    function () use ($pdf): void {
                        echo $pdf;
                    },
                    'crif-report-'.$record->pan_masked.'-'.$record->completed_at?->format('Ymd').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function viewRawJson(): Action
    {
        return Action::make('viewRawCrifJson')
            ->label('Raw JSON')
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->visible(fn (CrifReport $record): bool => is_array($record->raw_response) && (bool) self::user()?->can('viewRaw', $record))
            ->modalHeading('Complete Surepass response')
            ->modalDescription('Unmasked and exactly as received. Opening this is recorded in the report\'s history.')
            ->modalWidth('7xl')
            ->mountUsing(fn (CrifReport $record) => $record->recordEvent('raw_viewed'))
            ->modalContent(fn (CrifReport $record) => view('filament.crif-reports.raw-json', [
                'json' => json_encode($record->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private static function requestAction(string $name, CrifRequestType $type, bool $pickCustomer = false): Action
    {
        return Action::make($name)
            ->modalHeading($type === CrifRequestType::Fresh ? 'Fetch a fresh CRIF report' : 'Fetch CRIF report')
            ->modalDescription($type === CrifRequestType::Fresh
                ? 'A reusable report already exists, so this is a SECOND paid pull. Use it only when a fresh report is genuinely required (e.g. a lender needs a report dated today, or the customer\'s circumstances changed). The reason is recorded.'
                : 'Each new report is a paid Surepass call. If this PAN already has a report from the last '.CrifSettings::reuseDays().' days, or a request in progress, that is shown instead and nothing is charged.')
            ->modalWidth('3xl')
            ->fillForm(fn (?Model $record): array => $record === null ? ['consent_source' => self::RECORD_NEW_CONSENT, 'consented_at' => now()] : self::defaults($record))
            ->schema(fn (?Model $record): array => self::requestSchema($record, $type, $pickCustomer))
            ->action(function (?Model $record, array $data, Action $action) use ($type, $pickCustomer): void {
                self::submit($action, function () use ($record, $data, $type, $pickCustomer): CrifRequestOutcome {
                    $customer = $pickCustomer ? Customer::query()->find($data['customer_id'] ?? null) : self::customerOf($record);

                    if ($customer === null) {
                        throw new CrifRequestRefused('Choose the customer this report is for.');
                    }

                    return app(RequestCrifReport::class)->handle(new CrifReportRequest(
                        customer: $customer,
                        firstName: (string) ($data['first_name'] ?? ''),
                        lastName: (string) ($data['last_name'] ?? ''),
                        middleName: (string) ($data['middle_name'] ?? ''),
                        mobile: (string) ($data['mobile'] ?? ''),
                        pan: (string) ($data['pan'] ?? ''),
                        consent: self::consentInput($data, $customer),
                        type: $type,
                        freshReason: $data['fresh_reason'] ?? null,
                        journeySession: $record instanceof JourneySession ? $record : ($record instanceof CrifReport ? $record->journeySession : null),
                        previousReport: $record instanceof CrifReport ? $record : null,
                    ), self::actor());
                });
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function requestSchema(?Model $record, CrifRequestType $type, bool $pickCustomer): array
    {
        $fixedCustomer = $pickCustomer ? null : self::customerOf($record);

        return [
            ...($pickCustomer ? [
                Select::make('customer_id')
                    ->label('Customer')
                    ->required()
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search): array => Customer::query()
                        ->where(fn ($query) => $query
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orderBy('full_name')
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (Customer $customer) => [$customer->id => self::customerLabel($customer)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($customer = Customer::query()->find($value)) ? self::customerLabel($customer) : null)
                    ->helperText('Reports are always linked to a customer record. Someone who has not applied on the website yet? Add them first under Customers → Add customer.'),
            ] : []),
            Section::make('Customer details sent to CRIF')
                ->description('Use the name exactly as on the customer\'s PAN card. Surepass takes a first and a last name, so a middle name is sent together with the first name.')
                ->components([
                    Grid::make(3)->schema([
                        TextInput::make('first_name')->label('First name')->required()->maxLength(100),
                        TextInput::make('middle_name')->label('Middle name')->maxLength(100)->placeholder('If on the PAN card'),
                        TextInput::make('last_name')->label('Last name')->required()->maxLength(100),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('mobile')
                            ->label('Mobile number')
                            ->tel()
                            ->required()
                            ->maxLength(15)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! MobileNumber::isValid(MobileNumber::normalize($value))) {
                                    $fail('Enter a 10-digit Indian mobile number.');
                                }
                            }),
                        TextInput::make('pan')
                            ->label('PAN')
                            ->required()
                            ->maxLength(12)
                            ->placeholder('ABCDE1234F')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! PanNumber::isValid(PanNumber::normalize($value))) {
                                    $fail('Enter a valid PAN, e.g. ABCDE1234F.');
                                }
                            }),
                    ]),
                ]),
            Section::make('Customer consent')
                ->description('A CRIF report may only be requested with the customer\'s consent, given within the last '.CrifSettings::consentValidityDays().' days.')
                ->components([
                    Select::make('consent_source')
                        ->label('Consent')
                        ->required()
                        ->live()
                        ->options(fn (Get $get): array => self::consentOptions($fixedCustomer ?? Customer::query()->find($get('customer_id')))),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => $get('consent_source') === self::RECORD_NEW_CONSENT)
                        ->schema([
                            Select::make('consent_method')
                                ->label('How the customer consented')
                                ->options(ConsentMethod::employeeRecordable())
                                ->required(),
                            DateTimePicker::make('consented_at')
                                ->label('When (UTC)')
                                ->seconds(false)
                                ->maxDate(now())
                                ->required(),
                            TextInput::make('evidence_reference')
                                ->label('Evidence reference')
                                ->helperText('Where the proof is kept: form number, call recording ID, email subject and date…')
                                ->required()
                                ->minLength(3)
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Checkbox::make('attested')
                                ->label('I confirm the customer consented to FynnEdge obtaining their CRIF credit report for this application, and the evidence above is on file.')
                                ->accepted()
                                ->columnSpanFull(),
                        ]),
                ]),
            ...($type === CrifRequestType::Fresh ? [
                Textarea::make('fresh_reason')
                    ->label('Why is a fresh report needed?')
                    ->required()
                    ->minLength(15)
                    ->maxLength(1000),
            ] : []),
        ];
    }

    /**
     * Prefills the form from what the site already knows about the person.
     *
     * @return array<string, mixed>
     */
    private static function defaults(Model $record): array
    {
        $customer = self::customerOf($record);
        $session = $record instanceof JourneySession ? $record : null;
        $previous = $record instanceof CrifReport ? $record : $customer?->crifReports()->whereNotNull('pan_encrypted')->latest('id')->first();

        [$firstName, $middleName, $lastName] = $previous && filled($previous->first_name)
            ? [$previous->first_name, $previous->middle_name, $previous->last_name]
            : self::splitName((string) ($customer?->full_name ?? ''));

        $journeyConsent = $session?->creditConsent
            ?? CreditConsent::query()
                ->whereNotNull('pan_number')
                ->whereHas('journeySession', fn ($query) => $query->where('customer_id', $customer?->id))
                ->latest('consented_at')
                ->first();

        $consentOptions = self::consentOptions($customer);
        $preferredConsent = $record instanceof CrifReport ? $record->credit_consent_id : $session?->creditConsent?->id;

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'mobile' => $previous?->mobile ?? $session?->phone_number ?? $customer?->phone,
            'pan' => $previous?->pan_encrypted ?? $journeyConsent?->pan_number,
            'consent_source' => $preferredConsent !== null && array_key_exists($preferredConsent, $consentOptions)
                ? $preferredConsent
                : (collect($consentOptions)->keys()->first(fn ($key) => $key !== self::RECORD_NEW_CONSENT) ?? self::RECORD_NEW_CONSENT),
            'consented_at' => now(),
        ];
    }

    /**
     * Consents still valid for this customer, newest first, then "record new".
     *
     * @return array<int|string, string>
     */
    private static function consentOptions(?Customer $customer): array
    {
        $options = $customer === null ? collect() : CreditConsent::query()
            ->where('status', ConsentStatus::Given)
            ->where('consented_at', '>=', now()->subDays(CrifSettings::consentValidityDays()))
            ->where(fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->orWhereHas('journeySession', fn ($query) => $query->where('customer_id', $customer->id)))
            ->latest('consented_at')
            ->get()
            ->mapWithKeys(fn (CreditConsent $consent) => [$consent->id => self::consentLabel($consent)]);

        // `+`, not spread: spreading re-numbers the integer consent ids.
        return $options->all() + [self::RECORD_NEW_CONSENT => 'Record new consent now'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function consentInput(array $data, Customer $customer): CrifConsentInput
    {
        $source = $data['consent_source'] ?? null;

        if ($source !== self::RECORD_NEW_CONSENT) {
            $consent = CreditConsent::query()->find($source);

            if ($consent === null) {
                throw new CrifRequestRefused('Choose the customer\'s consent, or record new consent.');
            }

            return CrifConsentInput::existing($consent);
        }

        $method = $data['consent_method'] ?? null;

        return CrifConsentInput::recordedByEmployee(
            $method instanceof ConsentMethod ? $method : (ConsentMethod::tryFrom((string) $method) ?? throw new CrifRequestRefused('Choose how the customer gave consent.')),
            $data['evidence_reference'] ?? null,
            filled($data['consented_at'] ?? null) ? Carbon::parse($data['consented_at']) : null,
            (bool) ($data['attested'] ?? false),
        );
    }

    /**
     * @param  Closure(): CrifRequestOutcome  $request
     */
    private static function submit(Action $action, Closure $request): void
    {
        try {
            $outcome = $request();
        } catch (CrifRequestRefused|AuthorizationException $exception) {
            self::refuse($action, $exception);

            return;
        }

        $notification = Notification::make()->body($outcome->message());

        match ($outcome->kind) {
            CrifRequestOutcome::QUEUED => $notification->title('CRIF report requested')->success(),
            CrifRequestOutcome::REUSED => $notification->title('Existing report shown — no new charge')->info(),
            default => $notification->title('Request already in progress — no new charge')->warning(),
        };

        $notification->send();
        $action->redirect(CrifReportResource::getUrl('view', ['record' => $outcome->report]));
    }

    private static function run(Action $action, Closure $step): void
    {
        try {
            $step();
        } catch (CrifRequestRefused|AuthorizationException $exception) {
            self::refuse($action, $exception);
        }
    }

    private static function refuse(Action $action, CrifRequestRefused|AuthorizationException $exception): void
    {
        Notification::make()
            ->title('Not done')
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    /**
     * The customer's unresolved request if any, otherwise their newest reusable
     * report — what "View existing" opens and what hides "Fetch".
     */
    private static function latestRelevant(?Customer $customer): ?CrifReport
    {
        if ($customer === null) {
            return null;
        }

        return $customer->crifReports()->inFlight()->latest('id')->first()
            ?? $customer->crifReports()->reusable()->latest('completed_at')->latest('id')->first();
    }

    private static function customerOf(?Model $record): ?Customer
    {
        return match (true) {
            $record instanceof Customer => $record,
            $record instanceof JourneySession, $record instanceof CrifReport => $record->customer,
            default => null,
        };
    }

    /**
     * "Surya Prakash Chamoli" → first "Surya", middle "Prakash", last "Chamoli".
     * Two words have no middle name; one word is only a first name.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function splitName(string $fullName): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($fullName)) ?: []));

        if (count($parts) < 2) {
            return [$parts[0] ?? '', '', ''];
        }

        $firstName = array_shift($parts);
        $lastName = array_pop($parts);

        return [$firstName, implode(' ', $parts), $lastName];
    }

    private static function customerLabel(Customer $customer): string
    {
        return trim(($customer->full_name ?: 'Unnamed').' — '.($customer->email ?? $customer->phone ?? ''), ' —');
    }

    private static function consentLabel(CreditConsent $consent): string
    {
        $when = $consent->consented_at?->format('d M Y');

        return $consent->method === ConsentMethod::OnlineJourney || $consent->method === null
            ? "Website application consent, {$when} ({$consent->purpose})"
            : "{$consent->method->getLabel()}, {$when} — ref. {$consent->evidence_reference}";
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private static function actor(): User
    {
        return self::user() ?? throw new AuthorizationException('Sign in again to request CRIF reports.');
    }
}
