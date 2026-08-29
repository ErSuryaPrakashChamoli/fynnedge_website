<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Enums\PublishStatus;
use App\Models\LoanProduct;
use App\Support\Calculators\LoanCalculatorPreset;
use Illuminate\Contracts\View\View;

class LoanProductController extends Controller
{
    public function index(): View
    {
        return view('loans.index', [
            'loanProducts' => LoanProduct::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function show(LoanProduct $loanProduct): View
    {
        abort_unless($loanProduct->status === PublishStatus::Published, 404);

        $loanProduct->load([
            'lenderProducts' => fn ($query) => $query->where('status', LenderStatus::Active)->with('lender'),
            'faqs' => fn ($query) => $query->published(),
        ]);

        return view('loans.show', [
            'loanProduct' => $loanProduct,
            'calculatorSupported' => LoanCalculatorPreset::for($loanProduct->category) !== null,
        ]);
    }
}
