<?php

namespace App\Filament\Schemas;

use App\Modules\CreditBureau\DataTransferObjects\CreditConsentInput;
use App\Modules\CreditBureau\Enums\ConsentMethod;
use App\Modules\CreditBureau\Enums\ConsentStatus;
use App\Modules\CreditBureau\Models\CreditConsent;
use App\Modules\Customers\Models\Customer;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The "Customer consent" part of a bureau request form, for any bureau module:
 * pick a consent the customer already gave (website journey or recorded
 * earlier, still inside the validity period), or record one now with its
 * method, time, evidence reference and an attestation. The rules themselves
 * live in App\Modules\CreditBureau\Services\CreditConsentGate; this is only the form.
 */
final class CreditConsentFields
{
    public const RECORD_NEW = 'record';

    /**
     * @param  Closure(Get): ?Customer  $customer  The customer the form is for.
     */
    public static function section(Closure $customer, int $validityDays, string $reportName): Section
    {
        return Section::make('Customer consent')
            ->description("A {$reportName} may only be requested with the customer's consent, given within the last {$validityDays} days.")
            ->components([
                Select::make('consent_source')
                    ->label('Consent')
                    ->required()
                    ->live()
                    ->options(fn (Get $get): array => self::options($customer($get), $validityDays)),
                Grid::make(2)
                    ->visible(fn (Get $get): bool => $get('consent_source') === self::RECORD_NEW)
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
                            ->label("I confirm the customer consented to FynnEdge obtaining their {$reportName} for this application, and the evidence above is on file.")
                            ->accepted()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Consents still valid for this customer, newest first, then "record new".
     *
     * @return array<int|string, string>
     */
    public static function options(?Customer $customer, int $validityDays): array
    {
        $options = $customer === null ? [] : CreditConsent::query()
            ->where('status', ConsentStatus::Given)
            ->where('consented_at', '>=', now()->subDays($validityDays))
            ->where(fn ($query) => $query
                ->where('customer_id', $customer->id)
                ->orWhereHas('journeySession', fn ($query) => $query->where('customer_id', $customer->id)))
            ->latest('consented_at')
            ->get()
            ->mapWithKeys(fn (CreditConsent $consent) => [$consent->id => self::label($consent)])
            ->all();

        // `+`, not spread: spreading re-numbers the integer consent ids.
        return $options + [self::RECORD_NEW => 'Record new consent now'];
    }

    /**
     * The preferred option: $preferred when still valid, else the newest valid
     * consent, else "record new".
     */
    public static function defaultSource(?Customer $customer, int $validityDays, ?int $preferred = null): int|string
    {
        $options = self::options($customer, $validityDays);

        if ($preferred !== null && array_key_exists($preferred, $options)) {
            return $preferred;
        }

        return collect($options)->keys()->first(fn ($key) => $key !== self::RECORD_NEW) ?? self::RECORD_NEW;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function toInput(array $data): ?CreditConsentInput
    {
        $source = $data['consent_source'] ?? null;

        if ($source !== self::RECORD_NEW) {
            $consent = CreditConsent::query()->find($source);

            return $consent ? CreditConsentInput::existing($consent) : null;
        }

        $method = $data['consent_method'] ?? null;
        $method = $method instanceof ConsentMethod ? $method : ConsentMethod::tryFrom((string) $method);

        return $method === null ? null : CreditConsentInput::recordedByEmployee(
            $method,
            $data['evidence_reference'] ?? null,
            filled($data['consented_at'] ?? null) ? Carbon::parse($data['consented_at']) : null,
            (bool) ($data['attested'] ?? false),
        );
    }

    private static function label(CreditConsent $consent): string
    {
        $when = $consent->consented_at?->format('d M Y');

        return $consent->method === ConsentMethod::OnlineJourney || $consent->method === null
            ? "Website application consent, {$when} ({$consent->purpose})"
            : "{$consent->method->getLabel()}, {$when} — ref. {$consent->evidence_reference}";
    }
}
