<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('resources.index', [
            'articles' => Article::query()->published()->latest('published_at')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * A valid signed URL (generated only from the article's own Edit page in
     * Filament) lets an authorised admin preview a draft or not-yet-scheduled
     * article exactly as it will appear live, without making it publicly
     * reachable by anyone else.
     */
    public function show(Request $request, Article $article): View
    {
        abort_unless($article->isCurrentlyPublished() || $request->hasValidSignature(), 404);

        return view('resources.show', ['article' => $article]);
    }
}
