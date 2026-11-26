<?php

namespace App\Filament\Resources\DebtScoringConfigs;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Filament\Resources\DebtScoringConfigs\Pages\CreateDebtScoringConfig;
use App\Filament\Resources\DebtScoringConfigs\Pages\EditDebtScoringConfig;
use App\Filament\Resources\DebtScoringConfigs\Pages\ListDebtScoringConfigs;
use App\Modules\DebtConsolidation\DataTransferObjects\ScoringConfig;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\LenderPolicyStatus;
use App\Modules\DebtConsolidation\Models\DebtScoringConfig;
use App\Modules\DebtConsolidation\Services\MakerChecker;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Debt Consolidation → Scoring configurations: versioned suitability
 * weights and thresholds per applicant type. Drafts are edited; activating
 * one retires the previous version (the activator must not be the author,
 * unless they are a super admin). Every snapshot records the version used.
 */
class DebtScoringConfigResource extends Resource
{
    use BelongsToAdminModule;

    protected static ?string $model = DebtScoringConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Scoring Configuration';

    protected static ?string $modelLabel = 'scoring configuration';

    protected static ?string $slug = 'debt-scoring-configs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->components([
                Select::make('applicant_type')->options(ApplicantType::class)->required(),
                TextInput::make('version')->required()->maxLength(20),
                Textarea::make('notes')->label('Why this change')->columnSpanFull(),
            ]),
            Section::make('Weights (%) — must add up to 100')->components([
                KeyValue::make('weights')->keyLabel('Dimension')->valueLabel('Weight %')->addable(false)->deletable(false)->editableKeys(false)
                    ->default(ScoringConfig::defaults(ApplicantType::Individual)->weights)->required()
                    ->rules([fn (callable $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        foreach (ScoringConfig::problemsWith((array) $value, (array) $get('thresholds')) as $problem) {
                            $fail($problem);
                        }
                    }]),
            ]),
            Section::make('Thresholds')->description('See docs/debt-consolidation/06-financial-engine.md §6 for what each threshold means.')->components([
                KeyValue::make('thresholds')->keyLabel('Threshold')->valueLabel('Value')->addable(false)->deletable(false)->editableKeys(false)
                    ->default(ScoringConfig::defaultThresholds())->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('applicant_type')->label('For')->badge(),
                TextColumn::make('version'),
                TextColumn::make('status')->badge(),
                TextColumn::make('weights')->label('Weights')->state(fn (DebtScoringConfig $record): string => collect($record->weights)->map(fn ($weight, $key) => str($key)->headline()->append(' '.$weight.'%'))->implode(' · '))->wrap(),
                TextColumn::make('creator.name')->label('Author')->placeholder('Migration'),
                TextColumn::make('activator.name')->label('Activated by')->placeholder('—'),
                TextColumn::make('activated_at')->dateTime('j M Y', 'Asia/Kolkata')->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (DebtScoringConfig $record): bool => $record->status === LenderPolicyStatus::Draft),
                Action::make('activate')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (DebtScoringConfig $record): bool => $record->status === LenderPolicyStatus::Draft && (bool) auth()->user()?->can('Activate:DebtScoringConfig'))
                    ->requiresConfirmation()
                    ->modalDescription('New assessments will be scored with this version. Existing snapshots and reports keep the version they were scored with.')
                    ->action(function (DebtScoringConfig $record): void {
                        if (MakerChecker::isMaker($record, auth()->id()) && ! auth()->user()?->hasRole('super_admin')) {
                            Notification::make()->title('A second person must activate a configuration they did not write or change.')->danger()->send();

                            return;
                        }

                        DB::transaction(function () use ($record): void {
                            DebtScoringConfig::query()->where('applicant_type', $record->applicant_type)->where('status', LenderPolicyStatus::Active)
                                ->update(['status' => LenderPolicyStatus::Retired, 'retired_at' => now()]);
                            $record->update(['status' => LenderPolicyStatus::Active, 'activated_by' => auth()->id(), 'activated_at' => now()]);
                        });

                        Notification::make()->title('Activated')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDebtScoringConfigs::route('/'),
            'create' => CreateDebtScoringConfig::route('/create'),
            'edit' => EditDebtScoringConfig::route('/{record}/edit'),
        ];
    }
}
