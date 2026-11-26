<?php

namespace App\Filament\Resources\DebtApplications\Pages;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Actions\RecordDebtConsent;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\ConsentPurpose;
use App\Modules\DebtConsolidation\Enums\DebtApplicationStatus;
use App\Modules\DebtConsolidation\Models\DebtApplication;
use App\Modules\DebtConsolidation\Models\DebtConsent;
use App\Modules\MobileOtp\Services\MobileNumber;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * An advisor opens a case for a customer who called in. The mobile is not
 * OTP-verified, so the case is not in anyone's portal until the customer
 * verifies the number on the website — then it becomes theirs
 * (OpenDebtApplication). Consent must be documented by the advisor.
 */
class CreateDebtApplication extends CreateRecord
{
    protected static string $resource = DebtApplicationResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer')->columns(2)->components([
                Select::make('applicant_type')->options(ApplicantType::class)->required()->default(ApplicantType::Individual->value),
                TextInput::make('full_name')->label('Name')->required()->maxLength(150),
                TextInput::make('mobile')->tel()->required()->regex(MobileNumber::PATTERN)->helperText('10-digit Indian mobile. The customer verifies it later to see the case in their profile.'),
                TextInput::make('email')->email()->maxLength(191),
                TextInput::make('city')->maxLength(100),
            ]),
            Section::make('Consent')->components([
                Checkbox::make('consent_confirmed')->label('The customer consented to FynnEdge assessing their information and contacting them about this request.')->accepted(),
                Textarea::make('consent_note')->label('How and when consent was given')->required()->maxLength(500)->helperText('For example: "Verbal consent on recorded call, 25 Nov 2026, 11:20".'),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $application = DebtApplication::query()->create([
            'applicant_type' => $data['applicant_type'],
            'status' => DebtApplicationStatus::EnquiryReceived,
            'full_name' => $data['full_name'],
            'mobile' => MobileNumber::normalize($data['mobile']),
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'assigned_to' => auth()->id(),
            'enquiry_submitted_at' => now(),
        ]);

        $application->recordStatusChange(null, DebtApplicationStatus::EnquiryReceived->value, ActorType::Staff, auth()->id(), 'Opened by an advisor.');

        foreach ([ConsentPurpose::Assessment, ConsentPurpose::Contact] as $purpose) {
            $consent = app(RecordDebtConsent::class)->handle($application, $purpose, DebtConsent::METHOD_RECORDED_BY_STAFF, recordedBy: auth()->id());
            $application->notes()->create(['user_id' => auth()->id(), 'type' => 'internal_remark', 'body' => 'Consent ('.$purpose->value.') recorded: '.$data['consent_note'], 'subject' => 'Consent '.$consent->public_id]);
        }

        return $application;
    }

    protected function getRedirectUrl(): string
    {
        return DebtApplicationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
