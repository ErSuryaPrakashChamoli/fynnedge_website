<?php

namespace App\Modules\Journey\Models;

use Database\Factories\JourneyResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journey_session_id', 'field_key', 'value'])]
class JourneyResponse extends Model
{
    /** @use HasFactory<JourneyResponseFactory> */
    use HasFactory;

    protected static function newFactory(): JourneyResponseFactory
    {
        return JourneyResponseFactory::new();
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(JourneySession::class, 'journey_session_id');
    }
}
