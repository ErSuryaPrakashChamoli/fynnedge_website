<?php

namespace App\Http\Controllers\Portal;

use App\Modules\Customers\Services\CustomerApplications;
use App\Modules\Customers\Services\CustomerCreditReports;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends PortalController
{
    /**
     * Reads only what is already saved: opening the dashboard never asks a
     * bureau for anything.
     */
    public function __invoke(Request $request, CustomerCreditReports $reports, CustomerApplications $applications): View
    {
        $customer = $this->customer($request);
        $recentApplications = $applications->applications($customer)->limit(5)->get();

        return view('portal.dashboard', [
            'customer' => $customer,
            'latestScore' => $reports->latestScore($customer),
            'refresh' => $reports->refreshOverview($customer),
            'recentReports' => $reports->history($customer)->limit(5)->get(),
            'activeApplicationsCount' => $applications->activeCount($customer),
            'drafts' => $applications->drafts($customer),
            'recentApplications' => $recentApplications,
            'missingDocuments' => $applications->missingDocumentCounts($recentApplications),
            'loanProducts' => $applications->applicableProducts()->take(4),
        ]);
    }
}
