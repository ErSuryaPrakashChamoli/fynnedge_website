<?php

namespace App\Http\Controllers;

use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScorePageContent;
use Illuminate\Contracts\View\View;

/**
 * The page's wording is admin-editable (Website Settings → Credit Score Page)
 * through CreditScorePageContent.
 */
class CreditScoreController extends Controller
{
    public function show(BureauName $bureau): View
    {
        return view('credit-score.show', [
            'bureau' => $bureau,
            'content' => CreditScorePageContent::forBureau($bureau),
        ]);
    }
}
