<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\CalculatorPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable "about this calculator" copy for the standalone calculator
 * pages that have no LoanProduct to hang content off (Fixed Deposit, SIP,
 * Daily SIP, GST). Loan-category calculators (EMI, Eligibility, Prepayment)
 * instead reuse LoanProduct::calculator_explanation — the pre-existing,
 * already-admin-editable field for exactly this purpose — so this model
 * only needs to exist for the ones without a LoanProduct behind them.
 */
#[Fillable(['calculator_key', 'title', 'body'])]
class CalculatorPage extends Model
{
    /** @use HasFactory<CalculatorPageFactory> */
    use HasFactory, HasPublicId;
}
