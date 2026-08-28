<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\ContactEnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'message', 'source_url', 'handled_at'])]
class ContactEnquiry extends Model
{
    /** @use HasFactory<ContactEnquiryFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }
}
