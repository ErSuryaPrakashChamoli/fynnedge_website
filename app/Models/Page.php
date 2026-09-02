<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\Seoable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'expires_at'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use Auditable, HasFactory, HasPublicId, Publishable, Seoable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
