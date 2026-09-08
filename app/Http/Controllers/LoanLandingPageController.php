<?php

namespace App\Http\Controllers;

use App\Enums\LenderStatus;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\Testimonial;
use App\Support\Calculators\LoanCalculatorPreset;
use App\Support\Seo\SchemaGraph;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LoanLandingPageController extends Controller
{
    /**
     * A valid signed URL (generated only from the landing page's own Edit
     * page in Filament) lets an authorised admin preview a draft or
     * not-yet-scheduled page exactly as it will appear live, without making
     * it publicly reachable by anyone else.
     */
    public function show(Request $request, LoanProduct $loanProduct, LoanLandingPage $landingPage): View
    {
        abort_unless($landingPage->loan_product_id === $loanProduct->id, 404);
        abort_unless($landingPage->isCurrentlyPublished() || $request->hasValidSignature(), 404);

        $loanProduct->load([
            'lenderProducts' => fn ($query) => $query->where('status', LenderStatus::Active)->with('lender'),
            'faqs' => fn ($query) => $query->published(),
        ]);

        return view('loans.landing-page', [
            'loanProduct' => $loanProduct,
            'landingPage' => $landingPage,
            'schemaNodes' => [SchemaGraph::service(
                name: $landingPage->title,
                serviceType: $loanProduct->category->getLabel(),
                url: route('loans.landing-pages.show', ['loanProduct' => $loanProduct, 'landingPage' => $landingPage]),
                description: $landingPage->excerpt,
            )],
            'calculatorSupported' => LoanCalculatorPreset::for($loanProduct->category) !== null,
            'testimonials' => Testimonial::query()->published()->forCategory($loanProduct->category)->orderBy('sort_order')->get(),
        ]);
    }
}
