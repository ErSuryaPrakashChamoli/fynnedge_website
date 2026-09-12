<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ContactEnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every enquiry the public website produces, whichever form it came from —
 * `enquiry_type` is what separates a full contact-form message from a
 * phone-number-only Quick Enquiry. Deliberately one table: the team works a
 * single list, and a visitor who uses both forms is one person, not two leads.
 */
#[Fillable([
    'name', 'email', 'phone', 'message', 'source_url', 'handled_at',
    'enquiry_type', 'source', 'enquiry_source', 'status', 'enquiry_count',
    'phone_verified_at', 'loan_product_id', 'loan_amount',
])]
class ContactEnquiry extends Model
{
    /** @use HasFactory<ContactEnquiryFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'enquiry_type' => EnquiryType::class,
            'status' => EnquiryStatus::class,
            'enquiry_count' => 'integer',
            'loan_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        /*
         * `handled_at` predates the status column and still drives the admin
         * table's "Handled" icon and filter, so settling an enquiry (closed,
         * converted or rejected) stamps it rather than leaving the two
         * representations to drift apart. One direction only: an
         * already-recorded follow-up time is never erased.
         */
        static::saving(function (self $enquiry): void {
            if ($enquiry->status?->isSettled() && $enquiry->handled_at === null) {
                $enquiry->handled_at = now();
            }
        });
    }

    /**
     * The product the enquiry was made about, when it was made from a loan page.
     * Null for the general forms (contact page, homepage Quick Enquiry), which
     * name no product — so reporting groups on this, it never guesses.
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * The 10-digit Indian mobile number inside whatever the visitor typed —
     * spaces, hyphens, brackets, a +91/91/0 prefix. Anything that is not a
     * recognisable 10-digit number is returned cleaned but unchanged, so
     * validation (not this method) is what rejects it.
     */
    public static function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * The most recent enquiry from a mobile number, across every form. Ordered
     * by id as well as created_at: two rows written in the same second would
     * otherwise come back in arbitrary order.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForPhone(Builder $query, string $phone): Builder
    {
        return $query->where('phone', $phone)->latest('created_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeQuickEnquiries(Builder $query): Builder
    {
        return $query->where('enquiry_type', EnquiryType::QuickEnquiry);
    }

    public function isOpen(): bool
    {
        return $this->status?->isOpen() ?? true;
    }
}
