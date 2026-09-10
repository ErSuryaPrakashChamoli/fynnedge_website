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
