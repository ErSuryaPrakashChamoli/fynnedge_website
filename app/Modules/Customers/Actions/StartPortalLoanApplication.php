<?php

namespace App\Modules\Customers\Actions;

use App\Models\LoanProduct;
use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Actions\StartJourneySession;
use App\Modules\Journey\Enums\JourneySessionStatus;
use App\Modules\Journey\Models\JourneySession;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * "Apply for a loan" from the customer portal. It enters the SAME journey,
 * eligibility, lender-selection and document workflow as the public site —
 * there is no second application system — with the session already tied to
 * the customer:
 *
 *  - an unfinished application for the same product is resumed rather than
 *    duplicated (a refresh or a double click lands on the same session);
 *  - a new session is linked to the account and its mobile marked verified,
 *    since the customer proved that number by OTP to sign in.
 */
class StartPortalLoanApplication
{
    public function __construct(private readonly StartJourneySession $start) {}

    /**
     * @return JourneySession|null Null when the product has no live journey.
     */
    public function handle(Customer $customer, LoanProduct $loanProduct, Request $request): ?JourneySession
    {
        try {
            return Cache::lock("portal-apply:{$customer->id}:{$loanProduct->id}", 15)
                ->block(5, fn (): ?JourneySession => $this->resumeOrStart($customer, $loanProduct, $request));
        } catch (LockTimeoutException) {
            return $this->unfinished($customer, $loanProduct);
        }
    }

    public function unfinished(Customer $customer, LoanProduct $loanProduct): ?JourneySession
    {
        return JourneySession::query()
            ->verifiedFor($customer)
            ->where('loan_product_id', $loanProduct->id)
            ->where('status', JourneySessionStatus::InProgress)
            ->latest('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function resumeOrStart(Customer $customer, LoanProduct $loanProduct, Request $request): ?JourneySession
    {
        if ($existing = $this->unfinished($customer, $loanProduct)) {
            return $existing;
        }

        $session = $this->start->handle($loanProduct, $request);

        if ($session === null) {
            return null;
        }

        $session->update([
            'customer_id' => $customer->id,
            'phone_number' => $customer->verified_mobile,
            'phone_verified_at' => now(),
        ]);

        $customer->recordEvent('loan_application_started', ['product' => $loanProduct->getRawOriginal('slug'), 'session' => $session->public_id]);

        return $session;
    }
}
