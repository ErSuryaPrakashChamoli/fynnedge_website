<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Saves the changes a customer makes to their own profile. The request has
 * already refused locked fields; this keeps only the editable ones, stores a
 * PAN encrypted, and records WHICH fields changed (never their values) in the
 * account's audit history.
 */
class UpdateCustomerProfile
{
    /** @var list<string> */
    private const EDITABLE = ['full_name', 'date_of_birth', 'gender', 'email', 'address_line1', 'address_line2', 'city', 'state', 'pincode'];

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string> The fields that changed.
     */
    public function handle(Customer $customer, array $validated): array
    {
        return DB::transaction(function () use ($customer, $validated): array {
            $customer->fill(collect($validated)->only(self::EDITABLE)->map(fn ($value) => blank($value) ? null : $value)->all());

            if (filled($validated['pan_number'] ?? null) && blank($customer->pan_hash)) {
                $customer->setPan($validated['pan_number']);
            }

            $changed = collect(array_keys($customer->getDirty()))
                ->map(fn (string $field): string => in_array($field, ['pan_encrypted', 'pan_masked', 'pan_hash'], true) ? 'pan' : $field)
                ->unique()
                ->values()
                ->all();

            if ($changed === []) {
                return [];
            }

            $customer->save();
            $customer->recordEvent('profile_updated', ['fields' => implode(', ', $changed), 'source' => 'customer_portal']);

            return $changed;
        });
    }
}
