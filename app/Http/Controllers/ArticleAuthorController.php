<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Contracts\View\View;

/**
 * /resources/authors/{slug}: an active author's profile and their published
 * articles, newest first. Inactive authors are a 404 (route binding skips
 * is_active, so it is checked here).
 */
class ArticleAuthorController extends Controller
{
    public function __invoke(Author $author): View
    {
        abort_unless($author->is_active, 404);

        return view('resources.authors.show', [
            'author' => $author,
            'articles' => $author->articles()->published()->newestFirst()->with('author')->get(),
        ]);
    }
}
