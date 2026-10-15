<?php

use App\Modules\Newsletter\Jobs\SendNewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled campaigns.
 *
 * The "Send" action dispatches immediately; this covers campaigns given a
 * future date instead. It only DISPATCHES the fan-out job — the actual sending
 * still happens on the queue, so a large scheduled campaign never runs inside
 * the scheduler process.
 *
 * withoutOverlapping matters here: a slow dispatch must not be started twice,
 * or two SendNewsletterCampaign jobs race for the same campaign. (They would
 * still be safe — the recipients table's unique index is the real guard — but
 * the duplicate work is pointless.)
 */
Schedule::call(function (): void {
    NewsletterCampaign::query()->dueForSending()->each(
        fn (NewsletterCampaign $campaign) => SendNewsletterCampaign::dispatch($campaign),
    );
})->name('newsletter:dispatch-scheduled')->everyMinute()->withoutOverlapping();

/*
 * Hindi for anything still untranslated: content saved while no Google
 * Translate key was configured, a translation whose request failed, or rows
 * written outside the models. Existing Hindi is never touched; without a key
 * it only logs that nothing could be done.
 */
Schedule::command('translations:fill-hindi')->dailyAt('03:30')->withoutOverlapping();

/*
 * CRIF credit reports. flag-stale frees a PAN that a dead queue worker left
 * pending (never sent → retryable) or processing (maybe sent → reconciliation);
 * it never calls Surepass. enforce-retention erases report contents and
 * customer details older than the retention period set in CRIF Settings.
 */
Schedule::command('crif:flag-stale')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crif:enforce-retention')->dailyAt('02:30')->withoutOverlapping();
