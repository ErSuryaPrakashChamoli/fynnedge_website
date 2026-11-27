<?php

/*
 * Database checks and the two manual writes for the production content
 * restore approved by the Owner on 27 Nov 2026 (docs/debt-consolidation/36–39).
 * Called by run.sh from the application root; prints PASS / FAIL / INFO lines
 * and exits 1 when any check fails. Prints no customer personal data.
 */

declare(strict_types=1);

use App\Models\Page;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use App\Modules\Eligibility\Models\EligibilityRuleSet;
use App\Support\Localization\AutoTranslation;
use App\Support\Pages\DebtConsolidationPages;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

$appDir = getenv('APP_DIR') ?: getcwd();
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const WINDOW_FROM = '2026-11-26 13:05:00';
const WINDOW_TO = '2026-11-26 13:09:59';
const RUN_ONE = ['2026-11-26 13:05:43', '2026-11-26 13:05:49'];
const RUN_TWO = ['2026-11-26 13:09:07', '2026-11-26 13:09:12'];
/** Demo rule set id => the offer it was written onto. */
const DEMO_RULE_SETS = [3 => 359, 4 => 7];
const DEMO_LENDERS = [50, 51, 52, 53];
const DEMO_OFFER = 359;
const DUPLICATE_PRODUCT = 14;
const MOVED_PAGES = [1, 2, 3, 4, 37, 38, 39, 40, 84, 85, 87, 88];
const DUPLICATE_PAGES = [414, 438];
const DUPLICATE_PRODUCT_JOURNEYS = [1436, 1437, 1438, 1441, 1442];
const DEMO_ELIGIBILITY_RESULT = 1468;
const DC_APPLICATION = 1;
const DC_PUBLISHED_AT = '2026-11-26 13:22:32';
const PRESERVED_TABLES = [
    'customers', 'contact_enquiries', 'applications', 'debt_applications', 'debt_consents', 'debt_status_changes',
    'debt_liabilities', 'journey_sessions', 'journey_responses', 'eligibility_results', 'eligibility_result_reasons', 'credit_score_checks',
];

$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL').' | '.$label.($detail !== '' ? ' | '.$detail : '').PHP_EOL;
    $failures += $ok ? 0 : 1;
}

function info(string $label, string $detail): void
{
    echo 'INFO | '.$label.' | '.$detail.PHP_EOL;
}

function scalar(string $sql, array $bindings = []): mixed
{
    $row = DB::selectOne($sql, $bindings);

    return $row === null ? null : array_values((array) $row)[0];
}

function connectionInfo(): void
{
    $name = config('database.default');
    $c = config("database.connections.{$name}");
    info('app environment', (string) app()->environment());
    info('app url', (string) config('app.url'));
    info('database', sprintf('%s://%s@%s:%s/%s', $c['driver'], $c['username'], $c['host'], $c['port'], $c['database']));
    info('database server', (string) scalar('SELECT VERSION()'));
    info('session time zone', scalar('SELECT @@session.time_zone').' (system '.scalar('SELECT @@system_time_zone').')');
    info('database now, as the app reads it', (string) scalar('SELECT NOW()'));
}

function dcPageIds(): array
{
    return DB::table('pages')->whereIn('slug', DebtConsolidationPages::slugs())->pluck('id')->all();
}

function dcFaqs(): Collection
{
    return DB::table('faqs')->where('faqable_type', (new Page)->getMorphClass())->whereIn('faqable_id', dcPageIds())->get(['id', 'status', 'updated_at']);
}

function dcAcceptance(): array
{
    return [
        'service on' => DebtConsolidationSettings::enabled(),
        'accept individuals' => DebtConsolidationSettings::acceptsApplicant(ApplicantType::Individual),
        'accept businesses' => DebtConsolidationSettings::acceptsApplicant(ApplicantType::Business),
    ];
}

function checkDcPaused(): void
{
    $acceptance = dcAcceptance();
    check('DC pages stay on (no rollback)', $acceptance['service on']);
    check('new DC applications paused', ! $acceptance['accept individuals'] && ! $acceptance['accept businesses'], json_encode($acceptance));
}

function checkDcIntact(): void
{
    $pages = DB::table('pages')->whereIn('id', dcPageIds())->get(['slug', 'status', 'published_at', 'deleted_at']);
    check('7 DC pages published, not deleted', $pages->count() === 7 && $pages->every(fn ($p): bool => $p->status === 'published' && $p->deleted_at === null));
    check('DC pages still carry their 13:22:32 publication', $pages->every(fn ($p): bool => $p->published_at === DC_PUBLISHED_AT), $pages->pluck('published_at')->unique()->implode(', '));
    $faqs = dcFaqs();
    check('25 DC FAQs published', $faqs->count() === 25 && $faqs->every(fn ($f): bool => $f->status === 'published'), $faqs->count().' found');
}

function checkCustomerDataPreserved(): void
{
    $application = DB::table('debt_applications')->where('id', DC_APPLICATION)->first(['reference', 'status', 'deleted_at']);
    check('DC application #1 present and not deleted', $application !== null && $application->deleted_at === null, $application ? "{$application->reference}, {$application->status}" : 'missing');
    check('its consent and status history present', (int) scalar('SELECT COUNT(*) FROM debt_consents WHERE debt_application_id = ?', [DC_APPLICATION]) >= 1
        && (int) scalar("SELECT COUNT(*) FROM debt_status_changes WHERE subject_type LIKE '%DebtApplication' AND subject_id = ?", [DC_APPLICATION]) >= 2);
    check('its audit trail present', (int) scalar("SELECT COUNT(*) FROM audit_logs WHERE auditable_type LIKE '%DebtApplication' AND auditable_id = ?", [DC_APPLICATION]) >= 3);
    $journeys = DB::table('journey_sessions')->whereIn('id', DUPLICATE_PRODUCT_JOURNEYS)->pluck('loan_product_id', 'id');
    check('the 5 journeys on the duplicate product present', $journeys->count() === 5 && $journeys->every(fn ($product): bool => (int) $product === DUPLICATE_PRODUCT));
    check('demo eligibility result #1468 preserved as evidence', DB::table('eligibility_results')->where('id', DEMO_ELIGIBILITY_RESULT)->exists());
}

function snapshot(): array
{
    return collect(PRESERVED_TABLES)->mapWithKeys(fn (string $table): array => [$table => (int) DB::table($table)->count()])->all();
}

function checkDemoExcluded(): void
{
    $activeDemoLenders = DB::table('lenders')->where('status', 'active')->where(fn ($q) => $q->whereIn('id', DEMO_LENDERS)->orWhere('name', 'like', 'DEMO Lender%'))->count();
    check('no DEMO lender active', $activeDemoLenders === 0, "{$activeDemoLenders} active");
    $activeDemoOffers = DB::table('lender_products')->join('lenders', 'lenders.id', '=', 'lender_products.lender_id')
        ->where('lender_products.status', 'active')->where(fn ($q) => $q->whereIn('lenders.id', DEMO_LENDERS)->orWhere('lenders.name', 'like', 'DEMO Lender%'))->count();
    check('no active offer from a DEMO lender (matching and eligibility read offers)', $activeDemoOffers === 0, "{$activeDemoOffers} active");
    $activeDemoRuleSets = DB::table('eligibility_rule_sets')->where('status', 'active')
        ->where(fn ($q) => $q->whereIn('id', array_keys(DEMO_RULE_SETS))->orWhereBetween('created_at', RUN_ONE)->orWhereBetween('created_at', RUN_TWO))->count();
    check('no demo eligibility rule set active', $activeDemoRuleSets === 0, "{$activeDemoRuleSets} active");
    $demoOffers = DB::table('lender_products')->whereIn('lender_id', DEMO_LENDERS)->pluck('id')->all();
    check('no DC lender policy exists for a DEMO offer', DB::table('debt_lender_policies')->whereIn('lender_product_id', $demoOffers)->count() === 0);
}

$command = $argv[1] ?? '';

switch ($command) {
    case 'dbname':
        echo config('database.connections.'.config('database.default').'.database'), PHP_EOL;
        break;

    case 'credentials':
        $c = config('database.connections.'.config('database.default'));
        echo implode("\t", [$c['host'], $c['port'], $c['database'], $c['username'], $c['password']]), PHP_EOL;
        break;

    case 'table-count':
        echo (int) scalar('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'), PHP_EOL;
        break;

    case 'row-counts':
        if (isset($argv[2])) { // a scratch database on the same server; works with a cached config
            $connection = config('database.default');
            config(["database.connections.{$connection}.database" => $argv[2]]);
            DB::purge($connection);
        }

        foreach (['loan_products', 'loan_landing_pages', 'pages', 'articles', 'faqs', 'testimonials', 'lenders', 'lender_products', 'eligibility_rule_sets', 'debt_applications', 'debt_consents', 'seo_metas', 'settings'] as $table) {
            echo $table, "\t", DB::table($table)->count(), PHP_EOL;
        }
        break;

    case 'snapshot':
        foreach (snapshot() as $table => $count) {
            echo $table, "\t", $count, PHP_EOL;
        }
        break;

    case 'pause-dc':
        $before = dcAcceptance();
        info('before', json_encode($before));
        $saved = Setting::get(DebtConsolidationSettings::KEY, []);
        AutoTranslation::paused(fn () => Setting::set(DebtConsolidationSettings::KEY, array_replace(is_array($saved) ? $saved : [], ['individual_enabled' => false, 'business_enabled' => false])));
        info('after', json_encode(dcAcceptance()));
        checkDcPaused();
        checkDcIntact();
        break;

    case 'fingerprint':
        connectionInfo();
        $window = (int) scalar('SELECT COUNT(*) FROM audit_logs WHERE user_id IS NULL AND created_at BETWEEN ? AND ?', [WINDOW_FROM, WINDOW_TO]);
        $runOne = (int) scalar('SELECT COUNT(*) FROM audit_logs WHERE user_id IS NULL AND created_at BETWEEN ? AND ?', RUN_ONE);
        $runTwo = (int) scalar('SELECT COUNT(*) FROM audit_logs WHERE user_id IS NULL AND created_at BETWEEN ? AND ?', RUN_TWO);
        check('console writes in the approved window = 991 (export of 27 Nov)', $window === 991, "{$window}");
        check('run one 13:05:43–49 = 517, run two 13:09:07–12 = 474', $runOne === 517 && $runTwo === 474, "{$runOne} / {$runTwo}");
        check('product #1 is personal-loans, #14 is the duplicate personal-loan', DB::table('loan_products')->where('id', 1)->value('slug') === 'personal-loans'
            && DB::table('loan_products')->where('id', DUPLICATE_PRODUCT)->value('slug') === 'personal-loan');
        info('duplicate product #14', (string) DB::table('loan_products')->where('id', DUPLICATE_PRODUCT)->value('status'));
        info('staff edits after the window, any content model', (string) scalar('SELECT COUNT(*) FROM audit_logs WHERE user_id IS NOT NULL AND created_at > ?', [WINDOW_TO]));
        foreach (DEMO_RULE_SETS as $id => $offer) {
            $set = DB::table('eligibility_rule_sets')->where('id', $id)->first(['lender_product_id', 'status', 'created_at']);
            info("rule set #{$id}", $set ? "offer #{$set->lender_product_id}, {$set->status}, created {$set->created_at}" : 'missing');
        }
        checkDcPaused();
        checkDcIntact();
        checkCustomerDataPreserved();
        break;

    case 'archive-rulesets':
        foreach (DEMO_RULE_SETS as $id => $offer) {
            $set = EligibilityRuleSet::query()->find($id);
            $created = $set?->getRawOriginal('created_at');
            $isDemo = $set !== null && (int) $set->lender_product_id === $offer && $created >= RUN_ONE[0] && $created <= RUN_ONE[1];
            check("rule set #{$id} is the demo set on offer #{$offer} written at 13:05", $isDemo, $set ? "offer #{$set->lender_product_id}, created {$created}" : 'missing');

            if (! $isDemo) {
                continue;
            }

            $before = $set->status->value;

            if ($set->status !== EligibilityRuleSetStatus::Archived) {
                AutoTranslation::paused(fn () => $set->update(['status' => EligibilityRuleSetStatus::Archived]));
            }

            check("rule set #{$id} archived", $set->fresh()->status === EligibilityRuleSetStatus::Archived, "was {$before}");
        }
        checkDemoExcluded();
        break;

    case 'verify':
        connectionInfo();
        $productsBack = DB::table('loan_products')->whereBetween('id', [2, 12])->where('published_at', '<', '2026-11-26')->count();
        check('products #2–#12 back to their earlier publication date', $productsBack === 11, "{$productsBack}/11");
        check('product #1 personal-loans untouched and published', DB::table('loan_products')->where('id', 1)->value('status') === 'published');
        $duplicate = DB::table('loan_products')->where('id', DUPLICATE_PRODUCT)->value('status');
        $staffEdited = DB::table('audit_logs')->whereNotNull('user_id')->where('auditable_type', 'like', '%LoanProduct')->where('auditable_id', DUPLICATE_PRODUCT)->where('created_at', '>', WINDOW_TO)->exists();
        check('duplicate product #14 hidden, or kept because staff edited it (Owner decision)', $duplicate !== 'published' || $staffEdited, "{$duplicate}".($staffEdited ? ', staff-edited' : ''));
        $moved = DB::table('loan_landing_pages')->whereIn('id', MOVED_PAGES)->where('loan_product_id', 1)->where('status', 'published')->count();
        check('the 12 moved pages are back on personal-loans and published', $moved === 12, "{$moved}/12");
        $duplicatePages = DB::table('loan_landing_pages')->whereBetween('id', DUPLICATE_PAGES)->where('status', 'published')->count();
        check('the 25 pages created on #14 are hidden', $duplicatePages === 0, "{$duplicatePages} still published");
        $legal = DB::table('pages')->whereBetween('id', [1, 7])->get(['slug', 'body', 'published_at']);
        check('About and the 6 legal/careers pages no longer carry starter text', $legal->every(fn ($p): bool => ! str_contains((string) $p->body, 'November 26, 2026') && $p->published_at < '2026-11-26'));
        check('articles #1–#10 back to their earlier dates', DB::table('articles')->whereBetween('id', [1, 10])->where('published_at', '<', '2026-11-26')->count() === 10);
        check('starter testimonials #1–#6 back to draft', DB::table('testimonials')->whereBetween('id', [1, 6])->where('status', 'draft')->count() === 6);
        check('FAQs #158–#165 created by the seeder are hidden', DB::table('faqs')->whereBetween('id', [158, 165])->where('status', 'published')->count() === 0);
        checkDemoExcluded();
        checkDcPaused();
        checkDcIntact();
        checkCustomerDataPreserved();
        $before = [];
        foreach (file((string) getenv('SNAPSHOT_FILE'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$table, $count] = explode("\t", $line);
            $before[$table] = (int) $count;
        }
        foreach (snapshot() as $table => $count) {
            check("no {$table} row lost", $count >= ($before[$table] ?? PHP_INT_MAX), ($before[$table] ?? '?')." → {$count}");
        }
        break;

    default:
        fwrite(STDERR, "Usage: php checks.php dbname|credentials|table-count|row-counts|snapshot|pause-dc|fingerprint|archive-rulesets|verify\n");
        exit(2);
}

exit($failures > 0 ? 1 : 0);
