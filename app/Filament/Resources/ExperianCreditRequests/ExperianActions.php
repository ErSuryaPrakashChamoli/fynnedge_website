<?php

namespace App\Filament\Resources\ExperianCreditRequests;

use App\Filament\Schemas\CreditConsentFields;
use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Experian\Actions\FetchExperianPdf;
use App\Modules\Experian\Actions\ReconcileExperianRequest;
use App\Modules\Experian\DataTransferObjects\ExperianCheckRequest;
use App\Modules\Experian\DataTransferObjects\ExperianOutcome;
use App\Modules\Experian\Enums\ExperianProduct;
use App\Modules\Experian\Enums\ExperianReconciliationResolution;
use App\Modules\Experian\Enums\ExperianRequestStatus;
use App\Modules\Experian\Enums\ExperianRequestType;
use App\Modules\Experian\Exceptions\ExperianRequestRefused;
use App\Modules\Experian\Models\ExperianCreditRequest;
use App\Modules\Experian\Services\ExperianCreditService;
use App\Modules\Experian\Services\ExperianSettings;
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
 * The Experian buttons, shared by the Experian list and request pages and by
 * the Customer and Application screens (as an "Experian" menu), so every entry
 * point offers the same workflow. Visibility only tidies the UI: the Experian
 * module (RequestExperianCheck, ReconcileExperianRequest, FetchExperianPdf)
 * re-checks the permission, consent and state, and its refusals are shown to
 * the employee.
 */
final class ExperianActions
{
    /**
     * The "Experian" menu on a customer or application page.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            self::viewLatest(),
            self::request(ExperianProduct::Report),
            self::request(ExperianProduct::Score),
            self::request(ExperianProduct::Pdf),
            self::fetchFresh(),
        ])
            ->label('Experian')
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->color('gray')
            ->button();
    }

    public static function request(ExperianProduct $product): Action
    {
        return self::requestAction(self::actionName($product), $product, ExperianRequestType::Standard)
            ->label(match ($product) {
                ExperianProduct::Report => 'Fetch Experian Report',
                ExperianProduct::Score => 'Check Experian Score',
                ExperianProduct::Pdf => 'Get Experian PDF Report',
            })
            ->icon(self::icon($product))
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null && (bool) self::user()?->can('create', ExperianCreditRequest::class));
    }

    /**
     * Opens the customer's unresolved request, else their newest reusable result.
     */
    public static function viewLatest(): Action
    {
        return Action::make('viewLatestExperian')
            ->label(fn (Model $record): string => self::latestRelevant(self::customerOf($record))?->status === ExperianRequestStatus::Success
                ? 'View Latest Experian Result'
                : 'View Experian Request in Progress')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (Model $record): bool => self::latestRelevant(self::customerOf($record)) !== null
                && (bool) self::user()?->can('viewAny', ExperianCreditRequest::class))
            ->url(fn (Model $record): string => ExperianCreditRequestResource::getUrl('view', ['record' => self::latestRelevant(self::customerOf($record))]));
    }

    /**
     * An authorised second paid report pull while a reusable one exists.
     */
    public static function fetchFresh(): Action
    {
        return self::requestAction('fetchFreshExperianReport', ExperianProduct::Report, ExperianRequestType::Fresh)
            ->label('Fetch Fresh Experian Report')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Model $record): bool => ($customer = self::customerOf($record)) !== null
                && $customer->experianRequests()->reusableFor(ExperianProduct::Report)->exists()
                && (bool) self::user()?->can('fetchFresh', ExperianCreditRequest::class));
    }

    public static function fetchFreshFromRequest(): Action
    {
        return self::requestAction('fetchFreshExperian', null, ExperianRequestType::Fresh)
            ->label('Fetch fresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (ExperianCreditRequest $record): bool => $record->isReusable()
                && $record->customer !== null
                && (bool) self::user()?->can('fetchFresh', ExperianCreditRequest::class));
    }

    /**
     * From a score or report: the same person's PDF — a separate paid request,
     * answered by a recent stored PDF when there is one.
     */
    public static function requestPdfFromRequest(): Action
    {
        return self::requestAction('requestExperianPdf', ExperianProduct::Pdf, ExperianRequestType::Standard)
            ->label('Get PDF report')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (ExperianCreditRequest $record): bool => $record->product !== ExperianProduct::Pdf
                && $record->customer !== null
                && (bool) self::user()?->can('create', ExperianCreditRequest::class)
                && (bool) self::user()?->can('downloadPdf', $record));
    }

    /**
     * The Experian list's buttons: pick the customer, then the same form.
     */
    public static function requestForCustomer(ExperianProduct $product): Action
    {
        return self::requestAction(self::actionName($product), $product, ExperianRequestType::Standard, pickCustomer: true)
            ->label(match ($product) {
                ExperianProduct::Report => 'Fetch Experian report',
                ExperianProduct::Score => 'Check Experian score',
                ExperianProduct::Pdf => 'Get Experian PDF report',
            })
            ->icon(self::icon($product))
            ->color($product === ExperianProduct::Report ? 'primary' : 'gray')
            ->visible(fn (): bool => (bool) self::user()?->can('create', ExperianCreditRequest::class));
    }

    public static function retry(): Action
    {
        return Action::make('retryExperian')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (ExperianCreditRequest $record): bool => $record->status === ExperianRequestStatus::Failed
                && $record->retryable
                && ! ExperianCreditRequest::query()->where('previous_request_id', $record->id)->exists()
                && (bool) self::user()?->can('create', ExperianCreditRequest::class))
            ->requiresConfirmation()
            ->modalHeading('Retry this Experian request?')
            ->modalDescription(fn (ExperianCreditRequest $record): string => 'Surepass did not process the previous attempt ('.($record->reconciliation_resolution?->getLabel() ?? $record->error_code?->getLabel() ?? 'failed').'), so it was not charged. This sends the same details again as a new paid request — unless a usable result for this PAN has arrived in the meantime, which is shown instead.')
            ->action(fn (ExperianCreditRequest $record, Action $action) => self::submit(
                $action,
                fn () => app(ExperianCreditService::class)->request(ExperianCheckRequest::retryOf($record), self::actor()),
            ));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcileExperian')
            ->label('Reconcile')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (ExperianCreditRequest $record): bool => $record->status === ExperianRequestStatus::ReconciliationRequired
                && (bool) self::user()?->can('reconcile', $record))
            ->modalHeading('Reconcile this Experian request')
            ->schema(fn (ExperianCreditRequest $record): array => [
                Callout::make('Check Surepass before deciding')
                    ->description('This request ended without a usable answer ('.($record->error_code?->getLabel() ?? 'unknown').'), so Surepass may have charged for it. Look it up in the Surepass dashboard usage log around '.$record->requested_at?->format('d M Y, H:i').' UTC (or ask Surepass support) and record what you found. Nothing is sent to Surepass from here.')
                    ->warning(),
                Radio::make('resolution')
                    ->label('What did Surepass confirm?')
                    ->options(ExperianReconciliationResolution::class)
                    ->required(),
                Textarea::make('note')
                    ->label('How was this confirmed?')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000),
            ])
            ->action(function (ExperianCreditRequest $record, array $data, Action $action): void {
                try {
                    $resolution = $data['resolution'] instanceof ExperianReconciliationResolution
                        ? $data['resolution']
                        : ExperianReconciliationResolution::from($data['resolution']);

                    app(ReconcileExperianRequest::class)->handle($record, $resolution, (string) $data['note'], self::actor());
                } catch (ExperianRequestRefused|AuthorizationException $exception) {
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
        return Action::make('downloadExperianPdf')
            ->label('Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (ExperianCreditRequest $record): bool => app(FetchExperianPdf::class)->isAvailable($record) && (bool) self::user()?->can('downloadPdf', $record))
            ->action(function (ExperianCreditRequest $record, Action $action): ?StreamedResponse {
                try {
                    $pdf = app(FetchExperianPdf::class)->handle($record, self::actor());
                } catch (ExperianRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return null;
                }

                return response()->streamDownload(
                    function () use ($pdf): void {
                        echo $pdf;
                    },
                    'experian-report-'.$record->pan_masked.'-'.$record->completed_at?->format('Ymd').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function viewRawJson(): Action
    {
        return Action::make('viewRawExperianJson')
            ->label('Raw JSON')
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->visible(fn (ExperianCreditRequest $record): bool => is_array($record->raw_response) && (bool) self::user()?->can('viewRaw', $record))
            ->modalHeading('Complete Surepass response')
            ->modalDescription('Unmasked and exactly as received. Opening this is recorded in the request\'s history.')
            ->modalWidth('7xl')
            ->mountUsing(fn (ExperianCreditRequest $record) => $record->recordEvent('raw_viewed'))
            ->modalContent(fn (ExperianCreditRequest $record) => view('filament.experian.raw-json', [
                'json' => json_encode($record->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private static function requestAction(string $name, ?ExperianProduct $product, ExperianRequestType $type, bool $pickCustomer = false): Action
    {
        $productOf = fn (?Model $record): ExperianProduct => $product ?? ($record instanceof ExperianCreditRequest ? $record->product : ExperianProduct::Report);

        return Action::make($name)
            ->modalHeading(fn (?Model $record): string => ($type === ExperianRequestType::Fresh ? 'Fetch a fresh Experian ' : 'Experian ').match ($productOf($record)) {
                ExperianProduct::Report => 'credit report',
                ExperianProduct::Score => 'credit score',
                ExperianProduct::Pdf => 'PDF credit report',
            })
            ->modalDescription(fn (?Model $record): string => $type === ExperianRequestType::Fresh
                ? 'A reusable result already exists, so this is a SECOND paid pull. Use it only when a fresh result is genuinely required (e.g. a lender needs one dated today). The reason is recorded.'
                : 'Each new request is a paid Surepass call. If this PAN already has a usable Experian result (scores: '.ExperianSettings::scoreReuseDays().' days, reports and PDFs: '.ExperianSettings::reportReuseDays().' days), or a request in progress, that is shown instead and nothing is charged.')
            ->modalWidth('3xl')
            ->fillForm(fn (?Model $record): array => $record === null
                ? ['consent_source' => CreditConsentFields::RECORD_NEW, 'consented_at' => now()]
                : self::defaults($record))
            ->schema(fn (?Model $record): array => self::requestSchema($record, $type, $pickCustomer))
            ->action(function (?Model $record, array $data, Action $action) use ($type, $pickCustomer, $productOf, $product): void {
                self::submit($action, function () use ($record, $data, $type, $pickCustomer, $productOf, $product): ExperianOutcome {
                    $customer = $pickCustomer ? Customer::query()->find($data['customer_id'] ?? null) : self::customerOf($record);
                    $consent = CreditConsentFields::toInput($data);

                    if ($customer === null) {
                        throw new ExperianRequestRefused('Choose the customer this request is for.');
                    }

                    if ($consent === null) {
                        throw new ExperianRequestRefused('Choose the customer\'s consent, or record new consent.');
                    }

                    // A PDF asked for from a score or report is a new request, not a follow-up of it.
                    $isFollowUp = $record instanceof ExperianCreditRequest && ($product === null || $product === $record->product);

                    return app(ExperianCreditService::class)->request(new ExperianCheckRequest(
                        customer: $customer,
                        product: $productOf($record),
                        name: (string) ($data['full_name'] ?? ''),
                        mobile: (string) ($data['mobile'] ?? ''),
                        pan: (string) ($data['pan'] ?? ''),
                        consent: $consent,
                        type: $type,
                        freshReason: $data['fresh_reason'] ?? null,
                        journeySession: $record instanceof JourneySession ? $record : ($record instanceof ExperianCreditRequest ? $record->journeySession : null),
                        previousRequest: $isFollowUp ? $record : null,
                    ), self::actor());
                });
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function requestSchema(?Model $record, ExperianRequestType $type, bool $pickCustomer): array
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
            Section::make('Customer details sent to Experian')
                ->description('Use the name exactly as on the customer\'s PAN card.')
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
                    ]),
                ]),
            CreditConsentFields::section(
                fn (Get $get): ?Customer => $fixedCustomer ?? Customer::query()->find($get('customer_id')),
                ExperianSettings::consentValidityDays(),
                'Experian credit report',
            ),
            ...($type === ExperianRequestType::Fresh ? [
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
        $previous = $record instanceof ExperianCreditRequest ? $record : $customer?->experianRequests()->whereNotNull('pan_encrypted')->latest('id')->first();
        $journeyPan = $session?->creditConsent?->pan_number;
        $preferredConsent = $record instanceof ExperianCreditRequest ? $record->credit_consent_id : $session?->creditConsent?->id;

        return [
            'full_name' => $previous?->full_name ?? $customer?->full_name,
            'mobile' => $previous?->mobile ?? $session?->phone_number ?? $customer?->phone,
            'pan' => $previous?->pan_encrypted ?? $journeyPan,
            'consent_source' => CreditConsentFields::defaultSource($customer, ExperianSettings::consentValidityDays(), $preferredConsent),
            'consented_at' => now(),
        ];
    }

    /**
     * @param  Closure(): ExperianOutcome  $request
     */
    private static function submit(Action $action, Closure $request): void
    {
        try {
            $outcome = $request();
        } catch (ExperianRequestRefused|AuthorizationException $exception) {
            self::refuse($action, $exception);

            return;
        }

        $notification = Notification::make()->body($outcome->message());

        match ($outcome->kind) {
            ExperianOutcome::QUEUED => $notification->title('Experian request sent')->success(),
            ExperianOutcome::REUSED => $notification->title('Existing Experian result shown — no new charge')->info(),
            default => $notification->title('Request already in progress — no new charge')->warning(),
        };

        $notification->send();
        $action->redirect(ExperianCreditRequestResource::getUrl('view', ['record' => $outcome->request]));
    }

    private static function refuse(Action $action, ExperianRequestRefused|AuthorizationException $exception): void
    {
        Notification::make()
            ->title('Not done')
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function actionName(ExperianProduct $product): string
    {
        return match ($product) {
            ExperianProduct::Report => 'fetchExperianReport',
            ExperianProduct::Score => 'checkExperianScore',
            ExperianProduct::Pdf => 'fetchExperianPdf',
        };
    }

    private static function icon(ExperianProduct $product): Heroicon
    {
        return match ($product) {
            ExperianProduct::Report => Heroicon::OutlinedDocumentMagnifyingGlass,
            ExperianProduct::Score => Heroicon::OutlinedChartBar,
            ExperianProduct::Pdf => Heroicon::OutlinedDocumentArrowDown,
        };
    }

    private static function latestRelevant(?Customer $customer): ?ExperianCreditRequest
    {
        if ($customer === null) {
            return null;
        }

        return $customer->experianRequests()->inFlight()->latest('id')->first()
            ?? $customer->experianRequests()->reusableFor(ExperianProduct::Score)->latest('completed_at')->latest('id')->first();
    }

    private static function customerOf(?Model $record): ?Customer
    {
        return match (true) {
            $record instanceof Customer => $record,
            $record instanceof JourneySession, $record instanceof ExperianCreditRequest => $record->customer,
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
        return self::user() ?? throw new AuthorizationException('Sign in again to request Experian reports.');
    }
}
