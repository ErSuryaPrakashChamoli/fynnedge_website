<?php

namespace App\Http\Controllers;

use App\Support\Seo\Sitemap;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('sitemap', ['entries' => Sitemap::entries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
