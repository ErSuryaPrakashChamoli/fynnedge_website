<?php

namespace App\Http\Controllers\Portal;

use App\Http\Requests\Portal\UpdateCustomerProfileRequest;
use App\Modules\Customers\Actions\UpdateCustomerProfile;
use App\Modules\Equifax\Enums\Gender;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends PortalController
{
    public function edit(Request $request): View
    {
        return view('portal.profile', [
            'customer' => $this->customer($request),
            'genders' => Gender::cases(),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request, UpdateCustomerProfile $action): RedirectResponse
    {
        $changed = $action->handle($this->customer($request), $request->validated());

        return redirect()->route('portal.profile')->with('status', $changed === []
            ? __('Nothing to update — your details are unchanged.')
            : __('Your details have been updated.'));
    }
}
