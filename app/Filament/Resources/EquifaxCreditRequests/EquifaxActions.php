<?php

namespace App\Filament\Resources\EquifaxCreditRequests;

use App\Filament\Schemas\CreditConsentFields;
use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Actions\FetchEquifaxPdf;
use App\Modules\Equifax\Actions\ReconcileEquifaxRequest;
use App\Modules\Equifax\DataTransferObjects\EquifaxCheckRequest;
use App\Modules\Equifax\DataTransferObjects\EquifaxOutcome;
use App\Modules\Equifax\Enums\EquifaxProduct;
use App\Modules\Equifax\Enums\EquifaxReconciliationResolution;
use App\Modules\Equifax\Enums\EquifaxRequestStatus;
use App\Modules\Equifax\Enums\EquifaxRequestType;
use App\Modules\Equifax\Enums\Gender;
use App\Modules\Equifax\Exceptions\EquifaxRequestRefused;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Equifax\Services\EquifaxCreditService;
use App\Modules\Equifax\Services\EquifaxReportDocument;
use App\Modules\Equifax\Services\EquifaxSettings;
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
 * The Equifax buttons, shared by the Equifax list and request pages and by the
 * Customer and Application screens (as an "Equifax" menu), so every entry point
 * offers the same workflow. Visibility only tidies the UI: the Equifax module
 * (RequestEquifaxCheck, ReconcileEquifaxRequest, FetchEquifaxPdf) re-checks the
 * permission, consent and state, and its refusals are shown to the employee.
 */
final class EquifaxActions
{
    /**
     * The "Equifax" menu on a customer or application page.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            self::viewLatest(),
            self::request(EquifaxProduct::Report),
            self::request(EquifaxProduct::Score),
            self::fetchFresh(),
        ])
            ->label('Equifax')
            ->icon(Heroicon::OutlinedDocumentChartBar)
            ->color('gray')
            ->button();
    }

    public static function request(EquifaxProduct $product): Action
    {
        return self::requestAction($product === EquifaxProduct::Report ? 'fetchEquifaxReport' : 'checkEquifaxScore', $product, EquifaxRequestType::Standard)
            ->label($product === EquifaxProduct::Report ? 'Fetch Equifax Report' : 'Check Equifax Score')
            ->icon($product === EquifaxProduct::Report ? Heroicon::OutlinedDocumentMagnifyingGlass : Heroicon::OutlinedChartBar)
            ->visible(fn (Model $record): bool => self::customerOf($record) !== null && (bool) self::user()?->can('create', EquifaxCreditRequest::class));
    }

    /**
     * Opens the customer's unresolved request, else their newest reusable result.
     */
    public static function viewLatest(): Action
    {
        return Action::make('viewLatestEquifax')
            ->label(fn (Model $record): string => self::latestRelevant(self::customerOf($record))?->status === EquifaxRequestStatus::Success
                ? 'View Latest Equifax Result'
                : 'View Equifax Request in Progress')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (Model $record): bool => self::latestRelevant(self::customerOf($record)) !== null
                && (bool) self::user()?->can('viewAny', EquifaxCreditRequest::class))
            ->url(fn (Model $record): string => EquifaxCreditRequestResource::getUrl('view', ['record' => self::latestRelevant(self::customerOf($record))]));
    }

    /**
     * An authorised second paid report pull while a reusable one exists.
     */
    public static function fetchFresh(): Action
    {
        return self::requestAction('fetchFreshEquifaxReport', EquifaxProduct::Report, EquifaxRequestType::Fresh)
            ->label('Fetch Fresh Equifax Report')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Model $record): bool => ($customer = self::customerOf($record)) !== null
                && $customer->equifaxRequests()->reusableFor(EquifaxProduct::Report)->exists()
                && (bool) self::user()?->can('fetchFresh', EquifaxCreditRequest::class));
    }

    public static function fetchFreshFromRequest(): Action
    {
        return self::requestAction('fetchFreshEquifax', null, EquifaxRequestType::Fresh)
            ->label('Fetch fresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (EquifaxCreditRequest $record): bool => $record->isReusable()
                && $record->customer !== null
                && (bool) self::user()?->can('fetchFresh', EquifaxCreditRequest::class));
    }

    /**
     * The Equifax list's buttons: pick the customer, then the same form.
     */
    public static function requestForCustomer(EquifaxProduct $product): Action
    {
        return self::requestAction($product === EquifaxProduct::Report ? 'fetchEquifaxReport' : 'checkEquifaxScore', $product, EquifaxRequestType::Standard, pickCustomer: true)
            ->label($product === EquifaxProduct::Report ? 'Fetch Equifax report' : 'Check Equifax score')
            ->icon($product === EquifaxProduct::Report ? Heroicon::OutlinedDocumentMagnifyingGlass : Heroicon::OutlinedChartBar)
            ->color($product === EquifaxProduct::Report ? 'primary' : 'gray')
            ->visible(fn (): bool => (bool) self::user()?->can('create', EquifaxCreditRequest::class));
    }

    public static function retry(): Action
    {
        return Action::make('retryEquifax')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (EquifaxCreditRequest $record): bool => $record->status === EquifaxRequestStatus::Failed
                && $record->retryable
                && ! EquifaxCreditRequest::query()->where('previous_request_id', $record->id)->exists()
                && (bool) self::user()?->can('create', EquifaxCreditRequest::class))
            ->requiresConfirmation()
            ->modalHeading('Retry this Equifax request?')
            ->modalDescription(fn (EquifaxCreditRequest $record): string => 'Surepass did not process the previous attempt ('.($record->reconciliation_resolution?->getLabel() ?? $record->error_code?->getLabel() ?? 'failed').'), so it was not charged. This sends the same details again as a new paid request — unless a usable result for this PAN has arrived in the meantime, which is shown instead.')
            ->action(fn (EquifaxCreditRequest $record, Action $action) => self::submit(
                $action,
                fn () => app(EquifaxCreditService::class)->request(EquifaxCheckRequest::retryOf($record), self::actor()),
            ));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcileEquifax')
            ->label('Reconcile')
            ->icon(Heroicon::OutlinedScale)
            ->color('warning')
            ->visible(fn (EquifaxCreditRequest $record): bool => $record->status === EquifaxRequestStatus::ReconciliationRequired
                && (bool) self::user()?->can('reconcile', $record))
            ->modalHeading('Reconcile this Equifax request')
            ->schema(fn (EquifaxCreditRequest $record): array => [
                Callout::make('Check Surepass before deciding')
                    ->description('This request ended without a usable answer ('.($record->error_code?->getLabel() ?? 'unknown').'), so Surepass may have charged for it. Look it up in the Surepass dashboard usage log around '.$record->requested_at?->format('d M Y, H:i').' UTC (or ask Surepass support) and record what you found. Nothing is sent to Surepass from here.')
                    ->warning(),
                Radio::make('resolution')
                    ->label('What did Surepass confirm?')
                    ->options(EquifaxReconciliationResolution::class)
                    ->required(),
                Textarea::make('note')
                    ->label('How was this confirmed?')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000),
            ])
            ->action(function (EquifaxCreditRequest $record, array $data, Action $action): void {
                try {
                    $resolution = $data['resolution'] instanceof EquifaxReconciliationResolution
                        ? $data['resolution']
                        : EquifaxReconciliationResolution::from($data['resolution']);

                    app(ReconcileEquifaxRequest::class)->handle($record, $resolution, (string) $data['note'], self::actor());
                } catch (EquifaxRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return;
                }

                Notification::make()->title('Request reconciled')->success()->send();
            });
    }

    public static function downloadPdf(): Action
    {
        return Action::make('downloadEquifaxPdf')
            ->label(fn (EquifaxCreditRequest $record): string => self::pdfNeedsProviderCall($record) ? 'Get PDF from Surepass' : 'Download PDF')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (EquifaxCreditRequest $record): bool => $record->hasReport() && (bool) self::user()?->can('downloadPdf', $record))
            ->requiresConfirmation(fn (EquifaxCreditRequest $record): bool => self::pdfNeedsProviderCall($record))
            ->modalDescription('This report carries no usable PDF link, so Surepass\'s PDF endpoint is called once with the customer\'s details (another provider call). The PDF is stored encrypted and later downloads are served from that copy.')
            ->action(function (EquifaxCreditRequest $record, Action $action): ?StreamedResponse {
                try {
                    $pdf = app(FetchEquifaxPdf::class)->handle($record, self::actor());
                } catch (EquifaxRequestRefused|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return null;
                }

                return response()->streamDownload(
                    function () use ($pdf): void {
                        echo $pdf;
                    },
                    'equifax-report-'.$record->pan_masked.'-'.$record->completed_at?->format('Ymd').'.pdf',
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    public static function viewRawJson(): Action
    {
        return Action::make('viewRawEquifaxJson')
            ->label('Raw JSON')
            ->icon(Heroicon::OutlinedCodeBracket)
            ->color('gray')
            ->visible(fn (EquifaxCreditRequest $record): bool => is_array($record->raw_response) && (bool) self::user()?->can('viewRaw', $record))
            ->modalHeading('Complete Surepass response')
            ->modalDescription('Unmasked and exactly as received. Opening this is recorded in the request\'s history.')
            ->modalWidth('7xl')
            ->mountUsing(fn (EquifaxCreditRequest $record) => $record->recordEvent('raw_viewed'))
            ->modalContent(fn (EquifaxCreditRequest $record) => view('filament.equifax.raw-json', [
                'json' => json_encode($record->raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    private static function requestAction(string $name, ?EquifaxProduct $product, EquifaxRequestType $type, bool $pickCustomer = false): Action
    {
        $productOf = fn (?Model $record): EquifaxProduct => $product ?? ($record instanceof EquifaxCreditRequest ? $record->product : EquifaxProduct::Report);

        return Action::make($name)
            ->modalHeading(fn (?Model $record): string => ($type === EquifaxRequestType::Fresh ? 'Fetch a fresh Equifax ' : 'Equifax ').($productOf($record) === EquifaxProduct::Report ? 'credit report' : 'credit score'))
            ->modalDescription(fn (?Model $record): string => $type === EquifaxRequestType::Fresh
                ? 'A reusable result already exists, so this is a SECOND paid pull. Use it only when a fresh result is genuinely required (e.g. a lender needs one dated today). The reason is recorded.'
                : 'Each new request is a paid Surepass call. If this PAN already has a usable Equifax result (scores: '.EquifaxSettings::scoreReuseDays().' days, reports: '.EquifaxSettings::reportReuseDays().' days), or a request in progress, that is shown instead and nothing is charged.')
            ->modalWidth('3xl')
            ->fillForm(fn (?Model $record): array => $record === null
                ? ['consent_source' => CreditConsentFields::RECORD_NEW, 'consented_at' => now()]
                : self::defaults($record))
            ->schema(fn (?Model $record): array => self::requestSchema($record, $type, $pickCustomer))
            ->action(function (?Model $record, array $data, Action $action) use ($type, $pickCustomer, $productOf): void {
                self::submit($action, function () use ($record, $data, $type, $pickCustomer, $productOf): EquifaxOutcome {
                    $customer = $pickCustomer ? Customer::query()->find($data['customer_id'] ?? null) : self::customerOf($record);
                    $consent = CreditConsentFields::toInput($data);

                    if ($customer === null) {
                        throw new EquifaxRequestRefused('Choose the customer this request is for.');
                    }

                    if ($consent === null) {
                        throw new EquifaxRequestRefused('Choose the customer\'s consent, or record new consent.');
                    }

                    $gender = $data['gender'] ?? null;

                    return app(EquifaxCreditService::class)->request(new EquifaxCheckRequest(
                        customer: $customer,
                        product: $productOf($record),
                        name: (string) ($data['full_name'] ?? ''),
                        mobile: (string) ($data['mobile'] ?? ''),
                        pan: (string) ($data['pan'] ?? ''),
                        gender: $gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender),
                        consent: $consent,
                        type: $type,
                        freshReason: $data['fresh_reason'] ?? null,
                        journeySession: $record instanceof JourneySession ? $record : ($record instanceof EquifaxCreditRequest ? $record->journeySession : null),
                        previousRequest: $record instanceof EquifaxCreditRequest ? $record : null,
                    ), self::actor());
                });
            });
    }

    /**
     * @return array<int, mixed>
     */
    private static function requestSchema(?Model $record, EquifaxRequestType $type, bool $pickCustomer): array
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
            Section::make('Customer details sent to Equifax')
                ->description('Use the name exactly as on the customer\'s PAN card. Equifax also requires the gender.')
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
                EquifaxSettings::consentValidityDays(),
                'Equifax credit report',
            ),
            ...($type === EquifaxRequestType::Fresh ? [
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
        $previous = $record instanceof EquifaxCreditRequest ? $record : $customer?->equifaxRequests()->whereNotNull('pan_encrypted')->latest('id')->first();
        $journeyPan = $session?->creditConsent?->pan_number;
        $preferredConsent = $record instanceof EquifaxCreditRequest ? $record->credit_consent_id : $session?->creditConsent?->id;

        return [
            'full_name' => $previous?->full_name ?? $customer?->full_name,
            'mobile' => $previous?->mobile ?? $session?->phone_number ?? $customer?->phone,
            'pan' => $previous?->pan_encrypted ?? $journeyPan,
            'gender' => $previous?->gender?->value,
            'consent_source' => CreditConsentFields::defaultSource($customer, EquifaxSettings::consentValidityDays(), $preferredConsent),
            'consented_at' => now(),
        ];
    }

    /**
     * @param  Closure(): EquifaxOutcome  $request
     */
    private static function submit(Action $action, Closure $request): void
    {
        try {
            $outcome = $request();
        } catch (EquifaxRequestRefused|AuthorizationException $exception) {
            self::refuse($action, $exception);

            return;
        }

        $notification = Notification::make()->body($outcome->message());

        match ($outcome->kind) {
            EquifaxOutcome::QUEUED => $notification->title('Equifax request sent')->success(),
            EquifaxOutcome::REUSED => $notification->title('Existing Equifax result shown — no new charge')->info(),
            default => $notification->title('Request already in progress — no new charge')->warning(),
        };

        $notification->send();
        $action->redirect(EquifaxCreditRequestResource::getUrl('view', ['record' => $outcome->request]));
    }

    private static function refuse(Action $action, EquifaxRequestRefused|AuthorizationException $exception): void
    {
        Notification::make()
            ->title('Not done')
            ->body($exception->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function pdfNeedsProviderCall(EquifaxCreditRequest $record): bool
    {
        return ! $record->hasStoredPdf()
            && (new EquifaxReportDocument($record->raw_response ?? []))->pdfLink() === null;
    }

    private static function latestRelevant(?Customer $customer): ?EquifaxCreditRequest
    {
        if ($customer === null) {
            return null;
        }

        return $customer->equifaxRequests()->inFlight()->latest('id')->first()
            ?? $customer->equifaxRequests()->reusableFor(EquifaxProduct::Score)->latest('completed_at')->latest('id')->first();
    }

    private static function customerOf(?Model $record): ?Customer
    {
        return match (true) {
            $record instanceof Customer => $record,
            $record instanceof JourneySession, $record instanceof EquifaxCreditRequest => $record->customer,
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
        return self::user() ?? throw new AuthorizationException('Sign in again to request Equifax reports.');
    }
}
