<?php

namespace App\Http\Controllers;

use App\Models\CreditScorePage;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScoreIndexing;
use App\Support\Pages\CreditScorePageContent;
use Illuminate\Contracts\View\View;

/**
 * Each bureau page's title, headline and introduction is admin-editable
 * (Website Settings → Credit Score Page) through CreditScorePageContent,
 * whether search engines may index it through CreditScoreIndexing, and its
 * "About" section (Content → Credit Score Pages) through CreditScorePage —
 * the same three layers as CalculatorController.
 */
class CreditScoreController extends Controller
{
    public function show(BureauName $bureau): View
    {
        return view('credit-score.show', [
            'bureau' => $bureau,
            'content' => CreditScorePageContent::forBureau($bureau),
            'robots' => CreditScoreIndexing::robotsFor($bureau),
            'about' => CreditScorePage::aboutFor($bureau),
        ]);
    }
}
