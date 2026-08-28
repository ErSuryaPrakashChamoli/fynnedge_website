<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        $page = Page::query()->published()->where('slug', 'about')->firstOrFail();

        return view('pages.show', ['page' => $page]);
    }
}
