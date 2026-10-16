<?php

namespace App\Modules\CreditScore\Services;

use App\Modules\CreditScore\Enums\ScoreBand;
use App\Support\Formatting\IndianNumberFormatter;

/**
 * What the public result page says about a score: its band, the report's
 * score factors and the pointers to improve or keep it. Pointers drawn from
 * the visitor's own report (CreditScoreCheck::report_insights — overdue
 * dues, late payments, card usage, enquiries, credit age) come first, most
 * urgent first; the band's general pointers fill the rest. A demo score, or
 * a live one without a report behind it, gets the band's pointers only, and
 * no score at all gets pointers for building a first credit history.
 *
 * Every line is built in the visitor's language at render time; nothing here
 * is stored.
 */
final readonly class CreditScoreAdvice
{
    private const MAX_TIPS = 6;

    /** Card usage (balance ÷ limit) lenders read as comfortable, and as stretched. */
    private const HEALTHY_USAGE = 30;

    private const HIGH_USAGE = 50;

    /**
     * @param  array<string, int|null>|null  $insights  The report's headline figures (see CrifReportDocument::insights()).
     */
    public function __construct(
        public ?int $score,
        private ?array $insights,
        private string $bureau,
    ) {}

    public function band(): ?ScoreBand
    {
        return $this->score === null ? null : ScoreBand::fromScore($this->score);
    }

    /** Where the score sits on the 300–900 meter, 0–100. */
    public function meterPercent(): float
    {
        if ($this->score === null) {
            return 0.0;
        }

        $score = max(ScoreBand::LOWEST_SCORE, min(ScoreBand::HIGHEST_SCORE, $this->score));

        return round(($score - ScoreBand::LOWEST_SCORE) / (ScoreBand::HIGHEST_SCORE - ScoreBand::LOWEST_SCORE) * 100, 2);
    }

    /** How far the next band is, e.g. "38 points to Excellent"; null at the top. */
    public function nextBandGap(): ?string
    {
        $next = $this->band()?->next();

        return $next === null ? null : trans_choice(':points point to :band|:points points to :band', $next->min() - $this->score, ['points' => $next->min() - $this->score, 'band' => $next->getLabel()]);
    }

    public function hasReport(): bool
    {
        return $this->insights !== null && array_filter($this->insights, fn (?int $value) => $value !== null) !== [];
    }

    /**
     * The report's score factors, heaviest first — only those the report states.
     *
     * @return list<array{label: string, value: string, caption: string, rating: 'good'|'fair'|'poor', status: string, impact: string}>
     */
    public function factors(): array
    {
        $late = $this->figure('late_payments_last_12_months');
        $usage = $this->cardUsage();
        $age = $this->figure('credit_age_months');
        $enquiries = $this->figure('enquiries_last_6_months');
        $factors = [];

        if ($late !== null) {
            $defaulted = ($this->figure('accounts_written_off') ?? 0) > 0 || ($this->figure('overdue_amount') ?? 0) > 0;

            $factors[] = $this->factor(
                __('Payment history'),
                (string) $late,
                __('late payments in the last 12 months'),
                match (true) {
                    $defaulted || $late >= 3 => 'poor',
                    $late > 0 => 'fair',
                    default => 'good',
                },
                __('High impact'),
            );
        }

        if ($usage !== null) {
            $factors[] = $this->factor(
                __('Card usage'),
                $usage.'%',
                __('of your ₹:limit card limit in use', ['limit' => IndianNumberFormatter::format((int) $this->figure('card_limit'))]),
                match (true) {
                    $usage > self::HIGH_USAGE => 'poor',
                    $usage > self::HEALTHY_USAGE => 'fair',
                    default => 'good',
                },
                __('High impact'),
            );
        }

        if ($age !== null) {
            $factors[] = $this->factor(
                __('Credit age'),
                $this->duration($age),
                __('since your oldest account was opened'),
                match (true) {
                    $age < 24 => 'poor',
                    $age < 48 => 'fair',
                    default => 'good',
                },
                __('Medium impact'),
            );
        }

        if ($enquiries !== null) {
            $factors[] = $this->factor(
                __('Credit enquiries'),
                (string) $enquiries,
                __('loan or card applications in the last 6 months'),
                match (true) {
                    $enquiries >= 5 => 'poor',
                    $enquiries >= 3 => 'fair',
                    default => 'good',
                },
                __('Low impact'),
            );
        }

        return $factors;
    }

    /**
     * Account totals shown beside the factors.
     *
     * @return list<array{label: string, value: string, alert: bool}>
     */
    public function figures(): array
    {
        $active = $this->figure('accounts_active');
        $total = $this->figure('accounts_total');
        $outstanding = $this->figure('outstanding_balance');
        $overdue = $this->figure('overdue_amount');

        return array_values(array_filter([
            $active === null ? null : [
                'label' => __('Active accounts'),
                'value' => $total === null ? (string) $active : __(':active of :total', ['active' => $active, 'total' => $total]),
                'alert' => false,
            ],
            $outstanding === null ? null : ['label' => __('Outstanding'), 'value' => '₹'.IndianNumberFormatter::format($outstanding), 'alert' => false],
            $overdue === null ? null : ['label' => __('Overdue'), 'value' => '₹'.IndianNumberFormatter::format($overdue), 'alert' => $overdue > 0],
        ]));
    }

    public function tipsHeading(): string
    {
        return match ($this->band()) {
            null => __('How to build your credit score'),
            ScoreBand::Excellent => __('How to keep your score excellent'),
            ScoreBand::Good => __('How to reach an excellent score'),
            default => __('How to improve your score'),
        };
    }

    /**
     * Pointers from the visitor's own report first (most urgent first), then
     * the band's general ones, never two on the same theme.
     *
     * @return list<array{title: string, detail: string, tone: 'urgent'|'improve'|'keep'|'info'}>
     */
    public function tips(): array
    {
        return collect([...$this->reportTips(), ...$this->bandTips()])
            ->unique('theme')
            ->take(self::MAX_TIPS)
            ->map(fn (array $tip) => ['title' => $tip['title'], 'detail' => $tip['detail'], 'tone' => $tip['tone']])
            ->values()
            ->all();
    }

    /**
     * @return list<array{theme: string, title: string, detail: string, tone: string}>
     */
    private function reportTips(): array
    {
        if (! $this->hasReport()) {
            return [];
        }

        $writtenOff = $this->figure('accounts_written_off');
        $overdue = $this->figure('overdue_amount');
        $late = $this->figure('late_payments_last_12_months');
        $usage = $this->cardUsage();
        $enquiries = $this->figure('enquiries_last_6_months');
        $age = $this->figure('credit_age_months');
        $tips = [];

        if ($writtenOff > 0) {
            $tips[] = $this->tip('defaults', 'urgent', __('Resolve written-off or settled accounts'), trans_choice(
                ':count account on your report is marked written-off, settled or in default. Pay what is left in full, collect a No Dues Certificate and ask the lender to update :bureau — these marks hold your score down for years.|:count accounts on your report are marked written-off, settled or in default. Pay what is left in full, collect a No Dues Certificate and ask each lender to update :bureau — these marks hold your score down for years.',
                $writtenOff,
                ['bureau' => $this->bureau],
            ));
        }

        if ($overdue > 0) {
            $tips[] = $this->tip('overdue', 'urgent', __('Clear your overdue amount now'), __('₹:amount is overdue on your accounts. Pay it in full straight away — every month it stays unpaid is reported to :bureau and pulls your score down further.', [
                'amount' => IndianNumberFormatter::format($overdue),
                'bureau' => $this->bureau,
            ]));
        }

        if ($late > 0) {
            $tips[] = $this->tip('payments', $late >= 3 ? 'urgent' : 'improve', __('Never miss a due date'), trans_choice(
                ':count late payment was reported in the last 12 months. Set up auto-debit (e-NACH) or standing instructions for every EMI and card bill so it does not happen again.|:count late payments were reported in the last 12 months. Set up auto-debit (e-NACH) or standing instructions for every EMI and card bill so it does not happen again.',
                $late,
            ));
        } elseif ($late === 0 && ! $writtenOff && ! $overdue) {
            $tips[] = $this->tip('payments', 'keep', __('Keep your clean repayment record'), __('No late payments in the last 12 months — the biggest single factor in your score. Keep auto-pay on for every EMI and card bill so it stays that way.'));
        }

        if ($usage !== null && $usage > self::HEALTHY_USAGE) {
            $tips[] = $this->tip('usage', $usage > self::HIGH_USAGE ? 'urgent' : 'improve', __('Bring card usage under 30%'), __('You are using :percent% of your ₹:limit card limit. Pay balances down, ideally before the statement date, so less than 30% shows as used. Spreading spends across cards helps too.', [
                'percent' => $usage,
                'limit' => IndianNumberFormatter::format((int) $this->figure('card_limit')),
            ]));
        } elseif ($usage !== null) {
            $tips[] = $this->tip('usage', 'keep', __('Your card usage is healthy'), __('Only :percent% of your card limit is in use. Keep it under 30%, and avoid maxing out a card even for one month.', ['percent' => $usage]));
        }

        if ($enquiries >= 3) {
            $tips[] = $this->tip('applications', 'improve', __('Pause new loan and card applications'), __(':count credit enquiries in the last 6 months make you look credit-hungry to lenders. Space out applications by at least 6 months, and compare offers through eligibility checks that do not touch your score.', ['count' => $enquiries]));
        }

        if ($age !== null && $age < 24) {
            $tips[] = $this->tip('history', 'improve', __('Give your credit history time'), __('Your oldest account is :age old. Keep it open and use it lightly — lenders look for at least 2–3 years of history, and your score rises as it grows.', ['age' => $this->duration($age)]));
        } elseif ($age >= 60) {
            $tips[] = $this->tip('history', 'keep', __('Keep your oldest accounts open'), __('Your credit history is :age long. Closing an old card shortens it, so keep old cards open with a small purchase now and then.', ['age' => $this->duration($age)]));
        }

        $order = ['urgent' => 0, 'improve' => 1, 'keep' => 2, 'info' => 3];

        return collect($tips)->sortBy(fn (array $tip) => $order[$tip['tone']])->values()->all();
    }

    /**
     * @return list<array{theme: string, title: string, detail: string, tone: string}>
     */
    private function bandTips(): array
    {
        $checkReport = $this->tip('report', 'info', __('Check your report for errors'), __('Look for loans you do not recognise, wrong overdue amounts or closed accounts still shown as open, and raise a dispute with :bureau — a correction can lift your score.', ['bureau' => $this->bureau]));
        $payInFull = $this->tip('usage', 'improve', __('Pay card bills in full, not the minimum'), __('Paying only the minimum due keeps your balance — and your card usage — high, and adds interest on top.'));
        $holdApplications = $this->tip('applications', 'improve', __('Hold off on new loan applications'), __('Every application adds an enquiry, and a rejection makes the next one harder. Build your score for 6–12 months first, then apply.'));
        $guarantor = $this->tip('guarantor', 'info', __('Think twice before standing guarantor'), __('A loan you guarantee shows on your report too — if the borrower misses EMIs, your score drops with theirs.'));

        return match ($this->band()) {
            null => [
                $this->tip('first_credit', 'info', __('Start with a secured credit card'), __('A card issued against a fixed deposit is the easiest first credit to get. Use a little of it each month and pay the bill in full.')),
                $this->tip('small_loan', 'info', __('Repay a small loan on time'), __('A small consumer-durable or gold loan repaid on schedule also builds your history. Borrow only what you need.')),
                $this->tip('wait', 'info', __('Check again in a few months'), __('A score usually appears 3–6 months after your first loan or card is reported to the bureaus.')),
            ],
            ScoreBand::Poor => [
                $this->tip('payments', 'urgent', __('Talk to your lender before you miss a payment'), __('If EMIs feel unmanageable, ask your lender about a revised repayment plan early — a missed or defaulted payment does far more damage than asking for help.')),
                $this->tip('first_credit', 'improve', __('Rebuild with a secured credit card'), __('A card issued against a fixed deposit is easy to get. Use a little of it each month and pay the bill in full — on-time payments slowly rebuild your score.')),
                $holdApplications,
                $checkReport,
            ],
            ScoreBand::Average => [
                $this->tip('payments', 'improve', __('Make the next 6 months spotless'), __('On-time payment of every EMI and card bill is the quickest way up a band. Set reminders or auto-debit for each due date.')),
                $payInFull,
                $holdApplications,
                $checkReport,
            ],
            ScoreBand::Good => [
                $payInFull,
                $this->tip('mix', 'info', __('Keep a healthy credit mix'), __('A mix of secured loans (home, car, gold) and unsecured credit (cards, personal loans), all repaid on time, helps you cross 750.')),
                $checkReport,
                $guarantor,
            ],
            ScoreBand::Excellent => [
                $this->tip('negotiate', 'keep', __('Use your score to get better rates'), __('Lenders keep their lowest rates and fastest approvals for scores like yours. Compare offers, and ask your current lenders for a better rate.')),
                $this->tip('payments', 'keep', __('Keep paying every bill on time'), __('Even one 30-day delay can knock a top score down sharply. Keep auto-pay on for every EMI and card bill.')),
                $this->tip('report', 'info', __('Review your report every few months'), __('Spot loans you do not recognise or wrong entries early, and raise a dispute with :bureau to keep your record clean.', ['bureau' => $this->bureau])),
                $guarantor,
            ],
        };
    }

    /** Card balance as a whole percentage of the card limit; null without open cards. */
    private function cardUsage(): ?int
    {
        $limit = $this->figure('card_limit');
        $balance = $this->figure('card_balance');

        return $limit > 0 && $balance !== null ? (int) round(max(0, $balance) / $limit * 100) : null;
    }

    private function figure(string $key): ?int
    {
        $value = $this->insights[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** 88 → "7 yrs 4 mths", 18 → "1 yr 6 mths", 24 → "2 yrs", 5 → "5 mths". */
    private function duration(int $months): string
    {
        $years = intdiv($months, 12);
        $months %= 12;

        return implode(' ', array_filter([
            $years > 0 ? trans_choice(':count yr|:count yrs', $years) : null,
            $months > 0 || $years === 0 ? trans_choice(':count mth|:count mths', $months) : null,
        ]));
    }

    /**
     * @param  'good'|'fair'|'poor'  $rating
     * @return array{label: string, value: string, caption: string, rating: 'good'|'fair'|'poor', status: string, impact: string}
     */
    private function factor(string $label, string $value, string $caption, string $rating, string $impact): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'caption' => $caption,
            'rating' => $rating,
            'status' => match ($rating) {
                'good' => __('Healthy'),
                'fair' => __('Fair'),
                'poor' => __('Needs attention'),
            },
            'impact' => $impact,
        ];
    }

    /**
     * @return array{theme: string, title: string, detail: string, tone: string}
     */
    private function tip(string $theme, string $tone, string $title, string $detail): array
    {
        return ['theme' => $theme, 'title' => $title, 'detail' => $detail, 'tone' => $tone];
    }
}
