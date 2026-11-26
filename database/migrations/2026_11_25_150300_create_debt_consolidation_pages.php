<?php

use App\Enums\PublishStatus;
use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * The seven Debt Consolidation pages as General Pages rows, created as
 * DRAFTS with starter copy, SEO and draft FAQs for marketing to review and publish
 * (Website Pages → General Pages). Nothing goes live from this migration:
 * the pages also stay 404 until the service is switched on in Website
 * Settings → Debt Consolidation Settings. A row that already exists (an
 * editor created it first) is left untouched. down() removes only rows still
 * in draft and never edited by a person, so published or edited copy survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->pages() as $slug => $page) {
            if (Page::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $row = Page::query()->create([
                'title' => $page['title'],
                'slug' => $slug,
                'excerpt' => $page['excerpt'],
                'body' => $page['body'],
                'status' => PublishStatus::Draft,
            ]);

            $row->seoMeta()->create([
                'title' => $page['seo_title'],
                'description' => $page['seo_description'],
                'robots' => 'index, follow',
            ]);

            foreach ($page['faqs'] as $index => [$question, $answer]) {
                Faq::query()->create([
                    'question' => $question,
                    'answer' => '<p>'.e($answer).'</p>',
                    'sort_order' => $index + 1,
                    // Drafts, like the pages: each answer is reviewed before it goes live.
                    'status' => PublishStatus::Draft,
                    'faqable_type' => Page::class,
                    'faqable_id' => $row->getKey(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Page::query()
            ->whereIn('slug', array_keys($this->pages()))
            ->where('status', PublishStatus::Draft)
            ->whereColumn('updated_at', 'created_at')
            ->get()
            ->each(function (Page $page): void {
                $page->faqs()->delete();
                $page->seoMeta()->delete();
                $page->forceDelete();
            });
    }

    /**
     * @return array<string, array{title: string, excerpt: string, seo_title: string, seo_description: string, body: string, faqs: list<array{0: string, 1: string}>}>
     */
    private function pages(): array
    {
        return [
            'debt-consolidation' => [
                'title' => 'Debt Consolidation',
                'excerpt' => 'Paying several EMIs and credit card bills? See whether combining them into one loan would really cost you less — and get help applying and closing your old loans.',
                'seo_title' => 'Debt Consolidation in India — Combine Loans & Cards | FynnEdge',
                'seo_description' => 'Combine personal loans, credit cards and business loans into one repayment. Free assessment of total cost, foreclosure charges and savings with FynnEdge.',
                'body' => <<<'HTML'
<h2>What is debt consolidation?</h2>
<p>Debt consolidation means taking one new loan to close several existing ones — for example two personal loans and a credit card balance — so you repay a single lender instead of many. Done well, it can lower the interest you pay, simplify your month and give you a clear end date. Done badly, it can stretch your repayment and cost more in total.</p>
<h2>Will consolidating actually save me money?</h2>
<p>Not always, and a lower EMI alone does not prove it. A longer tenure can lower the EMI while raising the total you repay. FynnEdge compares, line by line, what you would pay by keeping your loans with what one new loan would cost — including foreclosure charges on your old loans, GST, the new lender's processing fee and the difference in tenure. You see the net saving (or extra cost) before you decide anything.</p>
<h2>How FynnEdge helps</h2>
<ul>
<li><strong>Assessment:</strong> we look at all your loans and cards together — total outstanding, EMIs, interest rates, remaining tenure and overdue amounts.</li>
<li><strong>Suitability:</strong> we tell you honestly whether consolidating looks financially sensible, and why.</li>
<li><strong>Lender options:</strong> where it makes sense, we shortlist banks and NBFCs whose published criteria fit your profile and help you apply — only with your consent.</li>
<li><strong>Closure support:</strong> after disbursement we help coordinate closure of each old loan and track every NOC until it is received.</li>
</ul>
<p>FynnEdge is a loan advisory and facilitation company, not a lender. The new lender alone decides whether to approve your loan and on what terms.</p>
HTML,
                'faqs' => [
                    ['Is FynnEdge a lender?', 'No. FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and facilitation company. We assess your debts, explain your options and help you apply to banks and NBFCs. The lender decides on approval, interest rate and disbursement.'],
                    ['Does the assessment affect my credit score?', 'No. Our assessment uses the figures you enter. A lender will check your credit report only when you apply to that lender with your consent.'],
                    ['Is the assessment free?', 'Yes. The assessment and the calculator are free and come with no obligation to apply.'],
                ],
            ],
            'personal-debt-consolidation' => [
                'title' => 'Personal Debt Consolidation',
                'excerpt' => 'Combine personal loans, credit card dues, consumer EMIs and BNPL into one manageable repayment — if the numbers say it is worth it.',
                'seo_title' => 'Personal Debt Consolidation — Credit Cards & Personal Loans | FynnEdge',
                'seo_description' => 'Consolidate credit card dues, personal loans and EMIs into one loan. Compare total cost, FOIR and savings for free with FynnEdge.',
                'body' => <<<'HTML'
<h2>When personal debt consolidation can help</h2>
<p>Credit card balances often carry interest of 36–42% a year, and several small loans mean several due dates. Moving high-cost balances into one lower-rate loan can reduce your interest and make repayments easier to manage — provided the new loan's rate, fees and tenure leave you better off overall.</p>
<h2>Routes we assess for individuals</h2>
<ul>
<li><strong>Personal loan:</strong> unsecured, typically used to close credit cards, other personal loans and consumer EMIs.</li>
<li><strong>Loan against property:</strong> secured on your property, usually at a lower rate and longer tenure — but your property is at risk if you cannot repay, so we flag this clearly.</li>
<li><strong>Home loan balance transfer:</strong> moving an existing home loan to another lender at a better rate.</li>
<li><strong>Gold loan:</strong> for smaller, short-term consolidation.</li>
</ul>
<h2>What lenders look at</h2>
<p>Lenders consider your income, how much of it already goes to EMIs (FOIR), your credit history, employment and repayment record. Loans that are overdue, settled or written off generally cannot be consolidated until they are regularised; we will tell you if that applies.</p>
HTML,
                'faqs' => [
                    ['Can I consolidate credit card debt with a personal loan?', 'Often, yes. Many lenders allow a personal loan to close credit card outstanding. Whether it saves money depends on the new rate, fees and tenure, which our calculator compares for you.'],
                    ['Should I use a loan against property to clear unsecured debt?', 'It usually brings a lower rate, but it turns unsecured debt into debt secured on your home. Consider it only if the saving is meaningful and the repayment is comfortable; our assessment flags this trade-off.'],
                    ['What if one of my loans is overdue?', 'Tell us the overdue amount and days past due. Loans 90 or more days overdue, settled or written off usually cannot be consolidated straight away. An advisor will explain your options.'],
                ],
            ],
            'business-debt-consolidation' => [
                'title' => 'Business Debt Consolidation',
                'excerpt' => 'Several business loans, working capital lines and merchant advances? Find out whether one restructured facility would ease your cash flow and lower your total cost.',
                'seo_title' => 'Business Debt Consolidation for MSMEs | FynnEdge',
                'seo_description' => 'Consolidate business term loans, NBFC loans, machinery loans and working capital into one facility. Free cash-flow and DSCR assessment with FynnEdge.',
                'body' => <<<'HTML'
<h2>Why businesses consolidate</h2>
<p>Short-tenure NBFC loans, merchant advances and multiple term loans can drain monthly cash flow even when the business is profitable. Consolidating into a longer, lower-rate facility can free up working capital — but only when the total cost and the new repayment fit your cash flow.</p>
<h2>How we assess a business</h2>
<ul>
<li>All existing facilities — term loans, machinery and commercial vehicle loans, cash credit, overdraft, invoice and merchant financing.</li>
<li>Average monthly revenue and operating costs, to measure your debt-service coverage (DSCR) before and after.</li>
<li>Business vintage, turnover and any collateral available for a secured route.</li>
<li>Foreclosure charges and lock-in periods on existing facilities.</li>
</ul>
<h2>Routes for businesses</h2>
<p>Depending on your profile, options can include an unsecured business loan, a secured business or term loan, or a loan against property. Each lender has its own policy on which existing facilities it will take over; we match your case against the criteria lenders have shared with us and show you why a lender may or may not fit.</p>
HTML,
                'faqs' => [
                    ['Can working capital limits be consolidated?', 'Cash credit and overdraft limits are usually renewed or restructured rather than closed. Some lenders will take them over as part of a secured facility; we check each lender\'s policy.'],
                    ['What documents does a business need?', 'Typically recent bank statements, GST returns, ITRs and financial statements, plus statements of the loans to be closed. The exact list depends on the lender.'],
                    ['What is DSCR?', 'Debt-service coverage ratio: the cash your business generates each month divided by the loan repayments due. Lenders generally look for at least 1.2 to 1.5.'],
                ],
            ],
            'debt-consolidation-how-it-works' => [
                'title' => 'How Debt Consolidation Works',
                'excerpt' => 'From your first enquiry to the last NOC — what happens at each step, and who decides what.',
                'seo_title' => 'How Debt Consolidation Works — Step by Step | FynnEdge',
                'seo_description' => 'Understand each step of debt consolidation: assessment, savings comparison, lender application, disbursement and closure of old loans with NOCs.',
                'body' => <<<'HTML'
<h2>1. Assessment</h2>
<p>You list your loans and cards with their outstanding, EMI, rate and remaining tenure, plus your income (or business cash flow). We calculate your total debt, weighted interest rate and how much of your income goes to repayments.</p>
<h2>2. Comparison</h2>
<p>We compare keeping your loans with consolidating them: closure amounts including foreclosure charges and GST, the new loan amount, processing fees, the new EMI, the total you would repay and how long it takes to recover the costs. If consolidating costs more, we say so.</p>
<h2>3. Lender matching</h2>
<p>If it makes sense, we compare your case with the debt consolidation criteria of partner banks and NBFCs and explain which may fit and why. A match is not an approval.</p>
<h2>4. Application</h2>
<p>With your explicit consent for each lender, we help you apply and track the lender's decision. The lender assesses your application independently and decides on approval, rate and amount.</p>
<h2>5. Disbursement and closure</h2>
<p>Once the lender disburses, the old loans are closed — usually by the new lender paying them directly. We help track each payment, the closure request and the NOC. A loan is marked closed only when there is proof it has been closed.</p>
HTML,
                'faqs' => [
                    ['How long does consolidation take?', 'The assessment takes minutes. A lender\'s decision typically takes a few days to a few weeks, and closing old loans and receiving NOCs can take another two to four weeks.'],
                    ['Who pays the old lenders?', 'Usually the new lender pays the existing lenders directly from the loan amount. Where you receive the money, you must pay the foreclosure amounts promptly; we help you track each one.'],
                ],
            ],
            'debt-consolidation-calculator' => [
                'title' => 'Debt Consolidation Calculator',
                'excerpt' => 'Add your loans and compare your current repayments with one consolidated loan — total cost, monthly outgo, foreclosure charges, affordability and break-even.',
                'seo_title' => 'Debt Consolidation Calculator — EMI, Savings & Break-even | FynnEdge',
                'seo_description' => 'Free debt consolidation calculator: add multiple loans and see the new EMI, total repayment, foreclosure charges, FOIR and true net savings.',
                'body' => <<<'HTML'
<h2>How the calculator works</h2>
<p>For each loan we project what you would still pay if you kept it, using your EMI and remaining months (or your rate). We then estimate what it costs to close it — outstanding principal, any overdue amount, the foreclosure charge and GST — and size a new loan that covers those closures, grossed up for a processing fee the lender deducts. The new loan's EMI and total repayment are calculated on a standard reducing-balance basis.</p>
<p>The <strong>net saving</strong> is the difference between everything you would pay by keeping your loans and everything you would pay by consolidating, including fees. A positive number means consolidating costs less; a negative number means it costs more, even if the EMI is lower.</p>
HTML,
                'faqs' => [
                    ['Why is my net saving negative when the EMI is lower?', 'A longer tenure spreads repayment over more months, so the EMI falls but the total interest can rise. Fees and foreclosure charges add to the cost. The calculator shows the total so you can see the real picture.'],
                    ['Are the rates in the calculator offers?', 'No. They are assumptions you can change. Lenders set their own rates based on your profile.'],
                ],
            ],
            'debt-consolidation-assessment' => [
                'title' => 'Free Debt Consolidation Assessment',
                'excerpt' => 'Tell us about your loans in about five minutes. You will see your debt summary and an honest view of whether consolidating could help — and an advisor will follow up.',
                'seo_title' => 'Free Debt Consolidation Assessment | FynnEdge',
                'seo_description' => 'Get a free, no-obligation debt consolidation assessment: total debt, FOIR, suitability score and savings comparison, reviewed by a FynnEdge advisor.',
                'body' => <<<'HTML'
<h2>What you will need</h2>
<ul>
<li>For each loan or card: the lender, outstanding amount, EMI or planned payment, interest rate and remaining months (a recent statement helps).</li>
<li>Your monthly income after tax — or, for a business, average monthly revenue and operating costs.</li>
<li>Any foreclosure charges you know of.</li>
</ul>
<p>You can save and come back later: your progress is kept in your FynnEdge profile, which you open with a one-time code on your mobile.</p>
HTML,
                'faqs' => [
                    ['Can I save and finish later?', 'Yes. After you verify your mobile number, your answers are saved as you go. Sign in from "Existing Customer" with the same number to continue.'],
                    ['Will you share my details with lenders?', 'Not without your consent. We ask for your separate consent for each lender before sharing anything.'],
                ],
            ],
            'debt-consolidation-faqs' => [
                'title' => 'Debt Consolidation FAQs',
                'excerpt' => 'Straight answers about consolidating loans and credit cards, costs, credit scores and how FynnEdge works.',
                'seo_title' => 'Debt Consolidation FAQs | FynnEdge',
                'seo_description' => 'Answers to common questions on debt consolidation: costs, foreclosure charges, credit score impact, eligibility, NOCs and how FynnEdge helps.',
                'body' => '',
                'faqs' => [
                    ['What is debt consolidation?', 'Taking one new loan to close several existing loans or card balances, so you repay one lender instead of many.'],
                    ['Does consolidation always reduce what I pay?', 'No. It reduces your total cost only when the new rate, fees and tenure work in your favour. A lower EMI over a longer period can cost more in total. Our calculator shows the full comparison.'],
                    ['What charges should I expect?', 'Existing lenders may charge a foreclosure or part-prepayment fee (plus 18% GST), and the new lender usually charges a processing fee (plus GST). Secured loans may add valuation, legal and stamp duty costs.'],
                    ['Will consolidation affect my credit score?', 'Applying for a new loan leads to a credit enquiry, which can lower your score slightly for a while. Closing loans on time and reducing card utilisation can help over time. Missing payments on the new loan will hurt your score.'],
                    ['Can every loan be consolidated?', 'No. Each lender decides which existing loans it will take over. Loans that are overdue by 90 days or more, settled, written off or under dispute generally cannot be consolidated until resolved, and some loans have lock-in periods.'],
                    ['How is my old loan closed?', 'Usually the new lender pays the closure amount directly to the old lender. You or we then request the closure and the NOC (no-objection certificate).'],
                    ['What is an NOC and why does it matter?', 'An NOC is the old lender\'s confirmation that the loan is fully repaid and closed. Keep it safe, and check your credit report shows the loan as closed.'],
                    ['Does FynnEdge charge me a fee?', 'The assessment and the calculator are free. Any fee or charge payable to FynnEdge or to a lender will be disclosed to you before you apply.'],
                    ['Is my information safe?', 'Your information is stored securely, documents are encrypted, and we share it with a lender only after you consent to that specific lender.'],
                    ['Can businesses consolidate debt?', 'Yes. Proprietorships, partnerships and companies can consolidate business loans and facilities. We assess your cash flow and debt-service coverage before suggesting a route.'],
                ],
            ],
        ];
    }
};
