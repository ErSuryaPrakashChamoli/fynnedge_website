<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->page(
            'privacy-policy',
            'Your Privacy. Our Responsibility.',
            $this->privacyPolicy(),
            excerpt: 'Every loan journey on FynnEdge involves sharing some personal and financial information — with us, and with the lending partners you choose. This page explains, in plain language, what we collect, why, and what happens to it at each step.',
        );
        $this->page(
            'terms',
            'Your Journey With FynnEdge',
            $this->terms(),
            excerpt: 'Clear and straightforward terms for using FynnEdge.',
        );
        $this->page(
            'disclaimer',
            'Important Information Before You Proceed',
            $this->disclaimer(),
            excerpt: 'Please review these important points before relying on information provided through FynnEdge.',
        );
        $this->page('grievance', 'Grievance Redressal', $this->grievance());
        $this->page('careers', 'Careers', $this->careers());
        $this->page(
            'credit-report-terms',
            'Credit Report Terms of Use',
            $this->creditReportTerms(),
            excerpt: 'These are the terms you agree to the moment you start exploring a loan with FynnEdge — what we do, what your chosen lender decides, and how a credit check fits into the picture.',
        );
    }

    private function page(string $slug, string $title, string $body, ?string $excerpt = null): void
    {
        Page::query()->updateOrCreate(
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

    /**
     * A numbered, accordion-driven rewrite of the Privacy Policy — deliberately restructured
     * (13 named sections, grouped data categories, a dedicated "journey flow" section, a
     * standalone credit-information section) rather than a light edit of the previous
     * generic-template version, per an explicit request to make this page read as an
     * original FynnEdge document rather than a reworded third-party policy. Markup reuses
     * the same native <details>/<summary> accordion pattern as x-site.faq-accordion (no JS
     * framework needed) and the same numbered-badge style already used in loans/show.blade.php's
     * "How it works" list, so it looks native to this app rather than a bolted-on design.
     */
    private function privacyPolicy(): string
    {
        return str_replace(
            '{{DATE}}',
            now()->format('F j, Y'),
            <<<'HTML'
                <div class="not-prose flex flex-col divide-y divide-line border-y border-line">

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">01</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Information You Share With Us</span>
                                <span class="mt-1 block text-sm text-ink-muted">We only ask for what a lender genuinely needs to evaluate your loan request — nothing more.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p><strong class="text-ink">About you</strong> — your full name, mobile number, email address, date of birth, residential address, and similar identifying details.</p>
                            <p><strong class="text-ink">KYC information</strong> — your PAN, along with the KYC details and documents a lender's onboarding process requires.</p>
                            <p><strong class="text-ink">Employment &amp; income</strong> — your employment type, employer or business details, designation, work experience, and income, including business turnover where relevant.</p>
                            <p><strong class="text-ink">Loan requirement</strong> — the type of loan, amount, tenure and purpose you're exploring, and anything else that helps us understand what you need.</p>
                            <p><strong class="text-ink">Credit information</strong> — your credit score and credit report, where a credit check forms part of assessing your request. See Section 04.</p>
                            <p>We follow a need-based approach: exactly what we ask for depends on the loan product, where you are in your journey, and what the relevant lender's process actually requires.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">02</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Why We Need This Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">Every detail we collect serves a specific step in getting your request in front of the right lender.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>To understand exactly what you're looking for.</li>
                                <li>To run a preliminary assessment of the loan options you're likely to qualify for.</li>
                                <li>To match you with loan products and lenders suited to your profile.</li>
                                <li>To help you complete and submit an application.</li>
                                <li>To verify your identity and documents as part of KYC.</li>
                                <li>To connect you with a FynnEdge Loan Expert, where applicable, for guidance through your application.</li>
                                <li>To coordinate with the lender you choose, and keep you updated on your application.</li>
                                <li>To respond to your questions and provide customer support.</li>
                                <li>To help protect FynnEdge, our users and our lending partners against fraud and misuse.</li>
                                <li>To meet our legal, regulatory and record-keeping obligations.</li>
                            </ul>
                            <p class="mt-3">We use this information to help move your request forward — the decision to sanction, decline, or offer a loan on particular terms always rests with the lender, not FynnEdge. See Section 11.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">03</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your Information Across the Loan Journey</span>
                                <span class="mt-1 block text-sm text-ink-muted">Your information doesn't sit in one place — it moves with you, only as far as it needs to, at each stage.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ol class="flex flex-col gap-2 font-mono text-xs text-ink-faint">
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">You</span> share your requirement and profile</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">FynnEdge</span> reviews it against participating lenders' criteria</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">Your FynnEdge Loan Expert</span> may step in to guide your application</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">The bank, NBFC or lender you choose</span> receives your application</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">Loan processing &amp; decisioning</span> happens with that lender</li>
                            </ol>
                            <p>At each stage, only the information relevant to that step travels forward. Your requirement and profile help us shortlist suitable options; your KYC and income documents matter once you decide to apply; and your full application reaches a lender only when you've specifically chosen to proceed with them. We don't hand your details to a lender you haven't chosen.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">04</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Credit Information &amp; CIBIL</span>
                                <span class="mt-1 block text-sm text-ink-muted">Understanding your credit history helps us — and your chosen lender — see the fuller picture.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p><strong class="text-ink">What we mean by credit information</strong> — details from your credit report, such as your credit score, repayment history and existing credit lines, as maintained by Credit Information Companies authorised to hold this data in India (the bureaus you may know as CIBIL, Experian, Equifax or CRIF).</p>
                            <p><strong class="text-ink">Why it may matter</strong> — a credit check can help refine which lenders and loan terms are realistically available to you, alongside the income and employment details you've already shared.</p>
                            <p><strong class="text-ink">Where it comes from</strong> — where applicable, this information may be obtained from an authorised Credit Information Company, through a provider FynnEdge or a lender works with.</p>
                            <p><strong class="text-ink">Your consent</strong> — a credit bureau check isn't run automatically just because you're using FynnEdge. Where an actual credit enquiry is going to be made, we show you a separate, specific consent step at that point in your journey — it's not bundled into browsing this page or starting a loan enquiry.</p>
                            <p><strong class="text-ink">What a score does — and doesn't — mean</strong> — your credit score is one input into an eligibility picture, not a promise. A strong score doesn't guarantee approval, and a lower one doesn't automatically rule you out. The lender makes that call, on their own policy.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">05</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">When Information May Be Shared</span>
                                <span class="mt-1 block text-sm text-ink-muted">We share what's needed, with whoever needs it — and nowhere else.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>The bank, NBFC or other lending institution you choose to apply with.</li>
                                <li>Verification partners who help confirm your KYC or documents.</li>
                                <li>Credit Information Companies, when a credit check is part of your journey.</li>
                                <li>Technology and infrastructure providers who help us run FynnEdge securely.</li>
                                <li>Providers who help us send you SMS, email or WhatsApp updates about your application.</li>
                                <li>Government bodies, regulators or law enforcement, where we're legally required to disclose information.</li>
                            </ul>
                            <p class="mt-3 font-semibold text-ink">We do not sell your personal information.</p>
                            <p class="mt-2">Service providers above only ever receive what they need to do their specific job for us — they don't get to use your information for their own purposes, and they're bound to protect it.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">06</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Keeping Your Information Secure</span>
                                <span class="mt-1 block text-sm text-ink-muted">We treat your information the way we'd want our own handled — with layered, practical safeguards.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Access to your information is restricted to people and systems that genuinely need it.</li>
                                <li>We use role-based access controls, so what someone can see depends on what their role requires.</li>
                                <li>Authentication and monitoring help us detect and respond to unusual activity.</li>
                                <li>Information is transmitted using secure, encrypted connections.</li>
                                <li>We apply reasonable technical and organisational safeguards appropriate to what we hold.</li>
                                <li>Everyone who handles your information — inside FynnEdge and among our service providers — is bound by confidentiality obligations.</li>
                            </ul>
                            <p class="mt-3">No system connected to the internet can be guaranteed completely secure, and we won't claim otherwise — but protecting your information is something we actively work at, not an afterthought.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">07</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Storage &amp; Retention</span>
                                <span class="mt-1 block text-sm text-ink-muted">We keep information only for as long as it's actually useful or required.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Your information is stored on FynnEdge's own systems and on the infrastructure of service providers we've engaged to support the platform.</li>
                                <li>Once you choose a lender, relevant information is also processed and stored by that lender, under their own systems and policies.</li>
                                <li>We retain information for as long as reasonably necessary to provide our service, resolve queries, and meet applicable legal and regulatory record-keeping requirements.</li>
                                <li>Where FynnEdge acts as a lending service provider supporting a regulated lender's digital lending process, information handling for that relationship follows the applicable regulatory requirements governing it.</li>
                                <li>Once information is no longer needed for these purposes, we delete, anonymise or otherwise securely dispose of it, to the extent the law allows.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">08</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your Choices &amp; Data Rights</span>
                                <span class="mt-1 block text-sm text-ink-muted">It's your information — you should be able to ask questions about it, and expect answers.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Ask us to correct information that's inaccurate or out of date.</li>
                                <li>Ask what information of yours we're processing, and why, where applicable.</li>
                                <li>Raise a privacy concern or ask a question about how your information is used.</li>
                                <li>Withdraw consent for a specific step of processing that is based on your consent, such as a credit bureau check.</li>
                                <li>Ask us to delete information, where we're not required to keep it for legal or regulatory reasons.</li>
                            </ul>
                            <p class="mt-3">Some information may need to be retained even after a request, where we have a legal or regulatory obligation to do so. And withdrawing consent for something central to your loan journey — like a credit check a lender needs to proceed — may mean we, or the lender, can no longer continue that particular part of your application. We'll always be upfront if that's the case.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">09</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Cookies &amp; Website Technologies</span>
                                <span class="mt-1 block text-sm text-ink-muted">A few small technologies help fynnedge.com work properly and improve over time.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Keep you signed in and remember your progress through a loan application.</li>
                                <li>Help protect the site against abuse and unauthorised access.</li>
                                <li>Help us understand how the site performs, so we can fix what's broken.</li>
                                <li>Remember preferences, so you don't have to re-enter them.</li>
                                <li>Help us understand, in aggregate, how people use FynnEdge.</li>
                            </ul>
                            <p class="mt-3">We don't use tracking technologies beyond what's needed to run and improve the site itself.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">10</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Third-Party Websites &amp; Lenders</span>
                                <span class="mt-1 block text-sm text-ink-muted">Once you step onto a lender's own platform, their rules take over.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may link, redirect, or guide you into an application journey hosted by a bank, NBFC or other lending partner. The moment you're interacting directly with that organisation's own website, app or portal, their privacy policy — not ours — governs how they handle your information from that point.</p>
                            <p class="mt-3">We'd encourage you to read a lender's own privacy policy before submitting anything directly to them. FynnEdge doesn't control, and isn't responsible for, the privacy practices of any third-party site we link to or work with.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">11</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">FynnEdge's Role in Your Loan Journey</span>
                                <span class="mt-1 block text-sm text-ink-muted">It matters that you know exactly what FynnEdge is — and isn't — doing with your application.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge Advisory (OPC) Private Limited operates as a loan advisory and distribution platform. We help you explore loan options across our lending partners, and facilitate your communication and application with the lender you choose — including, where applicable, introducing you to a FynnEdge Loan Expert along the way.</p>
                            <p><strong class="text-ink">FynnEdge is not a bank, NBFC, or lender</strong>, and we do not sanction, disburse, or guarantee any loan.</p>
                            <p>Every lending decision — including your eligibility, the sanctioned amount, interest rate, tenure, fees and any other loan term — is made independently by the concerned lender, based on their own policies and underwriting process. An indicative result shown on FynnEdge is not a loan offer, and shouldn't be treated as one.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">12</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Questions, Privacy Requests &amp; Grievances</span>
                                <span class="mt-1 block text-sm text-ink-muted">If something about your information doesn't feel right, tell us — we'd genuinely like to know.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>You can reach us anytime through our <a href="/contact" class="text-accent underline">Contact</a> page for anything related to this policy — correcting your information, asking how it's used, raising a consent-related concern, or requesting that we delete information we're not required to retain.</p>
                            <p>If your concern isn't resolved to your satisfaction, or you'd like to escalate it formally, our <a href="/grievance" class="text-accent underline">Grievance Redressal</a> page sets out the officer and contact details currently handling escalations, along with our resolution timelines.</p>
                            <p>If your concern relates specifically to a lender's own conduct or a regulated decision they've made — such as a credit sanction or its terms — that lender's own grievance redressal process, published on their website or in your loan documentation, is the right channel for that part of your concern. We're happy to help you find it if you're not sure where to start.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">13</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Changes to This Notice</span>
                                <span class="mt-1 block text-sm text-ink-muted">This page evolves as FynnEdge does.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We may update this Privacy Notice from time to time — to reflect changes in our services, the technology we use, new legal or regulatory requirements, or how we process information generally. When we do, the updated version takes effect from the date shown below, and we'll always publish the current version here.</p>
                            <p class="font-mono text-xs text-ink-faint">Effective Date: {{DATE}}<br>Last Updated: {{DATE}}</p>
                        </div>
                    </details>

                </div>
                HTML
        );
    }

    /**
     * A ground-up, 24-section rewrite of Terms of Use — new title, new vocabulary,
     * new ordering, and the same numbered <details>/<summary> accordion pattern as
     * privacyPolicy() above, per an explicit request to make this read as an
     * original FynnEdge document (not a light edit of the previous generic
     * template). Deliberately avoids any claim this project doesn't already
     * establish elsewhere: no RBI-regulated status, no registration/CIN number, no
     * invented court jurisdiction, no data categories FynnEdge doesn't actually
     * collect. Section 19 links out to the Privacy Policy rather than duplicating
     * it; Section 07 links out to Credit Report Terms of Use for the same reason.
     */
    private function terms(): string
    {
        return str_replace(
            '{{DATE}}',
            now()->format('F j, Y'),
            <<<'HTML'
                <div class="not-prose flex flex-col divide-y divide-line border-y border-line">

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">01</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">About FynnEdge</span>
                                <span class="mt-1 block text-sm text-ink-muted">What FynnEdge is, and what it does for you.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge Advisory (OPC) Private Limited ("FynnEdge", "we", "us") operates as a loan advisory, distribution and facilitation platform. We help customers explore loan options, understand loan-related information, run a preliminary eligibility assessment, compare relevant loan options, connect with FynnEdge Loan Experts, and facilitate their interaction and application with banks, NBFCs and other lending institutions ("lenders").</p>
                            <p><strong class="text-ink">FynnEdge is not a bank, NBFC, or lender.</strong> We do not sanction, disburse or guarantee any loan — every lending decision is made independently by the concerned lender.</p>
                            <p>These Terms of Use ("Terms") govern your use of fynnedge.com and the FynnEdge platform. By browsing this website, running a calculator, starting a loan enquiry, or otherwise using our services, you agree to these Terms in full.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">02</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Accepting These Terms</span>
                                <span class="mt-1 block text-sm text-ink-muted">Using FynnEdge means agreeing to a few basic things.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>By using FynnEdge, you confirm that:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>The information you provide us is genuine and accurate.</li>
                                <li>You are authorised to provide the information you submit — including where it concerns a loan you're exploring on someone else's behalf.</li>
                                <li>You are legally capable of entering into a binding agreement under applicable Indian law.</li>
                                <li>You will use the FynnEdge platform lawfully, and in line with these Terms.</li>
                            </ul>
                            <p class="mt-3">These Terms apply from the moment you start using the platform, and continue to apply throughout your loan journey — including any interaction we facilitate with a lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">03</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Starting a Loan Enquiry</span>
                                <span class="mt-1 block text-sm text-ink-muted">What we may ask for, so we can actually help you.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>As you move through your loan journey, FynnEdge may request information relevant to the service you're using, including:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Personal details and contact information</li>
                                <li>KYC information, including your PAN</li>
                                <li>Employment or business information</li>
                                <li>Income information</li>
                                <li>Existing financial obligations</li>
                                <li>Your loan requirement — the type, amount, tenure and purpose</li>
                                <li>Relevant supporting documents</li>
                                <li>Credit information, where applicable to your enquiry</li>
                            </ul>
                            <p class="mt-3">Exactly what we ask for depends on the loan product and where you are in your journey — we follow a need-based approach rather than collecting everything upfront.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">04</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Accuracy of Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">Your information must be genuine, accurate, and yours to give.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>You are responsible for submitting accurate, complete and current information at every step of your loan journey. In particular, you agree not to:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Provide fraudulent or false information</li>
                                <li>Submit a false or misleading loan application</li>
                                <li>Submit another person's information without their authorisation</li>
                                <li>Manipulate, alter or falsify any document</li>
                                <li>Misrepresent your income, employment or business details</li>
                            </ul>
                            <p class="mt-3">Inaccurate information can affect your lender's decision, and may lead FynnEdge to restrict or suspend your access — see Section 20.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">05</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">How FynnEdge Assists You</span>
                                <span class="mt-1 block text-sm text-ink-muted">From understanding your options to helping you apply.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may help you understand the loan options available to you, including information on loan amount, interest rate, tenure, EMI, eligibility, processing requirements, and applicable fees or charges.</p>
                            <p>Where we show this kind of information before you've applied, it is indicative — based on the details you've shared and each lender's published criteria — until your chosen lender confirms it as part of their own verification and underwriting.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">06</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Eligibility &amp; Loan Approval</span>
                                <span class="mt-1 block text-sm text-ink-muted">Eligibility depends on the lender's own criteria — not just ours.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Loan eligibility depends on lender-specific criteria, and may take into account:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Your income</li>
                                <li>Your employment or business profile</li>
                                <li>Your credit history</li>
                                <li>Your existing financial obligations</li>
                                <li>Your KYC details</li>
                                <li>Your loan requirement</li>
                                <li>The concerned lender's own policies</li>
                                <li>Internal credit assessment carried out by the lender</li>
                            </ul>
                            <p class="mt-3">An eligibility result shown on FynnEdge is not a guarantee of approval — see Section 17.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">07</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Credit Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">A credit check, where relevant, happens only with your consent.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Where applicable to your loan enquiry, FynnEdge may facilitate obtaining your credit information through authorised credit information channels, after obtaining the consent required for that check.</p>
                            <p>Not every visit to FynnEdge results in a credit bureau enquiry — where an actual check is going to be made, we present a separate, specific consent step at that point in your journey.</p>
                            <p>A credit score or credit report is one input into an eligibility picture, and does not by itself guarantee approval. For more detail on how a credit check fits into your application, see our <a href="/credit-report-terms" class="text-accent underline">Credit Report Terms of Use</a>.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">08</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your FynnEdge Loan Expert</span>
                                <span class="mt-1 block text-sm text-ink-muted">A dedicated person to guide you through your application.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>A FynnEdge Loan Expert may contact you to understand your requirement in more detail and assist you through the application process — from clarifying documentation to helping you compare relevant lenders.</p>
                            <p class="mt-3">Your FynnEdge Loan Expert represents FynnEdge, helping you navigate your options — they do not represent, and cannot bind, any specific lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">09</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Communication From FynnEdge</span>
                                <span class="mt-1 block text-sm text-ink-muted">How we may reach you, and what about.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>By sharing your contact details with us, you agree that FynnEdge and, where relevant, your FynnEdge Loan Expert may reach you through telephone, SMS, email, WhatsApp, or other appropriate digital channels.</p>
                            <p class="mt-3">This communication may relate to your enquiry, your application, documents we need from you, verification steps, Loan Expert assistance, and updates on the service generally.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">10</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Relationship With Banks &amp; NBFCs</span>
                                <span class="mt-1 block text-sm text-ink-muted">FynnEdge facilitates the introduction — the lender owns the relationship.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge facilitates your interaction and application with banks, NBFCs and other lending institutions — we help identify suitable options and carry your application forward to the lender you choose.</p>
                            <p class="mt-3">Once you proceed with a lender, the actual loan relationship is governed by that lender's own final documentation and terms — not by FynnEdge.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">11</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Interest Rates, Fees &amp; Loan Calculations</span>
                                <span class="mt-1 block text-sm text-ink-muted">Figures you see here are indicative until your lender confirms them.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Interest rates, EMI, tenure, processing fees and other figures displayed on FynnEdge — including on our calculators — may be indicative, based on information provided by our lending partners and the details you've shared.</p>
                            <p class="mt-3">Final terms applicable to your loan are determined by the concerned lender, at the time of sanction.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">12</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Using Our Website Responsibly</span>
                                <span class="mt-1 block text-sm text-ink-muted">A few things we ask you not to do on FynnEdge.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>When using fynnedge.com, you agree not to:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Access any part of the platform without authorisation</li>
                                <li>Engage in or attempt any fraud</li>
                                <li>Impersonate another person or misrepresent your identity</li>
                                <li>Misuse the platform in any way not intended by FynnEdge</li>
                                <li>Introduce malicious code, viruses or similar harmful content</li>
                                <li>Attempt to bypass our security measures</li>
                                <li>Use bots, scrapers or other automated means to abuse the platform</li>
                                <li>Use the platform for any unlawful activity</li>
                                <li>Copy, reproduce or exploit our website or its content without authorisation</li>
                            </ul>
                            <p class="mt-3">A violation of this section may result in restriction or suspension of your access — see Section 20.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">13</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Calculators, Comparisons &amp; Informational Content</span>
                                <span class="mt-1 block text-sm text-ink-muted">Tools to help you plan — not a sanction letter.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may provide EMI calculators, rate comparisons, tenure comparisons, loan-cost illustrations, eligibility indicators and other educational content, to help you plan and compare your options.</p>
                            <p class="mt-3">These tools are informational. They do not constitute a loan sanction, a lender's offer, or any guarantee of the terms you'll actually be offered.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">14</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Third-Party Platforms</span>
                                <span class="mt-1 block text-sm text-ink-muted">Stepping onto a partner's platform brings their rules into play.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>As part of your loan journey, you may interact with a lender's, credit bureau's, verification provider's, or other authorised third party's own platform.</p>
                            <p class="mt-3">That platform's own terms of use and privacy policy may apply to your interaction with it — FynnEdge does not control, and is not responsible for, the practices of any third-party platform we link to or work with.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">15</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">FynnEdge Website &amp; Intellectual Property</span>
                                <span class="mt-1 block text-sm text-ink-muted">The FynnEdge name, design and content are ours to protect.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>The FynnEdge name and logo, and the content, graphics, designs, software, tools and interface that make up fynnedge.com, belong to FynnEdge or our licensors, and are protected under applicable intellectual property law.</p>
                            <p class="mt-3">You may use the platform for your own personal loan enquiry — you may not copy, reproduce, modify or otherwise exploit any part of it for another purpose without our prior written permission.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">16</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Website Availability</span>
                                <span class="mt-1 block text-sm text-ink-muted">We aim for uptime, but nothing online is guaranteed 24/7.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may be temporarily unavailable because of scheduled maintenance, technical issues, network failures, third-party service issues, security events, or other circumstances outside our reasonable control.</p>
                            <p class="mt-3">We work to keep interruptions brief, but we don't guarantee that the platform will be available uninterrupted at all times.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">17</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">No Assurance of Loan Sanction</span>
                                <span class="mt-1 block text-sm text-ink-muted">The clearest thing we can tell you: nothing here is a loan guarantee.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p class="font-semibold text-ink">FynnEdge does not guarantee:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Approval of your loan application</li>
                                <li>The loan amount you'll be offered</li>
                                <li>The interest rate you'll be offered</li>
                                <li>The tenure you'll be offered</li>
                                <li>Disbursement of any loan</li>
                                <li>Any specific processing timeline</li>
                                <li>Any specific lender's offer</li>
                            </ul>
                            <p class="mt-3">Every one of these depends entirely on the concerned lender's own assessment, at the time they review your application.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">18</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your Responsibilities</span>
                                <span class="mt-1 block text-sm text-ink-muted">A few things that stay on you throughout your loan journey.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Throughout your loan journey, you're responsible for:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Providing genuine information</li>
                                <li>Keeping that information accurate and up to date</li>
                                <li>Arranging the documents your application requires</li>
                                <li>Reviewing your lender's documents carefully before proceeding</li>
                                <li>Understanding the repayment obligations you're taking on</li>
                                <li>Protecting your account and access information</li>
                                <li>Making an informed decision before you borrow</li>
                            </ul>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">19</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Privacy &amp; Use of Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">How we handle your information is covered in detail elsewhere.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Using FynnEdge also means agreeing to how we collect, use, share and protect your information — described fully in our <a href="/privacy-policy" class="text-accent underline">Privacy Policy</a>, which forms part of these Terms.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">20</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Restriction or Suspension</span>
                                <span class="mt-1 block text-sm text-ink-muted">Access can be limited when something's genuinely wrong.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may restrict or suspend your access to the platform where we reasonably believe there is fraud, misuse, a security concern, a violation of these Terms, unauthorised activity, a legal or regulatory requirement to do so, or another genuine operational reason.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">21</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Responsibility for Third-Party Decisions</span>
                                <span class="mt-1 block text-sm text-ink-muted">The lender's call stays the lender's call.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Where FynnEdge facilitates your application with a lender, that lender independently decides matters within its own remit — including credit assessment, approval or rejection, loan amount, interest rate, fees, tenure, documentation requirements, and disbursement.</p>
                            <p class="mt-3">FynnEdge is not responsible for a lender's decisions, timelines, or the final terms of any loan offered.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">22</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Updates to These Terms</span>
                                <span class="mt-1 block text-sm text-ink-muted">These Terms evolve as FynnEdge does.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We may update these Terms from time to time, to reflect changes in our services, the technology we use, our business operations, or applicable legal or regulatory requirements. When we do, the updated version takes effect from the date shown below, and we'll always publish the current version here.</p>
                            <p class="font-mono text-xs text-ink-faint">Effective Date: {{DATE}}<br>Last Updated: {{DATE}}</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">23</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Applicable Law</span>
                                <span class="mt-1 block text-sm text-ink-muted">Indian law governs these Terms.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>These Terms are governed by the applicable laws of India, and any disputes arising from them are subject to the exclusive jurisdiction of the courts at the location of FynnEdge's registered office.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">24</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Contact FynnEdge</span>
                                <span class="mt-1 block text-sm text-ink-muted">Questions about these Terms? We're easy to reach.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>If you have a question about these Terms, reach us through our <a href="/contact" class="text-accent underline">Contact</a> page. If your concern is a formal complaint that hasn't been resolved to your satisfaction, our <a href="/grievance" class="text-accent underline">Grievance Redressal</a> page sets out how to escalate it.</p>
                        </div>
                    </details>

                </div>
                HTML
        );
    }

    /**
     * A numbered, accordion-driven rewrite of the Disclaimer, matching the structure already
     * used for Privacy Policy and Terms of Use — 12 named sections spelling out FynnEdge's
     * role as a loan advisory/distribution/facilitation platform (not a lender), rather than
     * the previous 5-section generic-template version. Section 12 links to /contact and
     * /grievance instead of hardcoding phone/email/address, so it always reflects whatever is
     * currently configured in Settings rather than going stale.
     */
    private function disclaimer(): string
    {
        return str_replace(
            '{{DATE}}',
            now()->format('F j, Y'),
            <<<'HTML'
                <div class="not-prose flex flex-col divide-y divide-line border-y border-line">

                    <details class="group py-6" open>
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">01</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">FynnEdge's Role</span>
                                <span class="mt-1 block text-sm text-ink-muted">What FynnEdge is, and isn't, in every loan journey.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge Advisory (OPC) Pvt Ltd ("FynnEdge", "we", "us") operates as a loan advisory, distribution and facilitation platform. We help you explore loan options, understand loan products, check your preliminary eligibility, compare loan-related information, and connect with a FynnEdge Loan Expert where relevant.</p>
                            <p><strong class="text-ink">FynnEdge is not a bank, NBFC or lending institution</strong>, and we do not independently sanction or disburse any loan.</p>
                            <p>Where you choose to proceed, we facilitate your interaction and application with the banks, NBFCs and other lending institutions ("lenders") available on our platform. The final lending decision always rests with the concerned lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">02</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Loan Approval &amp; Eligibility</span>
                                <span class="mt-1 block text-sm text-ink-muted">An eligible result is a starting point, not a promise.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Eligibility shown on FynnEdge — including any "eligible", "pre-qualified" or "conditional" result — is preliminary and indicative. It is:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Not a loan offer.</li>
                                <li>Not a sanction letter.</li>
                                <li>Not a guarantee of approval.</li>
                            </ul>
                            <p class="mt-3">Every application is subject to the concerned lender's own assessment, verification, documentation and underwriting. The lender may approve, reject, or offer terms different from what was indicated on FynnEdge — the lender's final assessment and documentation are what actually determine your loan terms.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">03</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Interest Rates, Fees &amp; Charges</span>
                                <span class="mt-1 block text-sm text-ink-muted">What you see here is indicative — the lender's paperwork is final.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Interest rates, processing fees, EMI, tenure and other charges displayed on FynnEdge are indicative and may change.</p>
                            <p>The final interest rate, loan amount, tenure, EMI, fees, charges and repayment terms applicable to you are determined solely by the concerned lender. Please review the lender's final sanction letter and loan documentation carefully before accepting any loan.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">04</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Credit Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">A credit check happens only with your consent, and is only one part of the picture.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Where a loan product requires it, your credit information may be used as part of the facilitation process. This is obtained through authorised channels, only after you provide the required consent — see our <a href="/credit-report-terms" class="text-accent underline">Credit Report Terms of Use</a> for details.</p>
                            <p>Your credit score or credit report is only one factor that may be considered when evaluating your application. It does not, by itself, guarantee approval — the concerned lender makes its own independent credit decision.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">05</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Calculators &amp; Estimates</span>
                                <span class="mt-1 block text-sm text-ink-muted">Useful for planning — not a substitute for your sanction letter.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may provide EMI calculators, eligibility indicators, loan comparisons, interest-rate and tenure comparisons, loan-cost estimates and other informational tools. These are provided for guidance and estimation only.</p>
                            <p>Actual figures may differ from what a calculator shows, because of the lender's own calculation method, the final sanctioned amount, interest rate, fees, charges, loan structure, or changes to applicable terms. These tools are not, and must not be treated as, a loan sanction or a binding offer.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">06</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">General Information, Not Professional Advice</span>
                                <span class="mt-1 block text-sm text-ink-muted">Helpful context — not financial, legal or tax advice.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Information available on FynnEdge — including loan product descriptions, eligibility guidance, comparisons and articles — is provided for general informational purposes. It should not be treated as financial advice, investment advice, legal advice or tax advice.</p>
                            <p>Please evaluate your own financial circumstances, repayment ability and the terms of any loan carefully — on your own or with a qualified advisor — before accepting it.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">07</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Third-Party Lenders &amp; Services</span>
                                <span class="mt-1 block text-sm text-ink-muted">The lenders and partners we work with operate independently of FynnEdge.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge may facilitate your interaction and application with banks, NBFCs, other lending institutions, credit information providers, and other authorised service providers. These parties operate independently of FynnEdge, and their own eligibility criteria, policies, terms, privacy practices and processing procedures may apply to your dealings with them.</p>
                            <p>FynnEdge does not control, and is not responsible for, independent lending decisions made by these parties. If your application results in a loan, the loan agreement is between you and the concerned lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">08</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Information Accuracy &amp; Changes</span>
                                <span class="mt-1 block text-sm text-ink-muted">We keep this page current — always confirm against your lender's paperwork.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We make reasonable efforts to keep the information on FynnEdge current and useful. However, interest rates, fees, eligibility criteria, product availability, lender policies and product information can all change from time to time, and this page may not always reflect the latest position instantly.</p>
                            <p>Please verify anything you rely on against the final communication and documentation issued to you by the concerned lender.</p>
                            <p class="font-mono text-xs text-ink-faint">Last Updated: {{DATE}}</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">09</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your Responsibility</span>
                                <span class="mt-1 block text-sm text-ink-muted">A good outcome starts with accurate information from your side too.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>When you use FynnEdge, we'd ask you to:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>Provide accurate information and genuine documents.</li>
                                <li>Review your application details before submitting.</li>
                                <li>Read the lender's final offer carefully.</li>
                                <li>Understand your repayment obligations, and check applicable fees and charges.</li>
                                <li>Assess your own ability to repay before accepting a loan.</li>
                            </ul>
                            <p class="mt-3">Taking a few minutes on these steps helps you make a well-informed borrowing decision, and helps your application move smoothly.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">10</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Website &amp; Technical Availability</span>
                                <span class="mt-1 block text-sm text-ink-muted">We aim for a reliable platform — occasional disruptions can still happen.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We aim to keep FynnEdge available and reliable. That said, temporary downtime, delays, errors, interruptions or unavailability may occur from time to time, due to maintenance, technical issues, network problems, third-party service interruptions, security incidents, system upgrades, or other circumstances outside our reasonable control.</p>
                            <p>We'll work to resolve any disruption as quickly as we reasonably can.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">11</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Legal Rights &amp; Responsibilities</span>
                                <span class="mt-1 block text-sm text-ink-muted">Nothing here overrides the protections the law already gives you.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Nothing in this Disclaimer is intended to exclude, restrict or limit any right, remedy, responsibility or liability that cannot legally be excluded or limited under applicable Indian law. Where any part of this Disclaimer conflicts with such a right or protection, the law takes precedence.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">12</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Contact FynnEdge</span>
                                <span class="mt-1 block text-sm text-ink-muted">Questions about this Disclaimer? We're glad to help.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>If anything in this Disclaimer is unclear, or you'd like to understand how it applies to your specific loan enquiry, reach us through our <a href="/contact" class="text-accent underline">Contact</a> page — it carries our current phone number, email address and office address.</p>
                            <p>For a formal complaint or escalation, our <a href="/grievance" class="text-accent underline">Grievance Redressal</a> page sets out the officer and contact details currently handling escalations, along with our resolution timelines.</p>
                        </div>
                    </details>

                </div>
                HTML
        );
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

    /**
     * A full, ground-up rewrite of the "Credit Report Terms of Use" page — previously a
     * placeholder stub. Restructured into 23 numbered sections covering the entire FynnEdge
     * loan journey (not just the credit-check step), per an explicit request to make this the
     * comprehensive, customer-facing Terms document reached from the journey consent checkbox's
     * "Credit Report Terms of Use" link — while the separate, shorter `terms()` page (route
     * `terms`) is deliberately left untouched. Reuses the same numbered <details>/<summary>
     * accordion pattern as privacyPolicy() above, for a consistent look across FynnEdge's legal
     * pages.
     */
    private function creditReportTerms(): string
    {
        return str_replace(
            '{{DATE}}',
            now()->format('F j, Y'),
            <<<'HTML'
                <div class="not-prose flex flex-col divide-y divide-line border-y border-line">

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">01</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Welcome to FynnEdge</span>
                                <span class="mt-1 block text-sm text-ink-muted">These Terms explain what you're agreeing to the moment you start exploring a loan with us.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge Advisory (OPC) Private Limited ("FynnEdge", "we", "us", "our") operates fynnedge.com and the FynnEdge platform — a place to explore loan options, understand what you might qualify for, and get help moving your application forward with a bank, NBFC or other lending institution, including where that involves a check of your credit information.</p>
                            <p>These Terms of Use ("Terms") govern your use of FynnEdge, from the moment you land on this website through every step of a loan enquiry or application you begin with us. By browsing FynnEdge, starting a loan enquiry, or using our calculators and eligibility tools, you agree to these Terms. If something here doesn't sit right with you, we'd rather you not proceed than agree to something you're unsure about — you're always welcome to reach out to us first through our <a href="/contact" class="text-accent underline">Contact</a> page.</p>
                            <p>Read alongside these Terms: our <a href="/privacy-policy" class="text-accent underline">Privacy Policy</a>, which explains how we handle your information in full.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">02</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Using Our Platform</span>
                                <span class="mt-1 block text-sm text-ink-muted">FynnEdge is built for genuine loan seekers in India — here's what that means in practice.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge is intended for individuals who are at least 18 years old, capable of entering into a legally binding loan agreement under Indian law, and who are genuinely exploring a loan for themselves or a business they represent.</p>
                            <p>You agree to use FynnEdge only for its intended purpose — exploring loan options, checking indicative eligibility, and applying for a loan you genuinely intend to take up if approved — and to do so lawfully, without harming FynnEdge, our lending partners, or other users. Section 11 sets out conduct we don't allow.</p>
                            <p>If you're applying on behalf of a business — for a business loan or a loan against property held by a company or firm, for instance — you confirm you're authorised to share that business's information and act on its behalf.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">03</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Beginning Your Loan Journey</span>
                                <span class="mt-1 block text-sm text-ink-muted">From your first enquiry to choosing a lender, here's the shape of what happens.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ol class="flex flex-col gap-2 font-mono text-xs text-ink-faint">
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">You</span> tell us what you're looking for — loan type, amount and purpose</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">FynnEdge</span> runs a preliminary check against participating lenders' published criteria</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">We show you</span> the loan options and lenders you're likely eligible with</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">A FynnEdge Loan Expert</span> may connect with you to guide your application</li>
                                <li class="pl-1 text-ink-faint">↓</li>
                                <li class="flex items-center gap-2"><span class="font-semibold text-ink">You choose a lender</span>, and we help you submit your application to them</li>
                            </ol>
                            <p>Nothing about starting an enquiry commits you to anything — you can explore your options, compare what's on offer, and decide not to proceed at any point before you choose to submit an application to a lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">04</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Information You Provide</span>
                                <span class="mt-1 block text-sm text-ink-muted">What we can do for you depends entirely on what you tell us — so accuracy matters.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Everything FynnEdge does — from an indicative eligibility check to helping you complete an application — is based on the information you provide. You agree that the personal, contact, KYC, employment, business, income and loan-requirement information you share with us is accurate, complete, current, and genuinely yours to share.</p>
                            <p>If any of your information changes, or you realise something you've shared was incorrect, please let your FynnEdge Loan Expert know, or update it as early as possible. Information that's inaccurate, incomplete or misleading can lead to a lender declining your application, delays in processing, or — where it appears deliberate — the outcomes described in Section 11.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">05</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Loan Options &amp; Eligibility</span>
                                <span class="mt-1 block text-sm text-ink-muted">What we show you is a well-informed estimate — not a lender's final word.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>When FynnEdge shows you loan options, an eligibility result, an interest-rate range, or an indicative EMI, these are generated from the information you've provided and each lender's own published criteria, as configured on our platform at that time.</p>
                            <p><strong class="text-ink">These results are indicative, not final.</strong> They're a genuine, good-faith estimate of what you're likely to qualify for — not a loan offer, sanction letter, or commitment from any lender. A lender may still request more documentation, apply additional checks, offer different terms, or decline an application that FynnEdge indicated as eligible, based on their own verification and underwriting.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">06</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Credit Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">Understanding your credit history — CIBIL and other bureaus — can help sharpen your options.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p><strong class="text-ink">What we mean</strong> — your credit score and credit report, as maintained by Credit Information Companies authorised in India (commonly known by names like CIBIL, Experian, Equifax and CRIF).</p>
                            <p><strong class="text-ink">Where applicable</strong> — as part of assessing your loan requirement, FynnEdge or a lender we work with may obtain your credit information through an authorised credit information channel, to help build a fuller picture alongside your income and employment details.</p>
                            <p><strong class="text-ink">Your consent</strong> — a credit information check is not run simply because you're browsing FynnEdge or starting an enquiry. Where a credit check forms part of your journey, we ask for your specific, informed consent at that stage of your application, before any check is made.</p>
                            <p><strong class="text-ink">What it doesn't mean</strong> — your credit score is one input into an eligibility picture, not a guarantee either way. A strong score doesn't assure approval, and the concerned lender makes the final credit decision, on their own policy.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">07</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Assistance From FynnEdge Loan Experts</span>
                                <span class="mt-1 block text-sm text-ink-muted">A real person, not just a form — here to help you move through your application.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Depending on your loan requirement, a FynnEdge Loan Expert may reach out to understand your needs better, explain the options available to you, help you complete your application accurately, and keep you informed as it progresses with your chosen lender.</p>
                            <p>A Loan Expert's role is to guide and assist you — they don't make lending decisions, and nothing they tell you about your likely eligibility or loan terms overrides the lender's own assessment. Where a Loan Expert's guidance ever seems to conflict with what a lender communicates to you directly, the lender's communication governs.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">08</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Communication &amp; Updates</span>
                                <span class="mt-1 block text-sm text-ink-muted">Expect to hear from us — but only about the enquiry or application you've started.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>By sharing your contact details and starting a loan enquiry or application, you agree that FynnEdge, our Loan Experts, and — once you've chosen one — your selected lender, may contact you by phone call, SMS, email, WhatsApp or other permitted channels, for purposes connected to your enquiry or application. This includes clarifying your requirement, requesting documents, sharing eligibility results, following up on an incomplete application, and keeping you updated on its status.</p>
                            <p>If you'd prefer not to be contacted about an enquiry you've started, let us know through our <a href="/contact" class="text-accent underline">Contact</a> page and we'll do our best to accommodate that — though some communication, like a document request needed to keep your application moving, may still be necessary for us to help you.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">09</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Your Relationship With the Lender</span>
                                <span class="mt-1 block text-sm text-ink-muted">The loan itself — and everything that comes with it — is an agreement between you and the lender.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge helps you reach the right lender and supports you through the application process, but <strong class="text-ink">the loan agreement, if one is offered and you accept it, is entered into directly between you and the concerned bank, NBFC or lending institution.</strong> FynnEdge is not a party to that agreement.</p>
                            <p>Once your application is with a lender, their own terms and conditions, sanction letter, loan agreement and policies govern that relationship — including how your loan is disbursed and serviced, and how any default or dispute is handled. Read everything a lender sends you carefully before signing.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">10</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Rates, Fees &amp; Loan Costs</span>
                                <span class="mt-1 block text-sm text-ink-muted">What you'll actually pay is set by the lender you choose, not by FynnEdge.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Interest rates, processing fees, EMIs and other charges shown on FynnEdge — including in our calculators and comparisons — are indicative figures based on what our lending partners have published to us. The rate or fee you're finally offered is decided by the lender at the time of sanction, based on their assessment of your application, and can differ from what was shown to you earlier.</p>
                            <p><strong class="text-ink">Using FynnEdge to check your eligibility and explore loan options is free.</strong> We don't charge you to run an eligibility check, compare lenders, or submit an application through our platform. Any fee a lender charges — such as a processing fee — is disclosed by that lender before you proceed, and is payable by you to the lender, not to FynnEdge.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">11</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Using FynnEdge Responsibly</span>
                                <span class="mt-1 block text-sm text-ink-muted">A few things we ask you not to do, so FynnEdge stays trustworthy for everyone.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Submitting false, misleading or fraudulent information — including a fabricated income, employment or identity detail — to influence an eligibility result or application.</li>
                                <li>Impersonating another person, or submitting an application, documents or a credit check consent on someone else's behalf without their knowledge and authorisation.</li>
                                <li>Attempting to gain unauthorised access to FynnEdge's systems, another user's account or application, or information you're not entitled to see.</li>
                                <li>Using automated tools, scrapers or bots to extract data from FynnEdge, or to submit enquiries or applications at scale.</li>
                                <li>Interfering with, disrupting, or attempting to circumvent the security or normal functioning of our platform.</li>
                                <li>Misusing the assistance of a FynnEdge Loan Expert — for example, pressuring them to misrepresent your profile to a lender.</li>
                                <li>Using FynnEdge for any purpose that is unlawful, or that could expose FynnEdge, our lending partners or other users to harm or liability.</li>
                            </ul>
                            <p class="mt-3">Where we reasonably suspect any of the above, we may take the steps described in Section 19 — including restricting or suspending your access — and, where appropriate, report the matter to the concerned lender or the relevant authorities.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">12</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Calculators, Comparisons &amp; Information</span>
                                <span class="mt-1 block text-sm text-ink-muted">Useful for planning, not a substitute for a lender's actual sanction letter.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge's EMI calculators, interest-rate and tenure comparisons, loan-cost breakdowns, eligibility indicators and educational content (like our Resources articles and FAQs) are provided to help you plan and compare — built using standard financial formulas and the rate and fee information available to us at the time.</p>
                            <p>These tools are for guidance only. The figures they produce are estimates, and may not exactly match the final terms a lender offers you — actual EMI, tenure, rate and total cost depend on the lender's sanction, and can vary based on processing timelines, applicable taxes, and any conditions the lender attaches.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">13</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">External Platforms &amp; Services</span>
                                <span class="mt-1 block text-sm text-ink-muted">Once you step onto a lender's own site or app, their rules take over.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>Completing your loan journey may involve FynnEdge linking, redirecting, or guiding you into an application experience hosted by a bank, NBFC or other lending or service partner — for instance, to complete KYC, e-sign documents, or make a payment. The moment you're interacting directly with that organisation's own platform, their terms of use and privacy policy govern that interaction, not ours.</p>
                            <p>We'd encourage you to review a partner's own terms before submitting anything directly through their platform. FynnEdge doesn't control, and isn't responsible for, the content, security or practices of any third-party platform we link to or work with.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">14</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">FynnEdge Content &amp; Intellectual Property</span>
                                <span class="mt-1 block text-sm text-ink-muted">The FynnEdge name, look and content are ours — please don't borrow them without asking.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>The FynnEdge name, logo, website design, layout, graphics, text, calculators, software and all other content on fynnedge.com — excluding content or trademarks belonging to our lending partners — belong to FynnEdge Advisory (OPC) Private Limited, or are used by us under licence, and are protected by applicable intellectual property laws.</p>
                            <p>You're welcome to use FynnEdge as intended — browsing, checking eligibility, applying for a loan. You may not copy, reproduce, modify, distribute, or create derivative works from our content, branding or platform for any other purpose, including commercial use, without our prior written permission.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">15</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Website Availability</span>
                                <span class="mt-1 block text-sm text-ink-muted">We aim to be there when you need us — but no website runs perfectly, all the time.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We work to keep FynnEdge available and running smoothly, but we don't guarantee the platform will be available uninterrupted, or free from errors, at all times. Access may occasionally be affected by scheduled maintenance, technical issues, network problems, or the availability of third-party services — such as a lender's system, a credit bureau, or a payment or verification provider — that FynnEdge depends on to serve you.</p>
                            <p>Where reasonably possible, we'll try to keep disruption to a minimum and let you know about planned maintenance in advance. We're not responsible for delays or issues caused by circumstances genuinely outside our reasonable control.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">16</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">No Promise of Loan Approval</span>
                                <span class="mt-1 block text-sm text-ink-muted">The one thing worth saying plainly, on its own: nothing on FynnEdge is a guarantee.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>However positive an eligibility result looks, however experienced our Loan Experts are, and however smoothly your application moves through our platform — <strong class="text-ink">using FynnEdge does not guarantee that any lender will sanction your loan, or that it will be sanctioned for the amount, interest rate or tenure indicated.</strong> Approval or rejection, and every term of the loan if one is offered, is decided solely by the concerned lending institution.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">17</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Customer Responsibilities</span>
                                <span class="mt-1 block text-sm text-ink-muted">A loan is a real financial commitment — a few things worth doing on your end.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <ul class="list-disc space-y-2 pl-5">
                                <li>Share information that's accurate, complete and genuinely yours, and keep it updated if it changes (Section 04).</li>
                                <li>Read every document a lender sends you — sanction letter, loan agreement, fee schedule — carefully, before signing anything.</li>
                                <li>Understand the EMI, tenure, interest rate and any charges you're agreeing to, and make sure the loan genuinely fits your financial situation.</li>
                                <li>Ask questions — of your FynnEdge Loan Expert or the lender directly — if any term isn't clear to you.</li>
                                <li>Keep your login details, OTPs and application-related communications confidential, and let us know promptly if you suspect unauthorised access.</li>
                            </ul>
                            <p class="mt-3">If you're ever unsure whether a loan is the right decision for you, we'd encourage you to take independent financial advice before proceeding — FynnEdge's role is to help you find and access options, not to tell you what to decide.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">18</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Privacy &amp; Information Handling</span>
                                <span class="mt-1 block text-sm text-ink-muted">How we handle everything you share with us has its own dedicated page.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>The personal, financial and application information you share with FynnEdge — including any credit information covered by these Terms — is handled in accordance with our <a href="/privacy-policy" class="text-accent underline">Privacy Policy</a>, which explains in detail what we collect, why, who we may share it with, and the choices and rights available to you. We haven't repeated all of that here; please read it alongside these Terms.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">19</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Restriction or Suspension of Access</span>
                                <span class="mt-1 block text-sm text-ink-muted">In a few specific situations, we may need to pause or limit your access to FynnEdge.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We may restrict, suspend or terminate your access to FynnEdge — in whole or in part — where we reasonably believe:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5">
                                <li>You've provided false, misleading or fraudulent information, or otherwise breached Section 11.</li>
                                <li>Your use of the platform poses a security risk to FynnEdge, our lending partners, or other users.</li>
                                <li>We're required to do so to comply with a legal or regulatory requirement, or a request from a competent authority.</li>
                                <li>Continuing to provide access could expose FynnEdge or others to harm, fraud or liability.</li>
                            </ul>
                            <p class="mt-3">Where reasonably possible, we'll try to let you know why access was restricted or suspended, and what — if anything — you can do about it.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">20</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Responsibility &amp; Limitations</span>
                                <span class="mt-1 block text-sm text-ink-muted">What we're accountable for — and what genuinely sits with the lender or a third party.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>FynnEdge is responsible for the accuracy of the platform, tools and guidance we provide, and for facilitating your loan journey in good faith, as described throughout these Terms. We're not responsible for any independent decision, act or omission of a lender or other third party — including a lender's decision to approve, decline, delay, or offer different terms than indicated; a delay caused by a lender's, bureau's or verification partner's own systems; or a dispute that arises directly between you and a lender under your loan agreement.</p>
                            <p>To the extent permitted by applicable law, FynnEdge's liability in connection with your use of this platform is limited to facilitating your loan journey as intended — it does not extend to the outcome, terms, or performance of any loan ultimately offered by a third-party lender.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">21</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Updates to These Terms</span>
                                <span class="mt-1 block text-sm text-ink-muted">As FynnEdge grows, these Terms may need to grow with it.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>We may update these Terms from time to time — to reflect changes in our services, new loan products or lending partners, legal or regulatory requirements, or how FynnEdge generally operates. When we do, the updated version takes effect from the date it's published here, and continuing to use FynnEdge after that means you accept the updated Terms. We'll always keep the current version available on this page.</p>
                            <p class="font-mono text-xs text-ink-faint">Effective Date: {{DATE}}<br>Last Updated: {{DATE}}</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">22</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Applicable Law</span>
                                <span class="mt-1 block text-sm text-ink-muted">These Terms are written for — and governed by — Indian law.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>These Terms are governed by the laws of India. Any dispute arising out of or in connection with your use of FynnEdge is subject to the exclusive jurisdiction of the courts at the location of FynnEdge Advisory (OPC) Private Limited's registered office.</p>
                        </div>
                    </details>

                    <details class="group py-6">
                        <summary class="flex cursor-pointer list-none items-start gap-4 text-left [&::-webkit-details-marker]:hidden">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">23</span>
                            <span class="flex-1">
                                <span class="block font-display text-lg font-semibold text-ink">Contact &amp; Support</span>
                                <span class="mt-1 block text-sm text-ink-muted">Questions about these Terms, or anything else? We're a message away.</span>
                            </span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="mt-2 h-4 w-4 shrink-0 text-ink-faint transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <div class="mt-4 space-y-3 pl-12 text-sm leading-relaxed text-ink-muted">
                            <p>If anything in these Terms is unclear, or you'd like to talk through your loan journey before proceeding, reach us through our <a href="/contact" class="text-accent underline">Contact</a> page — we aim to respond promptly.</p>
                            <p>If you have a complaint that hasn't been resolved to your satisfaction, our <a href="/grievance" class="text-accent underline">Grievance Redressal</a> page sets out how to escalate it, including current officer and contact details and our resolution timelines.</p>
                        </div>
                    </details>

                </div>
                HTML
        );
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
            HTML;
    }
}
