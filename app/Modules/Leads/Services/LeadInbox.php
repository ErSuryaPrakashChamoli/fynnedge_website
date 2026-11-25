<?php

namespace App\Modules\Leads\Services;

use App\Modules\FynnOn\Enums\FynnOnSyncStatus;
use App\Modules\Leads\Enums\LeadSource;
use App\Modules\Leads\Models\LeadInboxEntry;
use App\Modules\MobileOtp\Enums\MobileOtpPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * One read-only, uniform view over every lead table — a UNION of
 * contact_enquiries, credit_score_checks, journey_sessions (with a phone
 * number), applications and mobile_otp_challenges, each projected onto the same
 * columns. Nothing is copied or stored: the source tables stay the single
 * source of truth and each row links back to its own admin screen.
 *
 * Only sources the signed-in user may open are included ({@see LeadSource::isAccessible()}).
 * Columns with no data in a source are NULL — attribution is never inferred.
 */
final class LeadInbox
{
    /**
     * @param  array<int, LeadSource>|null  $sources  null = every source the current user can access
     * @return Builder<LeadInboxEntry>
     */
    public static function query(?array $sources = null): Builder
    {
        $sources ??= LeadSource::accessible();

        if ($sources === []) {
            return LeadInboxEntry::query()->fromSub(self::select(LeadSource::Enquiry)->whereRaw('1 = 0'), 'lead_inbox');
        }

        $union = null;

        foreach ($sources as $source) {
            $select = self::select($source);
            $union = $union === null ? $select : $union->unionAll($select);
        }

        return LeadInboxEntry::query()->fromSub($union, 'lead_inbox');
    }

    /**
     * Every source, regardless of the current user — for system work (the LMS
     * sync dashboard counts, scheduled commands). Never for display without a
     * permission check of its own.
     *
     * @return Builder<LeadInboxEntry>
     */
    public static function unrestricted(): Builder
    {
        return self::query(LeadSource::cases());
    }

    public static function select(LeadSource $source): QueryBuilder
    {
        return match ($source) {
            LeadSource::Enquiry => DB::table('contact_enquiries as l')
                ->select(self::columns($source, [
                    'received_at' => 'l.created_at',
                    'name' => 'l.name',
                    'phone' => 'l.phone',
                    'email' => 'l.email',
                    'detail' => DB::raw('COALESCE(l.enquiry_source, l.enquiry_type)'),
                    'loan_product_id' => 'l.loan_product_id',
                    'source_page' => 'l.source_url',
                    'verified_at' => 'l.phone_verified_at',
                    'status' => 'l.status',
                    'repeat_count' => 'l.enquiry_count',
                ])),

            LeadSource::CreditScoreCheck => DB::table('credit_score_checks as l')
                ->select(self::columns($source, [
                    'received_at' => 'l.created_at',
                    'name' => 'l.full_name',
                    'phone' => 'l.mobile_number',
                    'detail' => 'l.bureau',
                    'verified_at' => 'l.mobile_verified_at',
                    'status' => 'l.status',
                ])),

            // An application nobody has given a phone number for is a funnel statistic, not a lead.
            LeadSource::OnlineApplication => DB::table('journey_sessions as l')
                ->whereNotNull('l.phone_number')
                ->select(self::columns($source, [
                    'received_at' => 'l.created_at',
                    'phone' => 'l.phone_number',
                    'detail' => 'l.status',
                    'loan_product_id' => 'l.loan_product_id',
                    'source_page' => 'l.landing_page',
                    'referrer' => 'l.referrer',
                    'utm_source' => 'l.utm_source',
                    'utm_medium' => 'l.utm_medium',
                    'utm_campaign' => 'l.utm_campaign',
                    'verified_at' => 'l.phone_verified_at',
                    'status' => 'l.status',
                ])),

            LeadSource::LoanApplication => DB::table('applications as l')
                ->leftJoin('journey_sessions as js', 'js.id', '=', 'l.journey_session_id')
                ->leftJoin('customers as c', 'c.id', '=', 'l.customer_id')
                ->leftJoin('lender_products as lp', 'lp.id', '=', 'l.lender_product_id')
                ->leftJoin('lenders as ln', 'ln.id', '=', 'lp.lender_id')
                ->select(self::columns($source, [
                    'received_at' => DB::raw('COALESCE(l.submitted_at, l.created_at)'),
                    'name' => 'c.full_name',
                    'phone' => DB::raw('COALESCE(js.phone_number, c.phone)'),
                    'email' => 'c.email',
                    'detail' => 'ln.name',
                    'loan_product_id' => DB::raw('COALESCE(js.loan_product_id, lp.loan_product_id)'),
                    'source_page' => 'js.landing_page',
                    'referrer' => 'js.referrer',
                    'utm_source' => 'js.utm_source',
                    'utm_medium' => 'js.utm_medium',
                    'utm_campaign' => 'js.utm_campaign',
                    'verified_at' => 'js.phone_verified_at',
                    'status' => 'l.status',
                ])),

            // Portal sign-ins are an existing customer logging in, not a new lead.
            LeadSource::OtpVerification => DB::table('mobile_otp_challenges as l')
                ->where(fn (QueryBuilder $query) => $query->whereNull('l.purpose')->orWhere('l.purpose', '!=', MobileOtpPurpose::CustomerLogin->value))
                ->select(self::columns($source, [
                    'received_at' => 'l.created_at',
                    'phone' => 'l.mobile_number',
                    'detail' => 'l.purpose',
                    'verified_at' => 'l.verified_at',
                    'status' => 'l.status',
                ])),
        };
    }

    /**
     * The fixed column list every source projects onto, in a fixed order (a
     * UNION matches columns by position).
     *
     * @param  array<string, string|Expression>  $map
     * @return array<int, string|Expression>
     */
    private static function columns(LeadSource $source, array $map): array
    {
        $morphClass = DB::getPdo()->quote($source->morphClass());
        $prefix = FynnOnSyncStatus::ACTION_PREFIX;
        $latestSync = "(SELECT %s FROM audit_logs a WHERE a.auditable_type = {$morphClass} AND a.auditable_id = l.id AND a.action LIKE '{$prefix}%%' ORDER BY a.id DESC LIMIT 1)";

        $columns = [
            'lead_key' => DB::raw(self::concat("'{$source->value}-'", 'l.id')),
            'source' => DB::raw("'{$source->value}'"),
            'source_id' => 'l.id',
            'record_key' => 'l.public_id',
            'received_at' => null,
            'name' => null,
            'phone' => null,
            'email' => null,
            'detail' => null,
            'loan_product_id' => null,
            'source_page' => null,
            'referrer' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'verified_at' => null,
            'status' => null,
            'repeat_count' => DB::raw('1'),
            'lms_status' => DB::raw(sprintf($latestSync, 'a.action')),
            'lms_at' => DB::raw(sprintf($latestSync, 'a.created_at')),
        ];

        $select = [];

        foreach ($columns as $alias => $default) {
            $expression = $map[$alias] ?? $default;

            if ($expression === null) {
                $select[] = DB::raw("NULL as {$alias}");

                continue;
            }

            $select[] = $expression instanceof Expression
                ? DB::raw($expression->getValue(DB::connection()->getQueryGrammar())." as {$alias}")
                : "{$expression} as {$alias}";
        }

        return $select;
    }

    private static function concat(string $left, string $right): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "({$left} || {$right})"
            : "CONCAT({$left}, {$right})";
    }
}
