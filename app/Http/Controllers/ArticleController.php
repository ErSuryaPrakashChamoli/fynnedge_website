<?php

namespace App\Http\Controllers;

use App\Enums\PublishStatus;
use App\Models\Article;
use Illuminate\Contracts\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('resources.index', [
            'articles' => Article::query()->published()->latest('published_at')->orderByDesc('id')->get(),
        ]);
    }

    public function show(Article $article): View
    {
        abort_unless($article->status === PublishStatus::Published, 404);

        return view('resources.show', ['article' => $article]);
    }
}
