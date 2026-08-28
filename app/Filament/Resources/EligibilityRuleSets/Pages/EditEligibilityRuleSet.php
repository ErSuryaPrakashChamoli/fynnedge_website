<?php

namespace App\Filament\Resources\EligibilityRuleSets\Pages;

use App\Filament\Resources\EligibilityRuleSets\EligibilityRuleSetResource;
use App\Modules\Eligibility\Actions\PublishEligibilityRuleSet;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEligibilityRuleSet extends EditRecord
{
    protected static string $resource = EligibilityRuleSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === EligibilityRuleSetStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription('This makes it the active rule set for this lender product and archives whichever version was active before.')
                ->action(function () {
                    $errors = app(PublishEligibilityRuleSet::class)->handle($this->record);

                    if ($errors !== []) {
                        Notification::make()
                            ->title('Could not publish')
                            ->body(implode("\n", $errors))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Rule set published')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),
            DeleteAction::make(),
        ];
    }
}
