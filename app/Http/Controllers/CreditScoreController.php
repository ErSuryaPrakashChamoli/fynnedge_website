<?php

namespace App\Http\Controllers;

use App\Modules\CreditScore\Enums\BureauName;
use Illuminate\Contracts\View\View;

class CreditScoreController extends Controller
{
    public function show(BureauName $bureau): View
    {
        return view('credit-score.show', ['bureau' => $bureau]);
    }
}
