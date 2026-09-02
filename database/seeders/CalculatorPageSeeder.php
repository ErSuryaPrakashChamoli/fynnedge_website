<?php

namespace Database\Seeders;

use App\Models\CalculatorPage;
use App\Support\Calculators\CalculatorPageKey;
use Illuminate\Database\Seeder;

/**
 * Starter copy for the calculator pages that have no LoanProduct behind them
 * (Fixed Deposit, SIP, Daily SIP, GST). Written here only as an initial
 * default — the digital marketing team edits this freely afterwards via
 * Content → Calculator Pages in the admin panel; nothing here is hardcoded
 * into the frontend.
 */
class CalculatorPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->page(
            CalculatorPageKey::FixedDeposit,
            <<<'HTML'
                <p>A fixed deposit (FD) is a lump sum you place with a bank or NBFC for a fixed period, at a fixed interest rate agreed upfront. In exchange for locking in your money, you typically earn a higher rate than a regular savings account — and you know exactly what you'll get back on the day you invest.</p>
                <h3>How this calculator works</h3>
                <p>Enter the amount you're depositing, the interest rate on offer, and the tenure. The calculator applies compounding to show you the maturity value — what your deposit grows to by the end of the term — and the total interest earned along the way.</p>
                <h3>A few things worth knowing</h3>
                <p>Interest rates vary by bank, tenure and deposit amount, and senior citizens are often offered a higher rate. Breaking an FD before maturity usually involves a penalty, so it's worth choosing a tenure you're comfortable committing to. This calculator gives you an estimate to plan with — always confirm the exact rate and terms with the bank or NBFC before investing.</p>
                HTML,
        );

        $this->page(
            CalculatorPageKey::Sip,
            <<<'HTML'
                <p>A Systematic Investment Plan (SIP) lets you invest a fixed amount in a mutual fund every month, rather than putting in a lump sum all at once. Over time, this smooths out the effect of market ups and downs — you buy more units when prices are low and fewer when they're high, an effect commonly called rupee-cost averaging.</p>
                <h3>How this calculator works</h3>
                <p>Enter your monthly investment amount, the return you expect, and how long you plan to stay invested. The calculator projects the maturity value using monthly compounding, and shows how much of that value came from your own contributions versus growth.</p>
                <h3>A few things worth knowing</h3>
                <p>The expected return you enter is an assumption, not a guarantee — actual mutual fund returns vary with market performance and are never fixed the way a fixed deposit's rate is. Mutual fund investments are subject to market risks; please read the scheme-related documents carefully before investing. This calculator is meant to help you plan, not as investment advice.</p>
                HTML,
        );

        $this->page(
            CalculatorPageKey::DailySip,
            <<<'HTML'
                <p>A daily SIP works the same way as a monthly SIP, except your contribution is invested every day instead of once a month. Smaller, more frequent instalments mean your money starts working sooner and spreads market timing risk across even more entry points.</p>
                <h3>How this calculator works</h3>
                <p>Enter the amount you'd invest each day, your expected annual return, and your investment horizon. The calculator compounds your contributions daily to estimate the maturity value at the end of the term, alongside the total amount you'd have actually invested.</p>
                <h3>A few things worth knowing</h3>
                <p>Not every mutual fund or platform supports daily SIPs — availability depends on the fund house and how your SIP is set up. As with any SIP, the return you enter here is an assumption for planning purposes; mutual fund investments are subject to market risks, and actual outcomes will depend on how the fund actually performs over your investment period.</p>
                HTML,
        );

        $this->page(
            CalculatorPageKey::Gst,
            <<<'HTML'
                <p>Goods and Services Tax (GST) is applied to most goods and services sold in India, at rates set by the government for each category. This calculator helps you move quickly between a base amount and a GST-inclusive amount, without doing the maths by hand.</p>
                <h3>How this calculator works</h3>
                <p>Choose whether you're adding GST to a base amount or extracting it from a total that already includes tax, enter the applicable GST rate, and the calculator shows the tax amount, the resulting total, and the CGST/SGST split for an intra-state supply.</p>
                <h3>A few things worth knowing</h3>
                <p>The applicable GST rate depends on the specific goods or service — always confirm the correct rate for your transaction with your accountant or the relevant GST notification, especially for anything filed with the tax department. This tool is a quick reckoner, not a substitute for professional tax advice.</p>
                HTML,
        );
    }

    private function page(CalculatorPageKey $key, string $body): void
    {
        CalculatorPage::query()->updateOrCreate(
            ['calculator_key' => $key->value],
            ['body' => $body],
        );
    }
}
