<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CalculatorController extends Controller
{
    public function __invoke(): View
    {
        return view('calculators.emi');
    }
}
