<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->page('privacy-policy', 'Privacy Policy', $this->privacyPolicy());
        $this->page('terms', 'Terms & Conditions', $this->terms());
        $this->page('disclaimer', 'Disclaimer', $this->disclaimer());
        $this->page('grievance', 'Grievance Redressal', $this->grievance());
        $this->page('careers', 'Careers', $this->careers());
    }

    private function page(string $slug, string $title, string $body): void
    {
        Page::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'excerpt' => null,
                'body' => $body,
                'status' => PublishStatus::Published,
                'published_at' => now(),
            ],
        );
    }

    private function privacyPolicy(): string
    {
        return <<<'HTML'
            <p>FynnEdge Advisory (OPC) Pvt Ltd ("FynnEdge", "we", "us") is a loan advisory and distribution
            business. This policy explains what information we collect when you use fynnedge.com, why we
            collect it, and who we share it with.</p>

            <h2>Information we collect</h2>
            <p>When you check your eligibility or apply for a loan through FynnEdge, we collect the details you
            provide in that journey — typically your name, contact details, date of birth, employment and
            income information, existing obligations, and the loan you're interested in. If you go on to
            select a lender, we also collect the identity, address and income documents that lender requires.</p>
            <p>We also automatically collect basic technical information (such as your IP address, browser,
            and the page that referred you) to keep the site secure and to understand which channels bring
            visitors to FynnEdge.</p>

            <h2>How we use it</h2>
            <ul>
                <li>To assess which of our partner lenders' published criteria you are likely to meet.</li>
                <li>To pass your application and documents to the lender you choose, so they can process it.</li>
                <li>To communicate with you about your application.</li>
                <li>To improve the eligibility and application experience on this site.</li>
            </ul>

            <h2>Credit bureau checks</h2>
            <p>Checking your eligibility on FynnEdge does <strong>not</strong> involve a credit bureau check —
            it is based only on the information you provide. If you go on to apply with a lender, that lender
            may need to check your credit report as part of their own underwriting. We only ever request your
            consent for this separately and explicitly, and record when and how that consent was given. We do
            not perform a credit bureau check ourselves without your consent.</p>

            <h2>Who we share information with</h2>
            <p>We share your information with the specific lender you choose to proceed with, so they can
            evaluate and process your application. We do not sell your information to third parties, and we
            do not share it with any lender you have not chosen to apply with.</p>

            <h2>How long we keep it</h2>
            <p>We retain your information for as long as needed to provide our service and to meet our legal
            and regulatory record-keeping obligations, after which it is deleted or anonymised.</p>

            <h2>Your rights</h2>
            <p>You can ask us what information we hold about you, ask us to correct it, or ask us to delete
            it (subject to any information we're required to retain by law). To do so, write to us using the
            contact details on our <a href="/contact">Contact</a> page.</p>

            <h2>Changes to this policy</h2>
            <p>We may update this policy from time to time. The version published on this page is always the
            current one.</p>
            HTML;
    }

    private function terms(): string
    {
        return <<<'HTML'
            <p>These Terms & Conditions govern your use of fynnedge.com, operated by FynnEdge Advisory (OPC)
            Pvt Ltd ("FynnEdge"). By using this site, you agree to these terms.</p>

            <h2>What FynnEdge is — and isn't</h2>
            <p>FynnEdge is a loan advisory and distribution business. We help you identify banks and NBFCs
            ("lenders") you are likely to be eligible with, based on the profile you share with us. FynnEdge
            is not a bank or NBFC, does not lend money itself, and does not guarantee that any lender will
            approve your application.</p>

            <h2>Eligibility results are indicative</h2>
            <p>The eligibility results shown on FynnEdge are based on the information you provide and each
            lender's published criteria, as configured on this site. They are indicative only, and are
            always subject to the lender's own verification, documentation and underwriting. A lender may
            still decline an application that FynnEdge indicated as eligible, or offer different terms.</p>

            <h2>Accuracy of information</h2>
            <p>You agree to provide accurate, current information when using this site. FynnEdge and its
            lending partners rely on the information you provide to assess your eligibility and process your
            application — inaccurate information may result in a lender declining your application.</p>

            <h2>Fees</h2>
            <p>Using FynnEdge to check your eligibility is free. We do not charge you a fee to check
            eligibility or to submit an application. Any fees charged by a lender (such as a processing fee)
            are disclosed by that lender before you proceed, and are between you and the lender.</p>

            <h2>Third-party lenders</h2>
            <p>Once you choose a lender and submit your application and documents, that lender's own terms,
            privacy policy and processes govern how they handle your application from that point on.
            FynnEdge is not responsible for a lender's decisions, timelines, or the terms of any loan
            ultimately offered.</p>

            <h2>Governing law</h2>
            <p>These terms are governed by the laws of India, and any disputes are subject to the exclusive
            jurisdiction of the courts at the location of FynnEdge's registered office.</p>
            HTML;
    }

    private function disclaimer(): string
    {
        return <<<'HTML'
            <p>FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory and distribution business. FynnEdge is not
            a bank, NBFC, or lender, and does not itself sanction or disburse loans.</p>

            <h2>No guarantee of approval</h2>
            <p>An "eligible" or "conditional" result on FynnEdge is not an offer, sanction, or guarantee of a
            loan. Every application is subject to the chosen lender's own verification, documentation and
            underwriting, and the lender may approve, decline, or offer different terms than indicated.</p>

            <h2>Interest rates and charges</h2>
            <p>Interest rates, processing fees and other charges shown on this site are indicative ranges
            provided by our lending partners and may change without notice. The final rate and charges
            applicable to your loan are determined by the lender at the time of sanction.</p>

            <h2>No financial advice</h2>
            <p>Content on FynnEdge — including loan product descriptions, calculators and eligibility
            guidance — is provided for general information only and does not constitute financial, legal or
            tax advice. You should independently evaluate any loan offer before accepting it.</p>

            <h2>Third-party lenders</h2>
            <p>FynnEdge is not responsible for the acts, omissions, products or services of any lender it
            refers you to. Your loan agreement, if any, is directly between you and the lender.</p>
            HTML;
    }

    private function grievance(): string
    {
        return <<<'HTML'
            <p>FynnEdge Advisory (OPC) Pvt Ltd is committed to resolving customer concerns promptly and
            fairly. If you have a complaint about your experience on FynnEdge — including how your
            information was handled, or an issue with the eligibility or application process — here's how to
            reach us.</p>

            <h2>Step 1: Contact us directly</h2>
            <p>Write to us using the details on our <a href="/contact">Contact</a> page, describing your
            concern and, if applicable, the application or eligibility result it relates to. We aim to
            acknowledge every complaint within 2 business days and resolve it within 15 business days.</p>

            <h2>Step 2: Escalate to the Grievance Officer</h2>
            <p>If you're not satisfied with the response, you may escalate your complaint in writing to our
            Grievance Officer, whose contact details are published on our <a href="/contact">Contact</a>
            page. Please include your original complaint reference so we can review it in full.</p>

            <h2>Complaints about a specific lender</h2>
            <p>If your complaint concerns the conduct, decision or service of a lender you applied with after
            being referred by FynnEdge, that lender's own grievance redressal process — published on their
            website or loan documentation — applies to that part of your complaint. We're glad to help you
            locate the right contact if you're unsure where to raise it.</p>
            HTML;
    }

    private function careers(): string
    {
        return <<<'HTML'
            <p>FynnEdge Advisory (OPC) Pvt Ltd is a loan advisory business focused on making it simpler for
            people in India to find the right lender for a personal loan, home loan, business loan, or loan
            against property.</p>

            <h2>Working at FynnEdge</h2>
            <p>We're a small, early-stage team, so everyone who joins has real ownership over what they
            build — whether that's product, credit policy, partnerships, or customer experience.</p>

            <h2>Open roles</h2>
            <p>We don't have any open positions listed right now. If you'd like to be considered for future
            roles, write to us using the details on our <a href="/contact">Contact</a> page and tell us a bit
            about what you're looking for.</p>
            HTML;
    }
}
