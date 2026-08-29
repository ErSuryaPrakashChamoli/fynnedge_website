<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $this->article(
            'how-emis-work',
            'How EMIs Work: Understanding Your Monthly Loan Repayment',
            'What an EMI actually covers, why more of it goes to interest early on, and how tenure changes your total cost.',
            $this->howEmisWork(),
        );

        $this->article(
            'credit-score-and-why-it-matters',
            'Understanding Your Credit Score and Why It Matters',
            'What goes into your credit score, why lenders rely on it, and practical ways to improve it before you apply.',
            $this->creditScore(),
        );

        $this->article(
            'what-is-foir',
            'What Is FOIR and How It Affects Your Loan Eligibility',
            'FOIR is one of the biggest reasons an application gets a lower amount than expected — here\'s what it means.',
            $this->foir(),
        );

        $this->article(
            'documents-needed-for-a-personal-loan',
            'Documents You\'ll Need for a Personal Loan',
            'The standard identity, address and income documents lenders ask for, and why each one matters.',
            $this->personalLoanDocuments(),
        );

        $this->article(
            'personal-loan-vs-loan-against-property',
            'Personal Loan vs. Loan Against Property: Which Should You Choose',
            'Two very different ways to borrow — one fast and unsecured, one larger and backed by your property.',
            $this->personalLoanVsLap(),
        );

        $this->article(
            'fixed-vs-floating-interest-rates',
            'Fixed vs. Floating Interest Rates, Explained',
            'The real trade-off between rate certainty and the chance of paying less over time.',
            $this->fixedVsFloating(),
        );

        $this->article(
            'improve-loan-approval-chances',
            'How to Improve Your Chances of Loan Approval',
            'The handful of factors that consistently matter most across lenders, and how to get ahead of them.',
            $this->improveApprovalChances(),
        );

        $this->article(
            'understanding-loan-processing-fees',
            'Understanding Processing Fees and Other Loan Charges',
            'The charges beyond your interest rate that affect what a loan actually costs you.',
            $this->processingFees(),
        );

        $this->article(
            'home-loan-eligibility-explained',
            'Home Loan Eligibility: What Lenders Actually Look At',
            'Income, property value, and existing obligations — how lenders weigh each one for a home loan.',
            $this->homeLoanEligibility(),
        );

        $this->article(
            'business-loan-eligibility-for-self-employed',
            'Business Loans: Eligibility Basics for Self-Employed Applicants',
            'What changes when your income isn\'t a fixed monthly salary, and how to present it well.',
            $this->businessLoanEligibility(),
        );
    }

    private function article(string $slug, string $title, string $excerpt, string $body): void
    {
        Article::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );
    }

    private function howEmisWork(): string
    {
        return <<<'HTML'
            <p>An EMI (Equated Monthly Instalment) is the fixed amount you pay your lender every month until a
            loan is fully repaid. It's "equated" because the amount stays the same each month — but what that
            amount is actually paying for changes a lot over the life of the loan.</p>

            <h2>Every EMI is part interest, part principal</h2>
            <p>Each instalment repays two things at once: interest on the amount you still owe, and a portion
            of the original amount borrowed (the principal). Early in the loan, most of your EMI goes toward
            interest, because the outstanding balance is still high. As you keep paying, the balance shrinks,
            so less of each EMI is interest and more goes toward principal — even though the EMI itself
            doesn't change.</p>

            <h2>Why tenure matters more than it looks</h2>
            <p>A longer tenure lowers your EMI, which is why it's tempting to stretch a loan out. But a longer
            tenure also means you pay interest for longer, so the total interest paid over the life of the
            loan is higher — often significantly. A shorter tenure raises the EMI but reduces total interest
            paid. There's no universally "right" tenure; it depends on what monthly amount is comfortable
            against your income, and how much the extra interest cost of a longer tenure is worth to you.</p>

            <h2>What changes your EMI after disbursal</h2>
            <p>On a fixed-rate loan, your EMI stays constant for the full tenure. On a floating-rate loan, your
            EMI (or your tenure, depending on the lender's policy) can change if the benchmark interest rate
            moves. It's worth checking which type of rate you're being offered before you commit — see our
            guide on <a href="/resources/fixed-vs-floating-interest-rates">fixed vs. floating rates</a> for more.</p>

            <p>Use our <a href="/calculators">EMI calculator</a> to see how a specific loan amount, rate, and
            tenure translate into a real monthly figure before you apply.</p>
            HTML;
    }

    private function creditScore(): string
    {
        return <<<'HTML'
            <p>Your credit score is a three-digit number (typically ranging from 300 to 900 in India) that
            summarises how reliably you've repaid debt in the past. Lenders use it as one of the first checks
            when deciding whether to offer you a loan, and often what interest rate to offer.</p>

            <h2>What actually affects your score</h2>
            <ul>
                <li><strong>Repayment history</strong> — on-time payments across credit cards and existing
                loans matter more than almost anything else.</li>
                <li><strong>Credit utilisation</strong> — how much of your available credit card limit you're
                using. Consistently maxing out cards can lower your score even if you pay on time.</li>
                <li><strong>Length of credit history</strong> — a longer track record of responsibly used
                credit generally helps.</li>
                <li><strong>Mix of credit</strong> — a healthy mix of secured (like a car loan) and unsecured
                (like a credit card) debt is viewed more favourably than relying on one type.</li>
                <li><strong>Recent credit applications</strong> — applying for many loans or cards in a short
                window can temporarily lower your score, since it may look like financial stress.</li>
            </ul>

            <h2>Why lenders care so much</h2>
            <p>A credit score is a fast, standardised way for a lender to estimate risk before looking at your
            full profile. A strong score doesn't guarantee approval on its own — income, existing obligations
            and the specific lender's policies matter too — but a poor score is one of the most common reasons
            an application is declined outright, regardless of income.</p>

            <h2>Practical ways to improve it</h2>
            <p>Pay every credit card and loan instalment on time, every time — this has the single biggest
            impact. Keep credit card balances well below your limit. Avoid applying for multiple loans or
            cards in a short period. And don't close your oldest credit accounts, since they contribute to
            your credit history length. Improvement is usually gradual — meaningful change typically takes a
            few months of consistent behaviour, not a single action.</p>
            HTML;
    }

    private function foir(): string
    {
        return <<<'HTML'
            <p>FOIR stands for Fixed Obligation to Income Ratio. It's one of the most common reasons someone
            with a good income and credit score still gets offered a smaller loan amount than they expected —
            or gets declined altogether.</p>

            <h2>What it measures</h2>
            <p>FOIR compares your total fixed monthly obligations — existing EMIs, credit card minimum
            payments, and the EMI of the new loan you're applying for — against your monthly income. It's
            expressed as a percentage: the higher the percentage, the more of your income is already
            committed to fixed payments, and the less comfortable a lender is extending you more credit.</p>

            <h2>Why it matters more than income alone</h2>
            <p>Two applicants can have identical salaries and very different FOIRs. Someone with no existing
            EMIs has far more room to take on a new loan than someone already paying off a car loan and two
            credit cards, even at the same income level. Lenders use FOIR because income alone doesn't tell
            them how much of that income is actually free.</p>

            <h2>What lenders typically look for</h2>
            <p>Each lender sets its own maximum acceptable FOIR, and it can vary by income band and loan type
            — this isn't a single fixed number across the industry. Generally, a lower FOIR gives you access
            to a larger loan amount, a wider choice of lenders, and sometimes a better interest rate.</p>

            <h2>How to improve your FOIR before applying</h2>
            <p>Paying down or closing an existing small loan before applying can meaningfully improve your
            FOIR. Avoiding new credit card debt in the months before you apply helps too. If your FOIR is
            high because of one large existing EMI, opting for a longer tenure on the new loan (which lowers
            its own EMI) can sometimes bring your FOIR back within a lender's threshold — though as covered in
            our <a href="/resources/how-emis-work">EMI guide</a>, that
            comes with a higher total interest cost.</p>
            HTML;
    }

    private function personalLoanDocuments(): string
    {
        return <<<'HTML'
            <p>Personal loan documentation is usually the same handful of items across most lenders, though
            exact requirements vary. Having these ready before you start speeds up the process considerably.</p>

            <h2>Identity and address proof</h2>
            <p>A PAN card is required by virtually every lender for a loan in India, since it's tied to your
            income tax and credit history. Alongside it, you'll typically need one address proof document —
            commonly an Aadhaar card, passport, voter ID, or a recent utility bill.</p>

            <h2>Income proof</h2>
            <p>What's needed here depends on your employment type:</p>
            <ul>
                <li><strong>Salaried applicants</strong> usually provide their last 2–3 months' salary slips
                and Form 16 or the latest income tax return.</li>
                <li><strong>Self-employed applicants</strong> typically provide income tax returns for the
                last 2–3 years, along with business proof (registration certificate, GST filings, or similar).</li>
            </ul>

            <h2>Bank statements</h2>
            <p>Most lenders ask for your last 3–6 months of bank statements from the account your salary or
            business income is credited to. This lets them see your actual cash flow and existing EMI debits,
            which feeds directly into the FOIR calculation covered in our
            <a href="/resources/what-is-foir">FOIR guide</a>.</p>

            <h2>A photograph and signature</h2>
            <p>A recent passport-size photograph and a specimen signature are standard requirements for the
            application form itself, though many digital application flows now capture these electronically.</p>

            <p>Having clear, recent, correctly named copies of all of the above before you start your
            application on FynnEdge means there's nothing left to chase once you've selected a lender.</p>
            HTML;
    }

    private function personalLoanVsLap(): string
    {
        return <<<'HTML'
            <p>Personal loans and loans against property (LAP) both let you borrow for broad purposes, but
            they work very differently — and picking the wrong one for your situation can cost you.</p>

            <h2>Personal loans: fast, unsecured, smaller</h2>
            <p>A personal loan doesn't require you to pledge any collateral. That makes it faster to get —
            often disbursed within a few days — but lenders compensate for the higher risk with higher
            interest rates and comparatively lower loan amounts, usually capped well below what a LAP can
            offer. Tenures are also shorter, typically up to 5 years.</p>

            <h2>Loan against property: larger, secured, cheaper per rupee borrowed</h2>
            <p>A LAP is secured against residential or commercial property you already own. Because the
            lender has collateral to fall back on, interest rates are meaningfully lower than a personal
            loan, and the loan amount can be substantially higher — often a sizeable percentage of the
            property's market value. Tenures are also longer, sometimes extending to 15 years or more. The
            trade-off is a slower process (the property needs to be legally verified and valued) and the risk
            that comes with securing debt against an asset you own.</p>

            <h2>Which one fits your situation</h2>
            <p>A personal loan tends to make more sense for smaller amounts, urgent needs, or when you don't
            own eligible property. A LAP tends to make more sense for larger amounts — funding a business
            expansion, consolidating multiple debts, or major expenses — where the lower rate and longer
            tenure meaningfully reduce your monthly burden, and you're comfortable securing the loan against
            property you own.</p>

            <p>You can check your eligibility for both product types on FynnEdge without committing to
            either — <a href="/eligibility">start here</a>.</p>
            HTML;
    }

    private function fixedVsFloating(): string
    {
        return <<<'HTML'
            <p>Most loans in India are offered as either fixed-rate or floating-rate, and the choice has a
            real effect on both your monthly EMI and what the loan ultimately costs you.</p>

            <h2>Fixed rate: certainty</h2>
            <p>A fixed interest rate stays the same for the agreed period — often the entire tenure, though
            some lenders fix it only for an initial period before it becomes floating. Your EMI never changes
            because of market conditions, which makes budgeting simple and protects you if interest rates
            rise. The trade-off is that fixed rates are usually set slightly higher than floating rates at the
            time you take the loan, as compensation to the lender for taking on that certainty.</p>

            <h2>Floating rate: tied to the market</h2>
            <p>A floating rate moves up or down in line with a benchmark rate the lender uses (in India, most
            floating-rate retail loans today are linked to an external benchmark such as the RBI's repo
            rate). When the benchmark falls, your rate — and either your EMI or your remaining tenure —
            typically falls too. When it rises, so does your cost. Floating rates are usually lower than fixed
            rates at the outset, which is why they're the more common choice for home loans in particular.</p>

            <h2>Which one to choose</h2>
            <p>If predictable monthly payments matter more to you than optimising for the lowest possible
            long-term cost, a fixed rate removes the uncertainty. If you can tolerate some variability and
            believe rates are more likely to fall or stay flat over your loan's tenure, a floating rate has
            historically worked out cheaper for many borrowers over long tenures like home loans. There's no
            universally correct answer — it depends on your comfort with risk and how long you plan to hold
            the loan.</p>
            HTML;
    }

    private function improveApprovalChances(): string
    {
        return <<<'HTML'
            <p>Loan approval isn't a single yes/no formula, but a handful of factors consistently matter
            across almost every lender. Getting ahead of these before you apply meaningfully improves your
            odds — and often the terms you're offered.</p>

            <h2>Check your credit score before, not after</h2>
            <p>A low credit score is one of the most common reasons for an outright decline. Review yours in
            advance so you're not surprised, and see our
            <a href="/resources/credit-score-and-why-it-matters">credit
            score guide</a> if it needs work before you apply.</p>

            <h2>Keep your FOIR in mind</h2>
            <p>If you have existing EMIs or high credit card balances, consider paying some down before
            applying for a new loan — as explained in our
            <a href="/resources/what-is-foir">FOIR guide</a>, this
            directly affects both whether you're approved and how much you're offered.</p>

            <h2>Apply for an amount that matches your income</h2>
            <p>Requesting the maximum amount you think you might qualify for, rather than what you actually
            need, increases the chance of a lower FOIR pushing you toward decline. A request that's clearly
            proportionate to your income and existing obligations is viewed more favourably.</p>

            <h2>Get your documents right the first time</h2>
            <p>Mismatched names, expired ID proof, or bank statements that don't clearly show your salary
            credits are common causes of delay or rejection that have nothing to do with your actual
            eligibility. Review our <a href="/resources/documents-needed-for-a-personal-loan">documents
            guide</a> before you start.</p>

            <h2>Avoid applying to many lenders at once</h2>
            <p>Each formal loan application can trigger a credit check, and several in a short window can
            make your credit score look worse, not better. This is exactly why FynnEdge checks your
            eligibility against multiple lenders' published criteria first — using your self-reported
            profile — before you commit to a formal application with any one of them.</p>
            HTML;
    }

    private function processingFees(): string
    {
        return <<<'HTML'
            <p>The interest rate is the most visible number on a loan offer, but it isn't the only cost.
            Understanding the other charges helps you compare offers accurately — a lower rate with high fees
            can end up costing more than a slightly higher rate with none.</p>

            <h2>Processing fee</h2>
            <p>Most lenders charge a one-time processing fee, usually a percentage of the loan amount, deducted
            either upfront or from the disbursed amount. This covers the lender's cost of verifying your
            application, documents and credit profile. It's worth asking whether it's a flat fee or a
            percentage, and whether GST is added on top.</p>

            <h2>Prepayment and foreclosure charges</h2>
            <p>If you plan to repay your loan early — either partially or in full — some lenders charge a fee
            for this, particularly on fixed-rate loans. Floating-rate loans to individual borrowers are
            generally exempt from prepayment penalties under RBI guidelines, but it's still worth confirming
            with the specific lender before you commit, especially for a fixed-rate loan.</p>

            <h2>Late payment charges</h2>
            <p>Missing an EMI due date typically triggers a late payment fee on top of the missed instalment,
            and can also affect your credit score. These charges vary significantly between lenders.</p>

            <h2>Other charges to ask about</h2>
            <p>Depending on the loan type, you may also encounter documentation charges, legal and technical
            valuation fees (common on home loans and loans against property), and stamp duty on the loan
            agreement. None of these are hidden if you ask upfront — a lender is required to disclose them
            before you accept an offer, so it's always worth asking for the full fee schedule, not just the
            headline interest rate.</p>
            HTML;
    }

    private function homeLoanEligibility(): string
    {
        return <<<'HTML'
            <p>Home loan eligibility depends on more than just your salary. Lenders weigh several factors
            together, and understanding each one helps you know what to expect before you apply.</p>

            <h2>Income and employment stability</h2>
            <p>Lenders look at both how much you earn and how stable that income is. Salaried applicants with
            a longer tenure at their current employer are generally viewed as lower risk. Self-employed
            applicants are assessed on business income trends over recent years, typically via income tax
            returns, rather than a single year's figure.</p>

            <h2>Property value and loan-to-value ratio</h2>
            <p>Lenders don't finance 100% of a property's value — they finance a percentage of it, commonly
            referred to as the loan-to-value (LTV) ratio, with the rest expected as your own down payment.
            The exact LTV a lender offers depends on the loan amount and the lender's own policy, and it's
            usually higher for lower loan amounts.</p>

            <h2>FOIR and existing obligations</h2>
            <p>Because home loan EMIs run for many years and are typically a borrower's largest monthly
            commitment, lenders apply FOIR particularly carefully here. Existing car loans, personal loans or
            high credit card balances can meaningfully reduce the home loan amount you qualify for — see our
            <a href="/resources/what-is-foir">FOIR guide</a> for more.</p>

            <h2>Age and tenure</h2>
            <p>Because home loans commonly run 15–20 years, your age matters for how long a tenure you can be
            offered — most lenders require the loan to be fully repaid before you reach a maximum age, often
            around retirement age. A younger applicant generally has access to a longer tenure, which lowers
            the EMI even at the same loan amount.</p>

            <h2>Co-applicants</h2>
            <p>Adding a co-applicant — commonly a spouse — with their own income can increase the loan amount
            you jointly qualify for, since the lender can factor in combined income while still applying FOIR
            across both applicants' obligations.</p>
            HTML;
    }

    private function businessLoanEligibility(): string
    {
        return <<<'HTML'
            <p>Business loan eligibility for self-employed applicants is assessed differently from a salaried
            applicant's, because there's no single fixed monthly salary slip to point to. Here's what lenders
            typically look at instead.</p>

            <h2>Business vintage</h2>
            <p>Most lenders require a minimum period of continuous business operation — commonly a few
            years — before considering a business loan application. A longer, stable operating history is
            viewed as lower risk than a newly started business, even if current revenue looks strong.</p>

            <h2>Income tax returns and business financials</h2>
            <p>In place of salary slips, lenders typically ask for income tax returns over the last 2–3
            years, along with financial statements where applicable. Rather than a single year's profit,
            lenders generally look at the trend — is income growing, stable, or declining — since that shapes
            their view of how reliably you can service a loan going forward.</p>

            <h2>Business registration and continuity proof</h2>
            <p>Documents such as a business registration certificate, GST filings, or trade licence help
            establish that the business is a genuine, ongoing concern, not just a source of irregular income.</p>

            <h2>Bank statements reflecting business cash flow</h2>
            <p>Business bank account statements — typically the last 6–12 months — let a lender see actual
            cash flow, which can matter more than the profit figure on paper for judging whether EMIs can be
            comfortably absorbed.</p>

            <h2>FOIR still applies — just calculated differently</h2>
            <p>Because self-employed income can be irregular month to month, lenders often average it over a
            longer period before applying the same FOIR principle covered in our
            <a href="/resources/what-is-foir">FOIR guide</a> — comparing
            fixed obligations against that averaged income rather than a single month's figure.</p>
            HTML;
    }
}
