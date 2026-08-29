<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'status', 'published_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
