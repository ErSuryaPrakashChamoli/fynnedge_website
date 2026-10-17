<?php

namespace App\Http\Requests\Portal;

use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use App\Modules\Equifax\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields a customer may change themselves. Identity fields (name, date of
 * birth, gender) are refused once staff have verified the customer's identity;
 * a PAN can be added once but never changed here; the mobile number is the
 * login and changes only through support. Anything not listed is ignored.
 */
class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->customer() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'pan_number' => $this->filled('pan_number') ? PanNumber::normalize($this->input('pan_number')) : null,
            'pincode' => $this->filled('pincode') ? preg_replace('/\s+/', '', (string) $this->input('pincode')) : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->customer();
        $identityLocked = $customer?->isIdentityVerified() ?? true;

        return [
            'full_name' => $identityLocked ? ['prohibited'] : ['required', 'string', 'max:150'],
            'date_of_birth' => $identityLocked ? ['prohibited'] : ['nullable', 'date', 'before:-18 years'],
            'gender' => $identityLocked ? ['prohibited'] : ['nullable', Rule::enum(Gender::class)],
            'pan_number' => filled($customer?->pan_hash) ? ['prohibited'] : ['nullable', 'regex:'.PanNumber::PATTERN],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prohibited' => __('This detail is verified and can only be changed by our team. Please contact support.'),
            'email.unique' => __('This email address cannot be used. Please use a different one or contact support.'),
            'date_of_birth.before' => __('You must be at least 18 years old.'),
            'pan_number.regex' => __('Enter a valid PAN (e.g. ABCDE1234F).'),
            'pincode.regex' => __('Enter a valid 6-digit PIN code.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('full name'),
            'date_of_birth' => __('date of birth'),
            'pan_number' => __('PAN'),
            'address_line1' => __('address line 1'),
            'address_line2' => __('address line 2'),
            'pincode' => __('PIN code'),
        ];
    }

    public function customer(): ?Customer
    {
        $customer = $this->user(LogInCustomer::GUARD);

        return $customer instanceof Customer ? $customer : null;
    }
}
