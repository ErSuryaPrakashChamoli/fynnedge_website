<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Models\LoanProduct;
use App\Models\Testimonial;
use App\Support\Calculators\LoanCalculatorPreset;
use App\Support\Faqs\PageFaqs;
use App\Support\Seo\SchemaGraph;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index(): View
    {
        return view('loans.index', [
            'loanProducts' => LoanProduct::query()->published()->orderedForDisplay()->get(),
        ]);
    }

    /**
     * A valid signed URL (generated only from the product's own Edit page in
     * Filament) lets an authorised admin preview a draft or not-yet-scheduled
     * product exactly as it will appear live, without making it publicly
     * reachable by anyone else.
     */
    public function show(Request $request, LoanProduct $loanProduct): View
    {
        abort_unless($loanProduct->isCurrentlyPublished() || $request->hasValidSignature(), 404);

        $loanProduct->load([
            'lenderProducts' => fn ($query) => $query->where('status', LenderStatus::Active)->with('lender'),
            'faqs' => fn ($query) => $query->published(),
            'landingPages' => fn ($query) => $query->published()->orderBy('group')->orderBy('sort_order'),
        ]);

        $view = $loanProduct->category->isHybridRepayment() ? 'loans.show-flexi-hybrid' : 'loans.show';

        return view($view, [
            'loanProduct' => $loanProduct,
            'faqs' => PageFaqs::merge($loanProduct->faqs),
            'schemaNodes' => [SchemaGraph::service(
                name: $loanProduct->name,
                serviceType: $loanProduct->category->getLabel(),
                url: route('loans.show', $loanProduct),
                description: $loanProduct->summary,
            )],
            'calculatorSupported' => LoanCalculatorPreset::for($loanProduct->category) !== null,
            'testimonials' => Testimonial::query()->published()->forCategory($loanProduct->category)->orderBy('sort_order')->get(),
        ]);
    }
}
