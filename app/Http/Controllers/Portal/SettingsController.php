<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SettingsController extends PortalController
{
    public function __invoke(Request $request): View
    {
        return view('portal.settings', [
            'customer' => $this->customer($request),
        ]);
    }
}
