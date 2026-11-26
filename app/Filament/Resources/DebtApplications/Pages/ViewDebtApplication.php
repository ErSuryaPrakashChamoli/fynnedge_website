<?php

namespace App\Filament\Resources\DebtApplications\Pages;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Models\LenderProduct;
use App\Models\User;
use App\Modules\DebtConsolidation\Actions\AnonymiseDebtApplication;
use App\Modules\DebtConsolidation\Actions\AssessDebtApplication;
use App\Modules\DebtConsolidation\Actions\ChangeDebtApplicationStatus;
use App\Modules\DebtConsolidation\Actions\CreateLenderApplication;
use App\Modules\DebtConsolidation\Actions\GenerateDebtReport;
use App\Modules\DebtConsolidation\Actions\MatchLenders;
use App\Modules\DebtConsolidation\Actions\RecordDebtConsent;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Enums\LenderMatchStatus;
use App\Modules\DebtConsolidation\Enums\NoteType;
use App\Modules\DebtConsolidation\Enums\ReportType;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\DebtConsolidation\Models\DebtReport;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The 360° view of one debt consolidation case. Content tabs read the newest
 * assessment snapshot; relation manager tabs work the case.
 */
class ViewDebtApplication extends ViewRecord
{
    protected static string $resource = DebtApplicationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Case')->columnSpan(2)->columns(3)->components([
                TextEntry::make('reference')->copyable()->weight('bold'),
                TextEntry::make('status')->badge(),
                TextEntry::make('applicant_type')->label('Type')->badge()->color('gray'),
                TextEntry::make('assignee.name')->label('Advisor')->placeholder('Unassigned'),
                TextEntry::make('follow_up_at')->label('Next follow-up')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->placeholder('—'),
                TextEntry::make('created_at')->label('Received')->dateTime('j M Y, g:i a', 'Asia/Kolkata'),
                TextEntry::make('submitted_at')->label('Assessment submitted')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->placeholder('Enquiry only'),
                TextEntry::make('declared_total_outstanding')->label('Declared total debt (enquiry)')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextEntry::make('declared_monthly_emi')->label('Declared EMIs (enquiry)')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextEntry::make('closure_reason')->label('Closed because')->placeholder('—')->visible(fn (DebtApplication $record): bool => ! $record->status->isOpen())->columnSpanFull(),
            ]),
            Section::make('Latest assessment')->columnSpan(1)->components([
                TextEntry::make('latestSnapshot.total_outstanding')->label('Total debt')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('Not assessed'),
                TextEntry::make('latestSnapshot.net_benefit')->label('Net benefit (recommended option)')->formatStateUsing(fn ($state): string => DebtPresenter::inr($state))->placeholder('—'),
                TextEntry::make('latestSnapshot.suitability_score')->label('Suitability score')->suffix(' / 100')->placeholder('—'),
                TextEntry::make('latestSnapshot.suitability_band')->label('Suitability')->badge()->placeholder('—'),
                TextEntry::make('latestSnapshot.created_at')->label('Calculated')->since()->placeholder('—'),
            ]),
            Section::make('Source')->columnSpanFull()->columns(4)->collapsed()->components([
                TextEntry::make('source_url')->label('Page')->placeholder('—'),
                TextEntry::make('utm_source')->label('UTM source')->placeholder('—'),
                TextEntry::make('utm_medium')->label('UTM medium')->placeholder('—'),
                TextEntry::make('utm_campaign')->label('UTM campaign')->placeholder('—'),
            ]),
        ]);
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    /**
     * The 360° tabs: content tabs built from the newest snapshot, mixed with
     * the relation managers (keyed as in DebtApplicationResource::getRelations()
     * so ?relation=… links keep working), followed by any remaining managers.
     */
    public function getRelationManagersContentComponent(): Component
    {
        $record = $this->getRecord();
        $snapshot = $record->latestSnapshot;
        $managers = $this->getCachedRelationManagers();
        $view = fn (string $name): View => View::make('filament.debt-consolidation.case-'.$name)->viewData(['record' => $record, 'snapshot' => $snapshot]);
        $manager = function (string $key, ?string $label = null) use ($managers, $record): ?Tab {
            if (! isset($managers[$key])) {
                return null;
            }

            $class = $this->normalizeRelationManagerClass($managers[$key]);
            $tab = $class::getTabComponent($record, static::class)
                ->schema(fn (): array => [Livewire::make($class, ['ownerRecord' => $record, 'pageClass' => static::class, ...$class::getDefaultProperties()])->key($class)]);

            return $label === null ? $tab : $tab->label($label);
        };

        // The full analysis is the calculation snapshot's content, so it needs the
        // snapshot permission as well as access to the case.
        $canSeeAnalysis = (bool) auth()->user()?->can('viewAny', DebtCalculationSnapshot::class);

        $tabs = [
            '' => $this->getContentTabComponent()->label('Overview'),
            'customer' => Tab::make('Customer profile')->schema([$view('customer')]),
            'liabilities' => $manager('liabilities', 'Existing liabilities'),
            'documents' => $manager('documents'),
            'assessment' => $canSeeAnalysis ? Tab::make('Financial assessment')->schema([$view('assessment')]) : null,
            'suitability' => $canSeeAnalysis ? Tab::make('Suitability score')->schema([$view('suitability')]) : null,
            'savings' => $canSeeAnalysis ? Tab::make('Savings comparison')->schema([$view('savings')]) : null,
            'foreclosure' => $canSeeAnalysis ? Tab::make('Foreclosure analysis')->schema([$view('foreclosure')]) : null,
            'matching' => $manager('matching', 'Lender matching'),
            'lender-applications' => $manager('lender-applications', 'Lender applications'),
            'closure' => $manager('closure', 'Debt closure'),
            'communication' => $manager('communication', 'Communication'),
            'timeline' => Tab::make('Activity timeline')->schema([$view('timeline')]),
            'audit' => $manager('audit', 'Audit history'),
        ];

        foreach (array_diff(array_keys($managers), array_keys($tabs)) as $key) {
            $tabs[$key] = $manager((string) $key);
        }

        return Tabs::make()
            ->key('relationManagerTabs')
            ->livewireProperty('activeRelationManager')
            ->contained(false)
            ->tabs(array_filter($tabs));
    }

    protected function getHeaderActions(): array
    {
        $can = fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord());
        // Each action also needs the permission of what it creates, so a role can work a case
        // without, say, being able to send it to lenders.
        $canAlso = fn (string $permission): Closure => fn (): bool => $can() && (bool) auth()->user()?->can($permission);

        return [
            Action::make('assess')
                ->label('Recalculate')
                ->icon('heroicon-o-calculator')
                ->visible($canAlso('Create:DebtCalculationSnapshot'))
                ->requiresConfirmation()
                ->modalDescription('Runs the engine on the case data as it is now and stores a new snapshot. Earlier snapshots and reports are kept unchanged.')
                ->action(function (): void {
                    app(AssessDebtApplication::class)->handle($this->getRecord(), ActorType::Staff, auth()->id());
                    Notification::make()->title('Assessment recalculated')->success()->send();
                }),
            Action::make('match')
                ->label('Match lenders')
                ->icon('heroicon-o-building-library')
                ->visible($canAlso('Create:DebtLenderMatch'))
                ->action(function (): void {
                    $matches = app(MatchLenders::class)->handle($this->getRecord(), auth()->id());
                    Notification::make()
                        ->title($matches->isEmpty() ? 'No lender debt consolidation policy is in force' : $matches->where('match_status', LenderMatchStatus::PotentialMatch)->count().' potential match(es) of '.$matches->count().' policies')
                        ->body($matches->isEmpty() ? 'Add lender policies in Loan Products & Lenders → Debt consolidation policies.' : 'See the Lender matching tab. A match is not an approval.')
                        ->success()->send();
                }),
            Action::make('status')
                ->label('Change status')
                ->icon('heroicon-o-arrow-path')
                ->visible($can)
                ->schema([
                    Select::make('status')->options(collect(DebtApplicationStatus::staffSelectable())->mapWithKeys(fn (DebtApplicationStatus $status): array => [$status->value => $status->getLabel()]))->required()->default(fn (): string => $this->getRecord()->status->value),
                    Textarea::make('note')->label('Note')->helperText('Required when closing a case.')->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->attempt(fn () => app(ChangeDebtApplicationStatus::class)->handle($this->getRecord(), DebtApplicationStatus::from($data['status']), auth()->id(), $data['note'] ?? null), 'Status updated')),
            ActionGroup::make([
                Action::make('assign')
                    ->label('Assign / follow-up')
                    ->icon('heroicon-o-user-plus')
                    ->visible($can)
                    ->fillForm(fn (): array => ['assigned_to' => $this->getRecord()->assigned_to, 'follow_up_at' => $this->getRecord()->follow_up_at])
                    ->schema([
                        Select::make('assigned_to')->label('Advisor')->options(fn () => User::query()->where('is_admin', true)->orderBy('name')->pluck('name', 'id'))->searchable(),
                        DateTimePicker::make('follow_up_at')->label('Next follow-up')->timezone('Asia/Kolkata'),
                    ])
                    ->action(function (array $data): void {
                        $this->getRecord()->update($data);
                        Notification::make()->title('Saved')->success()->send();
                    }),
                Action::make('note')
                    ->label('Log communication')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible($canAlso('Create:DebtNote'))
                    ->schema(self::noteSchema())
                    ->action(function (array $data): void {
                        $this->getRecord()->notes()->create([...$data, 'user_id' => auth()->id()]);
                        Notification::make()->title('Logged')->success()->send();
                    }),
                Action::make('lenderConsent')
                    ->label('Record lender-sharing consent')
                    ->icon('heroicon-o-shield-check')
                    ->visible($canAlso('Create:DebtConsent'))
                    ->modalDescription('Use only when the customer gave consent outside the website (signed form, recorded call). Customers can also consent from their profile.')
                    ->schema([
                        Select::make('lender_product_id')->label('Lender offer')->options(fn () => self::lenderProductOptions())->searchable()->required(),
                        Textarea::make('evidence')->label('How and when the customer consented')->required()->maxLength(500),
                    ])
                    ->action(function (array $data): void {
                        $application = $this->getRecord();
                        $product = LenderProduct::query()->with('lender')->findOrFail($data['lender_product_id']);
                        $consent = app(RecordDebtConsent::class)->handle($application, ConsentPurpose::LenderSharing, DebtConsent::METHOD_RECORDED_BY_STAFF, $product, recordedBy: auth()->id());
                        $application->notes()->create(['user_id' => auth()->id(), 'type' => NoteType::InternalRemark, 'subject' => 'Lender consent '.$consent->public_id, 'body' => 'Lender-sharing consent for '.$product->lender?->name.': '.$data['evidence']]);
                        Notification::make()->title('Consent recorded')->success()->send();
                    }),
                Action::make('lenderApplication')
                    ->label('New lender application')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible($canAlso('Create:DebtLenderApplication'))
                    ->schema([
                        Select::make('lender_product_id')->label('Lender offer')
                            ->options(fn () => self::lenderProductOptions($this->getRecord()->consents()->active()->where('purpose', ConsentPurpose::LenderSharing)->pluck('lender_product_id')->all()))
                            ->helperText('Only lenders the customer has consented to share their details with.')
                            ->required(),
                        TextInput::make('applied_amount')->numeric()->prefix('₹')->minValue(1),
                        Textarea::make('notes')->maxLength(1000),
                    ])
                    ->action(fn (array $data) => $this->attempt(fn () => app(CreateLenderApplication::class)->handle($this->getRecord(), LenderProduct::query()->findOrFail($data['lender_product_id']), filled($data['applied_amount']) ? (float) $data['applied_amount'] : null, auth()->id(), $data['notes'] ?? null), 'Lender application opened')),
                Action::make('report')
                    ->label('Generate report')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('create', DebtReport::class))
                    ->schema([
                        Select::make('report_type')->options(ReportType::class)->required(),
                        Toggle::make('is_customer_visible')->label('Release to the customer\'s profile')->helperText('Never available for the internal advisor assessment.'),
                    ])
                    ->action(fn (array $data) => $this->attempt(fn () => app(GenerateDebtReport::class)->handle($this->getRecord(), ReportType::from($data['report_type']), auth()->id(), (bool) $data['is_customer_visible']), 'Report generated')),
                // A customer's erasure request (Owner decision M3). Needs the case delete
                // permission, which no role holds by default.
                Action::make('anonymise')
                    ->label('Anonymise personal data')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (): bool => ! $this->getRecord()->isAnonymised() && (bool) auth()->user()?->can('delete', $this->getRecord()))
                    ->requiresConfirmation()
                    ->modalDescription('For a customer\'s erasure request. Permanently removes the name, contact details, loans, finances, documents, reports and calculations; keeps the reference, dates, consent records and status history. This cannot be undone, and is refused once a lender has received the case. A lead-inbox enquiry for this case is a separate record: handle it there.')
                    ->schema([TextInput::make('reference')->label('Erasure request reference')->required()->maxLength(150)])
                    ->action(fn (array $data) => $this->attempt(fn () => app(AnonymiseDebtApplication::class)->handle($this->getRecord(), AnonymiseDebtApplication::REASON_REQUEST, auth()->id(), $data['reference']), 'Personal data anonymised')),
            ])->label('More')->button()->icon('heroicon-o-ellipsis-horizontal'),
        ];
    }

    /**
     * @return list<Component|Field>
     */
    public static function noteSchema(): array
    {
        return [
            Select::make('type')->options(NoteType::class)->required()->default(NoteType::Call->value),
            Select::make('direction')->options(['outbound' => 'We contacted the customer', 'inbound' => 'The customer contacted us', 'internal' => 'Internal'])->default('outbound'),
            TextInput::make('subject')->maxLength(150),
            Textarea::make('body')->required()->maxLength(5000),
            DateTimePicker::make('follow_up_at')->label('Follow up on')->timezone('Asia/Kolkata'),
            Toggle::make('visible_to_customer')->label('Show this note to the customer in their profile'),
        ];
    }

    /**
     * @param  list<int>|null  $only
     * @return array<int, string>
     */
    public static function lenderProductOptions(?array $only = null): array
    {
        return LenderProduct::query()
            ->with('lender', 'loanProduct')
            ->when($only !== null, fn ($query) => $query->whereKey($only))
            ->get()
            ->mapWithKeys(fn (LenderProduct $product): array => [$product->id => $product->lender?->name.' — '.$product->loanProduct?->name])
            ->sort()
            ->all();
    }

    private function attempt(callable $action, string $success): void
    {
        try {
            $action();
            $this->getRecord()->refresh();
            Notification::make()->title($success)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title('Not saved')->body(implode(' ', $exception->validator->errors()->all()))->danger()->persistent()->send();
        }
    }
}
