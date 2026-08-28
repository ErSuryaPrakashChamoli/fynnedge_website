<footer class="border-t border-line bg-surface">
    <div class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <div class="grid grid-cols-2 gap-10 sm:grid-cols-4">
            <div class="col-span-2 sm:col-span-1">
                <p class="font-display text-lg font-semibold text-ink">FynnEdge</p>
                <p class="mt-2 font-display text-sm italic text-accent">Simplifying Loan, Amplifying Trust.</p>
                <p class="mt-4 text-xs text-ink-faint">FynnEdge Advisory (OPC) Pvt Ltd</p>
            </div>

            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Loans</p>
                <ul class="mt-3 flex flex-col gap-2">
                    <li><x-site.nav-link route="loans.personal-loan" label="Personal Loan" /></li>
                    <li><x-site.nav-link route="loans.home-loan" label="Home Loan" /></li>
                    <li><x-site.nav-link route="loans.business-loan" label="Business Loan" /></li>
                    <li><x-site.nav-link route="loans.loan-against-property" label="Loan Against Property" /></li>
                </ul>
            </div>

            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Company</p>
                <ul class="mt-3 flex flex-col gap-2">
                    <li><x-site.nav-link route="about" label="About" /></li>
                    <li><x-site.nav-link route="careers" label="Careers" /></li>
                    <li><x-site.nav-link route="contact" label="Contact" /></li>
                    <li><x-site.nav-link route="grievance" label="Grievance" /></li>
                </ul>
            </div>

            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Legal</p>
                <ul class="mt-3 flex flex-col gap-2">
                    <li><x-site.nav-link route="privacy-policy" label="Privacy Policy" /></li>
                    <li><x-site.nav-link route="terms" label="Terms" /></li>
                    <li><x-site.nav-link route="disclaimer" label="Disclaimer" /></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-line pt-6 text-xs text-ink-faint sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} FynnEdge Advisory (OPC) Pvt Ltd. All rights reserved.</p>
            <p class="max-w-2xl">Loan approval is subject to lender policies, documentation and underwriting. Eligibility results shown on this site are indicative, not a guarantee of approval.</p>
        </div>
    </div>
</footer>
