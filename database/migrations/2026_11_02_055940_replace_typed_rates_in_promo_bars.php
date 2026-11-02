<?php

use App\Support\Rates\RateText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Promo bars written before RateText::rule() still type their rate
     * ("Get up to *₹50 Lakhs* starting at *10.49%* p.a."), so the form refused
     * every later save of them, even one that only switched the bar off. Each
     * typed rate becomes the [rate:personal-loan] token, which shows the live,
     * verified rate and resolves by loan category on production too: every
     * such bar is the site-wide ₹50 Lakhs personal loan offer. Copy that
     * RateText::tokenised() cannot rewrite whole is left as it is, and its
     * form error shows what to write instead.
     */
    public function up(): void
    {
        DB::table('promo_bars')
            ->select(['id', 'headline', 'headline_hi', 'rotating_messages', 'rotating_messages_hi'])
            ->chunkById(100, function ($promoBars): void {
                foreach ($promoBars as $promoBar) {
                    $changes = [];

                    foreach (['headline', 'headline_hi'] as $column) {
                        if (is_string($promoBar->{$column}) && ($tokenised = RateText::tokenised($promoBar->{$column})) !== null && $tokenised !== $promoBar->{$column}) {
                            $changes[$column] = $tokenised;
                        }
                    }

                    foreach (['rotating_messages', 'rotating_messages_hi'] as $column) {
                        $messages = json_decode((string) $promoBar->{$column}, true);

                        if (! is_array($messages)) {
                            continue;
                        }

                        $tokenised = array_map(
                            fn (mixed $message): mixed => is_string($message) ? (RateText::tokenised($message) ?? $message) : $message,
                            $messages,
                        );

                        if ($tokenised !== $messages) {
                            $changes[$column] = json_encode($tokenised);
                        }
                    }

                    if ($changes !== []) {
                        DB::table('promo_bars')->where('id', $promoBar->id)->update($changes);
                    }
                }
            });
    }

    /**
     * The typed figures were unverified and are not restored.
     */
    public function down(): void {}
};
