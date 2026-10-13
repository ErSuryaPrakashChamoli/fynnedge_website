<?php

namespace App\Filament\Resources\LenderProductRates\Schemas;

use App\Enums\LenderStatus;
use App\Enums\RateType;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Enums\RepaymentMethod;
use App\Models\LenderProduct;
use App\Models\LenderProductRate;
use App\Models\LoanProduct;
use App\Support\Rates\RatePublicationRules;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class LenderProductRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Lender offer')
                    ->description('Pick the loan type, then the bank or NBFC. Each lender can have its own rate for every loan type.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('loan_product')
                            ->label('Loan type')
                            ->options(fn () => LoanProduct::query()->orderedForDisplay()->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            ->disabledOn('edit')
                            ->afterStateHydrated(function (Select $component, ?LenderProductRate $record): void {
                                if ($record?->lenderProduct) {
                                    $component->state($record->lenderProduct->loan_product_id);
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('lender_product_id', null);

                                if ($category = LoanProduct::query()->find($state)?->category) {
                                    $set('rate_unit', $category->rateUnit()->value);
                                }
                            }),
                        Select::make('lender_product_id')
                            ->label('Bank / NBFC')
                            ->options(fn (Get $get): array => self::offerOptions($get('loan_product')))
                            ->getOptionLabelUsing(fn ($value): ?string => LenderProduct::query()->with('lender')->find($value)?->lender?->name)
                            ->searchable()
                            ->required()
                            ->live()
                            ->disabledOn('edit')
                            ->helperText('Lenders are listed once you pick a loan type. An offer marked "inactive" is hidden on the site whatever its rate.'),
                        TextInput::make('variant')
                            ->label('Product variant')
                            ->maxLength(255)
                            ->placeholder('e.g. Xpress Credit, Green Car Loan')
                            ->helperText('Leave blank for the lender\'s standard product.'),
                        TextInput::make('borrower_segment')
                            ->label('Only for these borrowers')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->placeholder('e.g. Women borrowers, Defence salary account holders')
                            ->helperText('Leave blank if any eligible borrower can get this rate. A segment-only rate is shown with this condition and never sets the product\'s "from X%" headline.'),
                    ]),

                Section::make('Interest rate')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        Select::make('rate_unit')
                            ->label('Quoted as')
                            ->options(RateUnit::class)
                            ->default(RateUnit::PerAnnum->value)
                            ->required()
                            ->live()
                            ->helperText('Credit cards: % per month (the finance charge). Loans: % per annum.'),
                        TextInput::make('rate_min')
                            ->label('Starting rate')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): float => self::unit($get)->maximum())
                            ->suffix('%')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('The lowest rate the lender publishes for this product.'),
                        TextInput::make('rate_max')
                            ->label('Maximum rate')
                            ->numeric()
                            ->step(0.01)
                            ->maxValue(fn (Get $get): float => self::unit($get)->maximum())
                            ->gte('rate_min')
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->helperText('Leave blank if the lender only publishes a starting rate.'),
                        Select::make('rate_type')
                            ->options(RateType::class),
                        Select::make('repayment_method')
                            ->options(RepaymentMethod::class)
                            ->default(RepaymentMethod::ReducingBalance->value),
                        TextInput::make('benchmark')
                            ->maxLength(255)
                            ->placeholder('e.g. EBLR + 2.00%')
                            ->helperText('For floating rates: the benchmark the lender links to.'),
                    ]),

                Section::make('Promotion')
                    ->description('Optional. A limited-time rate shown as an offer badge only while it runs; the standard rate above stays the lender\'s published rate.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
                    ->components([
                        TextInput::make('promo_rate')
                            ->label('Promotional rate')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->maxValue(fn (Get $get): float => self::unit($get)->maximum())
                            ->suffix('%')
                            ->live(onBlur: true),
                        TextInput::make('promo_label')
                            ->label('Offer name')
                            ->maxLength(255)
                            ->placeholder('e.g. Festive offer')
                            ->requiredWith('promo_rate')
                            ->live(onBlur: true),
                        DateTimePicker::make('promo_starts_at')
                            ->label('Starts')
                            ->timezone(LenderProductRate::TIMEZONE)
                            ->seconds(false),
                        DateTimePicker::make('promo_ends_at')
                            ->label('Ends')
                            ->timezone(LenderProductRate::TIMEZONE)
                            ->seconds(false)
                            ->requiredWith('promo_rate')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($value) && filled($get('promo_starts_at')) && strtotime((string) $value) <= strtotime((string) $get('promo_starts_at'))) {
                                    $fail('The promotion must end after it starts.');
                                }
                            })
                            ->helperText('Required with a promotional rate. The badge disappears at this time automatically.'),
                    ]),

                Section::make('Validity')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        DatePicker::make('effective_from')
                            ->label('Valid from')
                            ->helperText('Leave blank to show it as soon as it is published.'),
                        DatePicker::make('effective_until')
                            ->label('Valid until')
                            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (filled($value) && filled($get('effective_from')) && strtotime((string) $value) < strtotime((string) $get('effective_from'))) {
                                    $fail('"Valid until" must be on or after "Valid from".');
                                }
                            })
                            ->helperText('After this date the rate is no longer shown and the lender\'s row reads "Check current rates".'),
                    ]),

                Section::make('Evidence')
                    ->description('Every public rate must be traceable to the lender\'s own published rate schedule. Comparison sites (BankBazaar, Paisabazaar…) are not evidence.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('source_url')
                            ->label('Official source link')
                            ->url()
                            ->maxLength(2048)
                            ->live(onBlur: true)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! RatePublicationRules::isOfficialSourceUrl($value)) {
                                    $fail('Use the full https:// link to the lender\'s own page.');
                                }
                            })
                            ->columnSpanFull(),
                        Textarea::make('source_notes')
                            ->label('What the source says')
                            ->rows(3)
                            ->helperText('Paste the exact sentence or table row that states the rate, so a reviewer can check it quickly.')
                            ->columnSpanFull(),
                        DatePicker::make('last_verified_at')
                            ->label('Last checked on')
                            ->default(fn () => LenderProductRate::today()->toDateString())
                            ->maxDate(fn () => LenderProductRate::today())
                            ->live(),
                        Select::make('verification_status')
                            ->options(RateVerificationStatus::class)
                            ->default(RateVerificationStatus::Unverified->value)
                            ->required()
                            ->live()
                            ->helperText('Choose "Verified" only after you have read the rate on the lender\'s own website on the date above.'),
                    ]),

                Section::make('Customer-facing notes')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Textarea::make('eligibility_notes')
                            ->label('Who gets this rate')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('e.g. Credit score 750+, salaried with a listed employer'),
                        Textarea::make('disclaimer')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('e.g. Rate linked to EBLR; may change when the benchmark changes.'),
                        Toggle::make('is_featured')
                            ->label('Featured lender offer')
                            ->helperText('Shows a "Featured" badge in the comparison table.'),
                        TextInput::make('display_priority')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(0)
                            ->helperText('Higher shows first when a lender has several rates of the same level.'),
                    ]),

                Section::make('Preview')
                    ->description('How this rate will read on the website, and what still blocks it from going live.')
                    ->columnSpanFull()
                    ->components([
                        Text::make(fn (Get $get) => RatePreview::render([
                            'lender_product_id' => $get('lender_product_id'),
                            'borrower_segment' => $get('borrower_segment'),
                            'rate_min' => $get('rate_min'),
                            'rate_max' => $get('rate_max'),
                            'rate_unit' => $get('rate_unit'),
                            'promo_rate' => $get('promo_rate'),
                            'promo_label' => $get('promo_label'),
                            'promo_starts_at' => $get('promo_starts_at'),
                            'promo_ends_at' => $get('promo_ends_at'),
                            'effective_from' => $get('effective_from'),
                            'effective_until' => $get('effective_until'),
                            'source_url' => $get('source_url'),
                            'last_verified_at' => $get('last_verified_at'),
                            'verification_status' => $get('verification_status'),
                        ])),
                    ]),
            ]);
    }

    /**
     * The chosen loan type's lender offers, labelled with the lender name and
     * flagged when the offer itself is switched off.
     *
     * @return array<int, string>
     */
    private static function offerOptions(mixed $loanProductId): array
    {
        if (blank($loanProductId)) {
            return [];
        }

        return LenderProduct::query()
            ->with('lender')
            ->where('loan_product_id', $loanProductId)
            ->get()
            ->sortBy(fn (LenderProduct $offer) => $offer->lender?->name)
            ->mapWithKeys(fn (LenderProduct $offer) => [
                $offer->id => $offer->lender?->name.($offer->status === LenderStatus::Inactive ? ' (inactive offer)' : ''),
            ])
            ->all();
    }

    private static function unit(Get $get): RateUnit
    {
        $unit = $get('rate_unit');

        return $unit instanceof RateUnit ? $unit : (RateUnit::tryFrom((string) $unit) ?? RateUnit::PerAnnum);
    }
}
