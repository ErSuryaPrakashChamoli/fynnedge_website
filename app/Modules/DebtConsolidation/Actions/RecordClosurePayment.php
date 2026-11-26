<?php

namespace App\Modules\DebtConsolidation\Actions;

use App\Modules\DebtConsolidation\Enums\ClosurePaymentMode;
use App\Modules\DebtConsolidation\Models\DebtClosure;
use App\Modules\DebtConsolidation\Models\DebtClosurePayment;
use App\Modules\DebtConsolidation\Models\DebtDocument;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment towards closing a liability, and its confirmation. A
 * payment is confirmed only with evidence: a UTR / reference or a proof
 * document attached to the case.
 */
class RecordClosurePayment
{
    /**
     * @param  array{amount: float, paid_on: string, payment_mode: string, paid_by: string, utr_reference?: ?string, notes?: ?string, debt_document_id?: ?int}  $data
     */
    public function record(DebtClosure $closure, array $data, int $userId): DebtClosurePayment
    {
        if (isset($data['debt_document_id']) && ! DebtDocument::query()->whereKey($data['debt_document_id'])->where('debt_application_id', $closure->debt_application_id)->exists()) {
            throw ValidationException::withMessages(['debt_document_id' => 'That document belongs to another case.']);
        }

        $payment = $closure->payments()->create([
            'amount' => round((float) $data['amount'], 2),
            'paid_on' => $data['paid_on'],
            'payment_mode' => ClosurePaymentMode::from($data['payment_mode']),
            'paid_by' => $data['paid_by'],
            'utr_reference' => $data['utr_reference'] ?? null,
            'debt_document_id' => $data['debt_document_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $userId,
        ]);

        $closure->application?->recordEvent('closure_payment_recorded', ['closure' => $closure->public_id, 'payment' => $payment->public_id], $userId);

        return $payment;
    }

    public function confirm(DebtClosurePayment $payment, int $userId): DebtClosurePayment
    {
        if (blank($payment->utr_reference) && $payment->debt_document_id === null) {
            throw ValidationException::withMessages(['utr_reference' => 'Add the UTR / reference or attach the payment proof before confirming.']);
        }

        $payment->forceFill(['confirmed_at' => now(), 'confirmed_by' => $userId])->save();

        return $payment;
    }
}
