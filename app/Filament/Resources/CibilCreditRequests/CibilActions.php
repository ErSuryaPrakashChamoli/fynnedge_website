<?php

namespace App\Filament\Resources\CibilCreditRequests;

use App\Filament\Schemas\CreditConsentFields;
use App\Models\User;
use App\Modules\Cibil\Actions\FetchCibilPdf;
use App\Modules\Cibil\Actions\ReconcileCibilRequest;
use App\Modules\Cibil\DataTransferObjects\CibilCheckRequest;
use App\Modules\Cibil\DataTransferObjects\CibilOutcome;
use App\Modules\Cibil\Enums\CibilProduct;
use App\Modules\Cibil\Enums\CibilReconciliationResolution;
use App\Modules\Cibil\Enums\CibilRequestStatus;
use App\Modules\Cibil\Enums\CibilRequestType;
use App\Modules\Cibil\Enums\Gender;
use App\Modules\Cibil\Exceptions\CibilRequestRefused;
use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\Cibil\Services\CibilCreditService;
use App\Modules\Cibil\Services\CibilSettings;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;
use App\Modules\MobileOtp\Services\MobileNumber;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
 * The CIBIL buttons, shared by the CIBIL list and request pages and by
 * the Customer and Application screens (as an "CIBIL" menu), so every entry
 * point offers the same workflow. Visibility only tidies the UI: the CIBIL
 * module (RequestCibilCheck, ReconcileCibilRequest, FetchCibilPdf)
 * re-checks the permission, consent and state, and its refusals are shown to
 * the employee.
 */
final class CibilActions
{
    /**
     * The "CIBIL" menu on a customer or application page.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            self::viewLatest(),
            self::request(CibilProduct::Report),
            self::request(CibilProduct::Score),
            self::request(CibilProduct::Pdf),
            self::fetchFresh(),
        ])
            ->label('CIBIL')
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->color('gray')
            ->button();
    }

    public static function request(CibilProduct $product): Action
    {
        return self::requestAction(self::actionName($product), $product, CibilRequestType::Standard)
            ->label(match ($product) {
                CibilProduct::Report => 'Fetch CIBIL Report',
                CibilProduct::Score => 'Check CIBIL Score',
                CibilProduct::Pdf => 'Get CIBIL PDF Report',
            })
            ->icon(self::icon($product))
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null && (bool) self::user()?->can('create', CibilCreditRequest::class));
    }

    /**
     * Opens the customer's unresolved request, else their newest reusable result.
     */
    public static function viewLatest(): Action
    {
        return Action::make('viewLatestCIBIL')
            ->label(fn (Model $record): string => self::latestRelevant(self::customerOf($record))?->status === CibilRequestStatus::Success
                ? 'View Latest CIBIL Result'
                : 'View CIBIL Request in Progress')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (Model $record): bool => self::latestRelevant(self::customerOf($record)) !== null
                && (bool) self::user()?->can('viewAny', CibilCreditRequest::class))
            ->url(fn (Model $record): string => CibilCreditRequestResource::getUrl('view', ['record' => self::latestRelevant(self::customerOf($record))]));
    }

    /**
     * An authorised second paid report pull while a reusable one exists.
     */
    public static function fetchFresh(): Action
    {
        return self::requestAction('fetchFreshCibilReport', CibilProduct::Report, CibilRequestType::Fresh)
            ->label('Fetch Fresh CIBIL Report')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Model $record): bool => ($customer = self::customerOf($record)) !== null
                && $customer->cibilRequests()->reusableFor(CibilProduct::Report)->exists()
                && (bool) self::user()?->can('fetchFresh', CibilCreditRequest::class));
    }

    public static function fetchFreshFromRequest(): Action
    {
        return self::requestAction('fetchFreshCIBIL', null, CibilRequestType::Fresh)
            ->label('Fetch fresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (CibilCreditRequest $record): bool => $record->isReusable()
                && $record->customer !== null
                && (bool) self::user()?->can('fetchFresh', CibilCreditRequest::class));
    }

    /**
     * From a score or report: the same person's PDF — a separate paid request,
     * answered by a recent stored PDF when there is one.
     */
    public static function requestPdfFromRequest(): Action
    {
        return self::requestAction('requestCibilPdf', CibilProduct::Pdf, CibilRequestType::Standard)
            ->label('Get PDF report')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (CibilCreditRequest $record): bool => $record->product !== CibilProduct::Pdf
                && $record->customer !== null
                && (bool) self::user()?->can('create', CibilCreditRequest::class)
                && (bool) self::user()?->can('downloadPdf', $record));
    }

    /**
     * The CIBIL list's buttons: pick the customer, then the same form.
     */
    public static function requestForCustomer(CibilProduct $product): Action
    {
        return self::requestAction(self::actionName($product), $product, CibilRequestType::Standard, pickCustomer: true)
            ->label(match ($product) {
                CibilProduct::Report => 'Fetch CIBIL report',
                CibilProduct::Score => 'Check CIBIL score',
                CibilProduct::Pdf => 'Get CIBIL PDF report',
            })
            ->icon(self::icon($product))
            ->color($product === CibilProduct::Report ? 'primary' : 'gray')
            ->visible(fn (): bool => (bool) self::user()?->can('create', CibilCreditRequest::class));
    }

    public static function retry(): Action
    {
        return Action::make('retryCIBIL')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (CibilCreditRequest $record): bool => $record->status === CibilRequestStatus::Failed
                && $record->retryable
                && ! CibilCreditRequest::query()->where('previous_request_id', $record->id)->exists()
                && (bool) self::user()?->can('create', CibilCreditRequest::class))
            ->requiresConfirmation()
            ->modalHeading('Retry this CIBIL request?')
            ->modalDescription(fn (CibilCreditRequest $record): string => 'Surepass did not process the previous attempt ('.($record->reconciliation_resolution?->getLabel() ?? $record->error_code?->getLabel() ?? 'failed').'), so it was not charged. This sends the same details again as a new paid request — unless a usable result for this PAN has arrived in the meantime, which is shown instead.')
            ->action(fn (CibilCreditRequest $record, Action $action) => self::submit(
                $action,
                fn () => app(CibilCreditService::class)->request(CibilCheckRequest::retryOf($record), self::actor()),
            ));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcileCIBIL')
            ->label('Reconcile')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (CibilCreditRequest $record): bool => $record->status === CibilRequestStatus::ReconciliationRequired
                && (bool) self::user()?->can('reconcile', $record))
            ->modalHeading('Reconcile this CIBIL request')
            ->schema(fn (CibilCreditRequest $record): array => [
                Callout::make('Check Surepass before deciding')
                    ->description('This request ended without a usable answer ('.($record->error_code?->getLabel() ?? 'unknown').'), so Surepass may have charged for it. Look it up in the Surepass dashboard usage log around '.$record->requested_at?->format('d M Y, H:i').' UTC (or ask Surepass support) and record what you found. Nothing is sent to Surepass from here.')
                    ->warning(),
                Radio::make('resolution')
                    ->label('What did Surepass confirm?')
                    ->options(CibilReconciliationResolution::class)
                    ->required(),
                Textarea::make('note')
                    ->label('How was this confirmed?')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000),
            ])
            ->action(function (CibilCreditRequest $record, array $data, Action $action): void {
                try {
                    $resolution = $data['resolution'] instanceof CibilReconciliationResolution
                        ? $data['resolution']
                        : CibilReconciliationResolution::from($data['resolution']);

                    app(ReconcileCibilRequest::class)->handle($record, $resolution, (string) $data['note'], self::actor());
                } catch (CibilRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return;
                }

                Notification::make()->title('Request reconciled')->success()->send();
            });
    }

    /**
     * The PDF of a successful PDF request — from its encrypted stored copy, or
     * once more from Surepass's link. Never a paid call.
     */
    public static function downloadPdf(): Action
    {
        return Action::make('downloadCibilPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (CibilCreditRequest $record): bool => app(FetchCibilPdf::class)->isAvailable($record) && (bool) self::user()?->can('downloadPdf', $record))
            ->action(function (CibilCreditRequest $record, Action $action): ?StreamedResponse {
                try {
                    $pdf = app(FetchCibilPdf::class)->handle($record, self::actor());
                } catch (CibilRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return null;
                }

                return response()->streamDownload(
                    function () use ($pdf): void {
                        echo $pdf;
                    },
                    'cibil-report-'.$record->pan_masked.'-'.$record->completed_at?->format('Ymd').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function viewRawJson(): Action
    {
        return Action::make('viewRawCibilJson')
            ->label('Raw JSON')
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->visible(fn (CibilCreditRequest $record): bool => is_array($record->raw_response) && (bool) self::user()?->can('viewRaw', $record))
            ->modalHeading('Complete Surepass response')
            ->modalDescription('Unmasked and exactly as received. Opening this is recorded in the request\'s history.')
            ->modalWidth('7xl')
            ->mountUsing(fn (CibilCreditRequest $record) => $record->recordEvent('raw_viewed'))
            ->modalContent(fn (CibilCreditRequest $record) => view('filament.cibil.raw-json', [
                'json' => json_encode($record->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private static function requestAction(string $name, ?CibilProduct $product, CibilRequestType $type, bool $pickCustomer = false): Action
    {
        $productOf = fn (?Model $record): CibilProduct => $product ?? ($record instanceof CibilCreditRequest ? $record->product : CibilProduct::Report);

        return Action::make($name)
            ->modalHeading(fn (?Model $record): string => ($type === CibilRequestType::Fresh ? 'Fetch a fresh CIBIL ' : 'CIBIL ').match ($productOf($record)) {
                CibilProduct::Report => 'credit report',
                CibilProduct::Score => 'credit score',
                CibilProduct::Pdf => 'PDF credit report',
            })
            ->modalDescription(fn (?Model $record): string => $type === CibilRequestType::Fresh
                ? 'A reusable result already exists, so this is a SECOND paid pull. Use it only when a fresh result is genuinely required (e.g. a lender needs one dated today). The reason is recorded.'
                : 'Each new request is a paid Surepass call. If this PAN already has a usable CIBIL result (scores: '.CibilSettings::scoreReuseDays().' days, reports and PDFs: '.CibilSettings::reportReuseDays().' days), or a request in progress, that is shown instead and nothing is charged.')
            ->modalWidth('3xl')
            ->fillForm(fn (?Model $record): array => $record === null
                ? ['consent_source' => CreditConsentFields::RECORD_NEW, 'consented_at' => now()]
                : self::defaults($record))
            ->schema(fn (?Model $record): array => self::requestSchema($record, $type, $pickCustomer))
            ->action(function (?Model $record, array $data, Action $action) use ($type, $pickCustomer, $productOf, $product): void {
                self::submit($action, function () use ($record, $data, $type, $pickCustomer, $productOf, $product): CibilOutcome {
                    $customer = $pickCustomer ? Customer::query()->find($data['customer_id'] ?? null) : self::customerOf($record);
                    $consent = CreditConsentFields::toInput($data);

                    if ($customer === null) {
                        throw new CibilRequestRefused('Choose the customer this request is for.');
                    }

                    if ($consent === null) {
                        throw new CibilRequestRefused('Choose the customer\'s consent, or record new consent.');
                    }

                    // A PDF asked for from a score or report is a new request, not a follow-up of it.
                    $isFollowUp = $record instanceof CibilCreditRequest && ($product === null || $product === $record->product);
                    $gender = $data['gender'] ?? null;

                    return app(CibilCreditService::class)->request(new CibilCheckRequest(
                        customer: $customer,
                        product: $productOf($record),
                        name: (string) ($data['full_name'] ?? ''),
                        mobile: (string) ($data['mobile'] ?? ''),
                        pan: (string) ($data['pan'] ?? ''),
                        gender: $gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender),
                        consent: $consent,
                        type: $type,
                        freshReason: $data['fresh_reason'] ?? null,
                        journeySession: $record instanceof JourneySession ? $record : ($record instanceof CibilCreditRequest ? $record->journeySession : null),
                        previousRequest: $isFollowUp ? $record : null,
                    ), self::actor());
                });
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function requestSchema(?Model $record, CibilRequestType $type, bool $pickCustomer): array
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
                        ->mapWithKeys(fn (Customer $customer) => [$customer->id => trim(($customer->full_name ?: 'Unnamed').' — '.($customer->email ?? $customer->phone ?? ''), ' —')])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->full_name)
                    ->helperText('Requests are always linked to a customer record. Someone who has not applied on the website yet? Add them first under Customers → Add customer.'),
            ] : []),
            Section::make('Customer details sent to CIBIL')
                ->description('Use the name exactly as on the customer\'s PAN card. CIBIL also requires the gender.')
                ->components([
                    Grid::make(2)->schema([
                        TextInput::make('full_name')->label('Full name (as on PAN card)')->required()->maxLength(150)->columnSpanFull(),
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
                        Select::make('gender')
                            ->label('Gender')
                            ->options(Gender::class)
                            ->required(),
                    ]),
                ]),
            CreditConsentFields::section(
                fn (Get $get): ?Customer => $fixedCustomer ?? Customer::query()->find($get('customer_id')),
                CibilSettings::consentValidityDays(),
                'CIBIL credit report',
            ),
            ...($type === CibilRequestType::Fresh ? [
                Textarea::make('fresh_reason')
                    ->label('Why is a fresh pull needed?')
                    ->required()
                    ->minLength(15)
                    ->maxLength(1000),
            ] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(Model $record): array
    {
        $customer = self::customerOf($record);
        $session = $record instanceof JourneySession ? $record : null;
        $previous = $record instanceof CibilCreditRequest ? $record : $customer?->cibilRequests()->whereNotNull('pan_encrypted')->latest('id')->first();
        $journeyPan = $session?->creditConsent?->pan_number;
        $preferredConsent = $record instanceof CibilCreditRequest ? $record->credit_consent_id : $session?->creditConsent?->id;

        return [
            'full_name' => $previous?->full_name ?? $customer?->full_name,
            'mobile' => $previous?->mobile ?? $session?->phone_number ?? $customer?->phone,
            'pan' => $previous?->pan_encrypted ?? $journeyPan,
            'gender' => $previous?->gender?->value,
            'consent_source' => CreditConsentFields::defaultSource($customer, CibilSettings::consentValidityDays(), $preferredConsent),
            'consented_at' => now(),
        ];
    }

    /**
     * @param  Closure(): CibilOutcome  $request
     */
    private static function submit(Action $action, Closure $request): void
    {
        try {
            $outcome = $request();
        } catch (CibilRequestRefused|AuthorizationException $exception) {
            self::refuse($action, $exception);

            return;
        }

        $notification = Notification::make()->body($outcome->message());

        match ($outcome->kind) {
            CibilOutcome::QUEUED => $notification->title('CIBIL request sent')->success(),
            CibilOutcome::REUSED => $notification->title('Existing CIBIL result shown — no new charge')->info(),
            default => $notification->title('Request already in progress — no new charge')->warning(),
        };

        $notification->send();
        $action->redirect(CibilCreditRequestResource::getUrl('view', ['record' => $outcome->request]));
    }

    private static function refuse(Action $action, CibilRequestRefused|AuthorizationException $exception): void
    {
        Notification::make()
            ->title('Not done')
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function actionName(CibilProduct $product): string
    {
        return match ($product) {
            CibilProduct::Report => 'fetchCibilReport',
            CibilProduct::Score => 'checkCibilScore',
            CibilProduct::Pdf => 'fetchCibilPdf',
        };
    }

    private static function icon(CibilProduct $product): Heroicon
    {
        return match ($product) {
            CibilProduct::Report => Heroicon::OutlinedDocumentMagnifyingGlass,
            CibilProduct::Score => Heroicon::OutlinedChartBar,
            CibilProduct::Pdf => Heroicon::OutlinedDocumentArrowDown,
        };
    }

    private static function latestRelevant(?Customer $customer): ?CibilCreditRequest
    {
        if ($customer === null) {
            return null;
        }

        return $customer->cibilRequests()->inFlight()->latest('id')->first()
            ?? $customer->cibilRequests()->reusableFor(CibilProduct::Score)->latest('completed_at')->latest('id')->first();
    }

    private static function customerOf(?Model $record): ?Customer
    {
        return match (true) {
            $record instanceof Customer => $record,
            $record instanceof JourneySession, $record instanceof CibilCreditRequest => $record->customer,
            default => null,
        };
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private static function actor(): User
    {
        return self::user() ?? throw new AuthorizationException('Sign in again to request CIBIL reports.');
    }
}
