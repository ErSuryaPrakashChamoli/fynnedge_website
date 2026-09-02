<?php

namespace App\Http\Controllers;

use App\Models\JobOpening;
use App\Models\Page;
use Illuminate\Contracts\View\View;

class CareerController extends Controller
{
    public function __invoke(): View
    {
        $page = Page::query()->published()->where('slug', 'careers')->firstOrFail();

        return view('careers', [
            'page' => $page,
            'jobOpenings' => JobOpening::query()->published()->orderBy('sort_order')->get(),
        ]);
    }
}
