/**
 * Livewire bundles and starts its own Alpine instance — importing and
 * starting a second one here (as this file used to) makes two Alpine
 * instances run at once. That breaks Livewire's own wire:model DOM
 * morphing unpredictably (e.g. a slider and its paired number input,
 * both wire:model-bound to the same property, stop reflecting each
 * other's changes) since Livewire's Alpine plugin hooks can end up
 * registered against the wrong instance. Registering through the
 * `alpine:init` event instead targets Livewire's own instance, which it
 * dispatches just before calling Alpine.start() itself.
 *
 * Drives client-side conditional field visibility for the customer journey engine.
 * `conditions` maps field key -> { field, operator, value } | null, mirroring
 * App\Modules\Journey\Models\Concerns\EvaluatesCondition on the server.
 */
/**
 * Indian digit-grouping (8,00,000 rather than 800,000) for the raw digit
 * string a currency input holds — the last three digits together, then
 * pairs of two for the rest. Mirrors App\Support\Formatting\IndianNumberFormatter
 * so the client-side and server-side rendering agree.
 */
function formatIndianNumber(digits) {
    if (!digits) {
        return '';
    }

    const negative = digits.startsWith('-');
    let value = negative ? digits.slice(1) : digits;
    value = value.replace(/^0+(?=\d)/, '');

    if (value.length <= 3) {
        return (negative ? '-' : '') + value;
    }

    const lastThree = value.slice(-3);
    let remaining = value.slice(0, -3);
    const groups = [];

    while (remaining.length > 2) {
        groups.unshift(remaining.slice(-2));
        remaining = remaining.slice(0, -2);
    }

    if (remaining) {
        groups.unshift(remaining);
    }

    return (negative ? '-' : '') + groups.join(',') + ',' + lastThree;
}

/**
 * Formats up to 8 raw digits typed for a date-of-birth field as DD/MM/YYYY —
 * used instead of the native <input type="date"> picker because its displayed
 * format follows the browser/OS locale (e.g. MM/DD/YYYY on a US-locale
 * browser), which doesn't match the dd/mm/yyyy format Indian applicants expect.
 */
function formatDateInput(digits) {
    if (!digits) {
        return '';
    }

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4, 8)}`;
}

/**
 * Converts the DD/MM/YYYY digit string above into the ISO yyyy-mm-dd format
 * the server's `date` validation rule and the database column expect.
 */
function dateInputToIso(digits) {
    if (digits.length !== 8) {
        return '';
    }

    return `${digits.slice(4, 8)}-${digits.slice(2, 4)}-${digits.slice(0, 2)}`;
}

/**
 * Reverses dateInputToIso() — used to prefill the masked input from a
 * previously saved yyyy-mm-dd response (old() input or a resumed session).
 */
function isoToDateInputDigits(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');

    return match ? `${match[3]}${match[2]}${match[1]}` : '';
}

/**
 * Spells out a rupee amount using the Indian numbering system (Thousand,
 * Lakh, Crore, ...) so customers can double-check the figure they typed —
 * e.g. 800000 -> "Eight Lakh".
 */
function numberToIndianWords(digits) {
    let amount = parseInt(digits, 10);

    if (Number.isNaN(amount)) {
        return '';
    }

    if (amount === 0) {
        return 'Zero';
    }

    const negative = amount < 0;
    amount = Math.abs(amount);

    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const scales = ['', 'Thousand', 'Lakh', 'Crore', 'Arab', 'Kharab'];

    const threeDigitWords = (n) => {
        let words = '';

        if (n >= 100) {
            words += `${ones[Math.floor(n / 100)]} Hundred `;
            n %= 100;
        }

        if (n >= 20) {
            words += `${tens[Math.floor(n / 10)]} `;
            n %= 10;
        }

        if (n > 0) {
            words += `${ones[n]} `;
        }

        return words.trim();
    };

    const groups = [amount % 1000];
    amount = Math.floor(amount / 1000);

    while (amount > 0) {
        groups.push(amount % 100);
        amount = Math.floor(amount / 100);
    }

    let words = '';
    for (let i = groups.length - 1; i >= 0; i--) {
        if (groups[i] > 0) {
            words += `${threeDigitWords(groups[i])} ${scales[i]} `;
        }
    }

    return (negative ? 'Minus ' : '') + words.trim().replace(/\s+/g, ' ');
}

/**
 * Run right before a native form submits — currency inputs display
 * Indian comma grouping once blurred (see the `data-currency-input`
 * fields), which the server's `numeric` validation rules would reject,
 * so this strips the commas back to plain digits first.
 */
function stripCurrencyCommas(form) {
    form.querySelectorAll('[data-currency-input]').forEach((el) => {
        el.value = el.value.replace(/[^0-9]/g, '');
    });
}

/**
 * Appends one more upload slot to a document requirement that allows
 * multiple uploads (e.g. "Other document"). Clones the requirement's
 * <template>, swapping the __SLOT__ placeholder in each field's `name`
 * for the next free slot index, so the new inputs post alongside the
 * server-rendered ones under `documents[type][slot]`.
 */
function addDocumentSlot(button, documentTypeId) {
    const container = document.querySelector(`[data-document-slots="${documentTypeId}"]`);
    const template = document.querySelector(`[data-document-slot-template="${documentTypeId}"]`);

    if (!container || !template) {
        return;
    }

    const slot = Number(container.dataset.nextSlot);
    const clone = template.content.cloneNode(true);

    clone.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace('__SLOT__', slot);
    });

    container.appendChild(clone);
    container.dataset.nextSlot = slot + 1;
}

document.addEventListener('click', (event) => {
    const addButton = event.target.closest('[data-add-document-slot]');

    if (addButton) {
        addDocumentSlot(addButton, addButton.dataset.addDocumentSlot);

        return;
    }

    const removeButton = event.target.closest('[data-remove-document-slot]');

    if (removeButton) {
        removeButton.closest('[data-document-slot]').remove();
    }
});

/**
 * The "Upload documents" form has one file input per document slot, all
 * optional individually (a customer might only fill in one this visit) —
 * but submitting with every input empty just redisplays the same page with
 * nothing to show for it, so this blocks that specific case client-side.
 * ApplicationController::uploadDocuments() enforces the same rule server-side.
 */
document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-require-file-upload]');

    if (!form) {
        return;
    }

    const hasFile = Array.from(form.querySelectorAll('input[type="file"]')).some((input) => input.files.length > 0);

    if (hasFile) {
        return;
    }

    event.preventDefault();

    const error = form.querySelector('[data-upload-empty-error]');

    if (error) {
        error.hidden = false;
    }
});

window.formatIndianNumber = formatIndianNumber;
window.formatDateInput = formatDateInput;
window.dateInputToIso = dateInputToIso;
window.isoToDateInputDigits = isoToDateInputDigits;
window.numberToIndianWords = numberToIndianWords;
window.stripCurrencyCommas = stripCurrencyCommas;
window.addDocumentSlot = addDocumentSlot;

/**
 * Scroll-triggered entrance reveal, used across the marketing pages for the
 * "world class" polish pass. Deliberately varied rather than one effect
 * reused everywhere: `data-reveal="<variant> [stagger] [delay-1|2|3]"` picks
 * the entrance style — up (default), down, left, right, zoom, fade — so
 * sections read as distinct from each other down the page. The hidden state
 * itself is styled in app.css directly off the `[data-reveal]` attribute
 * (present from first paint) rather than a class this script adds — see the
 * comment there for why: adding the hidden state via JS turned out to start
 * an unwanted fade-out transition of its own, which then visibly collided
 * with the real reveal for anything already in the viewport on load. This
 * function only ever needs to set each element's stagger/delay timing and
 * flip it to `.reveal-visible` once — no artificial pause required.
 *
 * This is plain DOM/IntersectionObserver code, NOT an Alpine directive, and
 * deliberately so: Livewire's bundled Alpine is booted by a classic
 * (non-deferred) <script src="livewire.js"> that Blade emits, which can run
 * and call Alpine.start() — walking the whole DOM — before this file (a
 * type="module" script, always deferred) even executes. A custom
 * Alpine.directive() registered after that point never applies to elements
 * that already existed at Alpine.start() time, since Alpine doesn't
 * retroactively re-scan the DOM for newly-registered directives — so the
 * reveal would silently never fire on a normal page load. Plain JS run at
 * module-execution time (guaranteed to be after the HTML is fully parsed)
 * has no such race.
 */
function initScrollReveal() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const delays = { 'delay-1': '100ms', 'delay-2': '200ms', 'delay-3': '300ms' };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -10% 0px' });

    document.querySelectorAll('[data-reveal]').forEach((el) => {
        const tokens = el.dataset.reveal.split(/\s+/).filter(Boolean);

        const delayToken = tokens.find((t) => t in delays);
        if (delayToken) {
            el.style.transitionDelay = delays[delayToken];
        } else if (tokens.includes('stagger')) {
            // Reveals items in a grid/list one by one rather than all at once —
            // the delay is based on the element's position among its siblings,
            // capped so a long list doesn't take forever to finish appearing.
            const index = Array.prototype.indexOf.call(el.parentElement?.children ?? [], el);
            el.style.transitionDelay = `${Math.min(Math.max(index, 0), 10) * 130}ms`;
        }

        observer.observe(el);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollReveal);
} else {
    initScrollReveal();
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('journeyStep', (initialValues, conditions, phoneVerification = {}) => ({
        values: initialValues,

        phoneRequiresVerification: phoneVerification.required ?? false,
        phoneVerified: phoneVerification.verified ?? false,
        verifiedPhoneNumber: phoneVerification.verifiedNumber ?? null,
        phoneOtpSent: false,
        phoneOtpCode: '',
        phoneOtpChallengeId: null,
        phoneDemoOtpCode: null,
        phoneOtpError: null,
        phoneOtpLoading: false,

        init() {
            // Editing the number after it's been verified invalidates that verification —
            // otherwise someone could verify one number, then swap in a different one and
            // still pass the (now stale) phoneVerified flag.
            this.$watch('values.phone', (value) => {
                if (this.phoneVerified && value !== this.verifiedPhoneNumber) {
                    this.phoneVerified = false;
                    this.phoneOtpSent = false;
                    this.phoneOtpCode = '';
                    this.phoneOtpChallengeId = null;
                    this.phoneDemoOtpCode = null;
                }
            });
        },

        isVisible(key) {
            if (this.phoneRequiresVerification && key !== 'phone' && !this.phoneVerified) {
                return false;
            }

            const condition = conditions[key];

            if (!condition) {
                return true;
            }

            const actual = this.values[condition.field];

            if (condition.operator === '!=') {
                return actual != condition.value;
            }

            if (condition.operator === 'in') {
                return Array.isArray(condition.value) && condition.value.includes(actual);
            }

            return actual == condition.value;
        },

        async requestOtp(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json().catch(() => ({}));

            return { ok: response.ok, data };
        },

        async sendPhoneOtp() {
            this.phoneOtpError = null;
            this.phoneOtpLoading = true;

            try {
                const { ok, data } = await this.requestOtp(phoneVerification.sendOtpUrl, { phone: this.values.phone });

                if (!ok) {
                    this.phoneOtpError = data.message ?? data.errors?.phone?.[0] ?? 'Could not send the code. Please check the number and try again.';

                    return;
                }

                this.phoneOtpChallengeId = data.otp_challenge_id;
                this.phoneDemoOtpCode = data.demo_otp_code ?? null;
                this.phoneOtpSent = true;
                this.phoneOtpCode = '';
            } finally {
                this.phoneOtpLoading = false;
            }
        },

        async verifyPhoneOtp() {
            this.phoneOtpError = null;
            this.phoneOtpLoading = true;

            try {
                const { ok, data } = await this.requestOtp(phoneVerification.verifyOtpUrl, {
                    otp_challenge_id: this.phoneOtpChallengeId,
                    otp_code: this.phoneOtpCode,
                });

                if (!ok) {
                    this.phoneOtpError = data.message ?? 'That code is incorrect or has expired.';

                    return;
                }

                this.phoneVerified = true;
                this.verifiedPhoneNumber = this.values.phone;
            } finally {
                this.phoneOtpLoading = false;
            }
        },
    }));
});

/**
 * Analytics for the Quick Enquiry form. Pushed to GTM's dataLayer and sent as a
 * GA4 event, but only to whichever of the two is actually on the page —
 * TrackingScripts renders neither until an admin enables it AND the visitor's
 * cookie consent allows analytics, so this must stay a no-op rather than
 * creating a dataLayer of its own and re-implementing a consent decision that
 * was already made server-side.
 */
function trackEnquiry(event, config, params = {}) {
    trackQuickEnquiry(event, { loan_product: config.product, loan_product_name: config.productName, ...params });
}

function trackQuickEnquiry(event, params = {}) {
    if (Array.isArray(window.dataLayer)) {
        window.dataLayer.push({ event, ...params });
    }

    if (typeof window.gtag === 'function') {
        window.gtag('event', event, params);
    }
}

document.addEventListener('alpine:init', () => {
    /**
     * The whole Quick Enquiry interaction: sanitise, send the code, verify,
     * confirm. Two steps — `phone` then `otp` — because the number is verified
     * before a lead is written, so the team never calls a number nobody owns.
     *
     * Every rule enforced here is enforced again in QuickEnquiryController —
     * this exists to make the form quick and clear, not to be the gate. In
     * particular the code itself is only ever judged server-side; nothing here
     * knows what it is.
     */
    window.Alpine.data('quickEnquiryForm', (config) => ({
        phone: config.phone ?? '',
        otpCode: '',
        // Seeded from the server so a no-JavaScript round trip that already sent
        // a code is picked up mid-flow rather than restarted from the number.
        step: config.step ?? 'phone',
        challengeId: config.challengeId ?? null,
        demoCode: config.demoCode ?? null,
        error: null,
        otpError: null,
        loading: false,
        done: false,
        resultTitle: '',
        resultMessage: '',
        entryTracked: false,

        init() {
            trackQuickEnquiry('quick_enquiry_view', { source: config.source });
        },

        // Indian mobile numbers are ten digits starting 6-9. Mirrors the
        // `regex:/^[6-9]\d{9}$/` rule the controller and the journey OTP step use.
        get isValid() {
            return /^[6-9]\d{9}$/.test(this.phone);
        },

        /**
         * Strips anything that is not a digit and caps the field at ten of them,
         * writing the cleaned value straight back — so a pasted "+91 98765 43210"
         * becomes "9876543210" in front of the visitor rather than being silently
         * rejected on submit.
         */
        onInput(event) {
            this.phone = event.target.value.replace(/\D+/g, '').slice(0, 10);
            event.target.value = this.phone;
            this.error = null;

            if (this.phone.length > 0 && !this.entryTracked) {
                this.entryTracked = true;
                trackQuickEnquiry('quick_enquiry_phone_entered', { source: config.source });
            }
        },

        /** Same digits-only treatment for the six-digit code. */
        onOtpInput(event) {
            this.otpCode = event.target.value.replace(/\D+/g, '').slice(0, 6);
            event.target.value = this.otpCode;
            this.otpError = null;
        },

        /** Step one: ask the server to text (for now, hand back) a code. */
        async sendOtp() {
            // The button is disabled while a request is in flight; this guards the
            // Enter key, which submits the form regardless of the button's state.
            if (this.loading) {
                return;
            }

            if (!this.isValid) {
                this.error = 'Please enter a valid 10-digit mobile number.';
                trackQuickEnquiry('quick_enquiry_validation_failed', { source: config.source });

                return;
            }

            this.loading = true;
            this.error = null;

            try {
                const { response, data } = await this.post(config.otpEndpoint, {
                    phone: this.phone,
                    // The honeypot travels with the request so a bot that fills the
                    // rendered form and replays it is rejected server-side.
                    website: this.$refs.honeypot?.value ?? '',
                });

                if (response.status === 429) {
                    this.error = 'Too many attempts. Please try again in a few minutes.';

                    return;
                }

                if (!response.ok) {
                    this.error = data.errors?.phone?.[0] ?? data.message ?? 'Please enter a valid 10-digit mobile number.';
                    trackQuickEnquiry('quick_enquiry_validation_failed', { source: config.source });

                    return;
                }

                this.challengeId = data.otp_challenge_id;
                // Null once an SMS gateway is connected; the panel only shows the
                // demo-mode notice while the server is still handing the code back.
                this.demoCode = data.demo_otp_code ?? null;
                this.otpCode = '';
                this.otpError = null;
                this.step = 'otp';
                trackQuickEnquiry('quick_enquiry_otp_sent', { source: config.source });
                this.$nextTick(() => this.$el.querySelector('input[name="otp_code"]')?.focus());
            } catch {
                this.error = 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        async resendOtp() {
            this.otpError = null;
            await this.sendOtp();
        },

        /** Back to step one with the number still in the field, ready to be corrected. */
        editPhone() {
            this.step = 'phone';
            this.challengeId = null;
            this.demoCode = null;
            this.otpCode = '';
            this.otpError = null;
            this.error = null;
        },

        /** Step two: the code goes up with the number and the lead is recorded. */
        async submit() {
            if (this.loading) {
                return;
            }

            if (!/^\d{6}$/.test(this.otpCode)) {
                this.otpError = 'Please enter the 6-digit code we sent you.';

                return;
            }

            this.loading = true;
            this.otpError = null;
            trackQuickEnquiry('quick_enquiry_submitted', { source: config.source });

            try {
                const { response, data } = await this.post(config.endpoint, {
                    phone: this.phone,
                    source: config.source,
                    otp_challenge_id: this.challengeId,
                    otp_code: this.otpCode,
                    website: this.$refs.honeypot?.value ?? '',
                });

                if (response.status === 429) {
                    this.otpError = 'Too many attempts. Please try again in a few minutes.';

                    return;
                }

                if (!response.ok) {
                    this.otpError = data.errors?.otp_code?.[0] ?? data.errors?.phone?.[0] ?? data.message ?? 'That code is incorrect or has expired. You can request a new one.';
                    trackQuickEnquiry('quick_enquiry_otp_failed', { source: config.source });

                    return;
                }

                this.done = true;
                this.resultTitle = data.title;
                this.resultMessage = data.message;
                trackQuickEnquiry(
                    data.outcome === 'duplicate' ? 'quick_enquiry_duplicate' : 'quick_enquiry_success',
                    { source: config.source },
                );
            } catch {
                this.otpError = 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * @returns {Promise<{response: Response, data: object}>}
         */
        async post(endpoint, body) {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            return { response, data: await response.json().catch(() => ({})) };
        },
    }));
});

document.addEventListener('alpine:init', () => {
    /**
     * The loan-page enquiry form: name, mobile, optional email, amount.
     *
     * Every rule enforced here is enforced again in LoanEnquiryController —
     * this exists to make the form quick and clear, not to be the gate. The
     * loan product is not held in state at all: the form posts to that
     * product's own endpoint, so there is nothing here for a visitor to edit
     * that would change which product the lead is counted against.
     */
    window.Alpine.data('loanEnquiryForm', (config) => ({
        // loan_product is seeded from config rather than read back from the DOM:
        // x-model writes the initial value into the <select> before init() runs.
        values: { name: '', phone: '', email: '', loan_amount: '', loan_product: config.selected ?? '' },
        errors: {},
        loading: false,
        done: false,
        resultTitle: '',
        resultMessage: '',
        startTracked: false,

        /**
         * The product the form is currently about: the page's own product on a
         * loan page, or whatever the dropdown says on the Quick Enquiry page.
         * Headline, amount hint and range check all read from here.
         */
        get selected() {
            return config.products?.[this.values.loan_product] ?? null;
        },

        get tracking() {
            return this.selected
                ? { product: this.values.loan_product.replace(/-/g, '_'), productName: this.selected.name }
                : { product: 'quick_enquiry', productName: 'Quick Enquiry' };
        },

        init() {
            trackEnquiry('loan_enquiry_form_view', this.tracking);

            // Pick up anything the browser restored or a no-JavaScript bounce
            // left in the fields, so state and DOM agree before the first edit.
            this.$nextTick(() => {
                Object.keys(this.values).forEach((key) => {
                    const field = this.$el.querySelector(`[name="${key}"]`);

                    if (field?.value) {
                        this.values[key] = field.value;
                    }
                });
            });
        },

        onInput(event) {
            this.errors[event.target.name] = null;
            this.trackStart();
        },

        /**
         * Digits only, capped at ten, written straight back — a pasted
         * "+91 98765 43210" becomes "9876543210" in front of the visitor rather
         * than being silently rejected on submit.
         */
        onPhoneInput(event) {
            this.values.phone = event.target.value.replace(/\D+/g, '').slice(0, 10);
            event.target.value = this.values.phone;
            this.errors.phone = null;
            this.trackStart();
        },

        /**
         * Indian digit grouping as they type (5,00,000 rather than 500,000),
         * matching the journey's currency inputs. The commas are display only —
         * the request sends bare digits, and the server strips them again.
         */
        onAmountInput(event) {
            const digits = event.target.value.replace(/\D+/g, '').slice(0, 10);
            this.values.loan_amount = formatIndianNumber(digits);
            event.target.value = this.values.loan_amount;
            this.errors.loan_amount = null;
            this.trackStart();
        },

        /**
         * A new loan type brings a new amount range, so an amount already typed
         * is re-judged the moment the dropdown changes — ₹1,00,000 is flagged
         * on switching to a Home Loan, not only when the visitor hits submit.
         */
        onProductChange() {
            this.errors.loan_product = null;
            this.errors.loan_amount = this.amountDigits ? this.amountRangeError() : null;
            this.trackStart();
        },

        trackStart() {
            if (!this.startTracked) {
                this.startTracked = true;
                trackEnquiry('loan_enquiry_form_started', this.tracking);
            }
        },

        get amountDigits() {
            return this.values.loan_amount.replace(/\D+/g, '');
        },

        /**
         * The selected product's own min/max and the exact message the server
         * would answer with, both rendered from LoanEnquiryAmount.
         */
        amountRangeError() {
            const amount = Number(this.amountDigits);

            if (!this.selected || !amount) {
                return null;
            }

            return amount < this.selected.min || amount > this.selected.max ? this.selected.rangeMessage : null;
        },

        validate() {
            const errors = {};

            if (config.selectable && !this.selected) {
                errors.loan_product = 'Please select a loan type.';
            }

            if (!this.values.name.trim()) {
                errors.name = 'Please enter your name.';
            }

            // Indian mobile numbers are ten digits starting 6-9. Mirrors the
            // `regex:/^[6-9]\d{9}$/` rule the controller uses.
            if (!/^[6-9]\d{9}$/.test(this.values.phone)) {
                errors.phone = 'Please enter a valid 10-digit mobile number.';
            }

            if (this.values.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.values.email.trim())) {
                errors.email = 'Please enter a valid email address.';
            }

            if (!this.amountDigits || Number(this.amountDigits) <= 0) {
                errors.loan_amount = 'Please enter the loan amount you need.';
            } else if (this.amountRangeError()) {
                errors.loan_amount = this.amountRangeError();
            }

            this.errors = errors;

            return Object.keys(errors).length === 0;
        },

        async submit() {
            // The button is disabled while a request is in flight; this guards the
            // Enter key, which submits the form regardless of the button's state.
            if (this.loading) {
                return;
            }

            if (!this.validate()) {
                trackEnquiry('loan_enquiry_failed', this.tracking, { reason: 'validation' });

                return;
            }

            this.loading = true;
            trackEnquiry('loan_enquiry_submitted', this.tracking);

            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        // Only the dropdown form names its product; a loan page's
                        // product is its URL, and it never sends one.
                        ...(config.selectable ? { loan_product: this.values.loan_product } : {}),
                        name: this.values.name,
                        phone: this.values.phone,
                        email: this.values.email,
                        loan_amount: this.amountDigits,
                        // The honeypot travels with the request so a bot that fills
                        // the rendered form and replays it is rejected server-side.
                        website: this.$refs.honeypot?.value ?? '',
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (response.status === 429) {
                    this.errors = { phone: 'Too many attempts. Please try again in a few minutes.' };
                    trackEnquiry('loan_enquiry_failed', this.tracking, { reason: 'rate_limited' });

                    return;
                }

                if (!response.ok) {
                    // Laravel returns { errors: { field: [message] } } on a 422.
                    this.errors = Object.fromEntries(
                        Object.entries(data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
                    );

                    if (Object.keys(this.errors).length === 0) {
                        this.errors = { phone: data.message ?? 'Something went wrong. Please try again.' };
                    }

                    trackEnquiry('loan_enquiry_failed', this.tracking, { reason: 'validation' });

                    return;
                }

                this.done = true;
                this.resultTitle = data.title;
                this.resultMessage = data.message;
                trackEnquiry('loan_enquiry_success', this.tracking, { outcome: data.outcome });
            } catch {
                this.errors = { phone: 'Something went wrong. Please try again.' };
                trackEnquiry('loan_enquiry_failed', this.tracking, { reason: 'network' });
            } finally {
                this.loading = false;
            }
        },
    }));
});

document.addEventListener('alpine:init', () => {
    /**
     * The sticky promo bar (x-site.promo-bar). `config` comes from
     * App\Models\PromoBar::clientConfig(); every message is already in the HTML.
     *
     * Shown = triggered, not dismissed, and the footer not on screen — so it
     * gets out of the way of the legal copy and comes back on scrolling up.
     * Closing it is remembered in localStorage for `reshowAfterHours`.
     *
     * While it is up, --promo-bar-offset on <html> holds its height, so other
     * bottom-corner widgets (the video testimonial bubble) can sit above it.
     */
    window.Alpine.data('promoBar', (config) => ({
        triggered: false,
        dismissed: false,
        nearFooter: false,
        paused: false,
        messageIndex: 0,
        countdown: '',

        get shown() {
            return this.triggered && !this.dismissed && !this.nearFooter;
        },

        init() {
            if (!this.matchesDevice() || this.recentlyDismissed()) {
                return;
            }

            this.$watch('shown', (shown) => this.$nextTick(() => this.reportHeight(shown)));
            this.observeFooter();
            this.armTrigger();

            if (config.countdownEndsAt) {
                this.startCountdown(new Date(config.countdownEndsAt).getTime());
            }
        },

        matchesDevice() {
            const wide = window.matchMedia('(min-width: 640px)').matches;

            return config.device === 'all' || (config.device === 'desktop' ? wide : !wide);
        },

        storageKey() {
            return `fynnedge.promo-bar-dismissed.${config.id}`;
        },

        recentlyDismissed() {
            try {
                return Number(localStorage.getItem(this.storageKey())) > Date.now();
            } catch {
                return false;
            }
        },

        armTrigger() {
            const hasMouse = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

            if (config.trigger === 'delay') {
                setTimeout(() => this.show(), config.triggerValue * 1000);

                return;
            }

            if (config.trigger === 'exit_intent' && hasMouse) {
                const onLeave = (event) => {
                    if (event.relatedTarget === null && event.clientY <= 0) {
                        document.removeEventListener('mouseout', onLeave);
                        this.show();
                    }
                };

                document.addEventListener('mouseout', onLeave);

                return;
            }

            // Scroll, and exit intent on touch screens, which have no tab bar to head for.
            const threshold = config.trigger === 'exit_intent' ? 50 : config.triggerValue;
            const onScroll = () => {
                const scrollable = document.documentElement.scrollHeight - window.innerHeight;
                const percent = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 100;

                if (percent >= threshold) {
                    window.removeEventListener('scroll', onScroll);
                    this.show();
                }
            };

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        },

        show() {
            if (this.triggered || this.dismissed) {
                return;
            }

            this.triggered = true;
            this.track('promo_bar_view');

            if (config.messageCount > 1) {
                setInterval(() => {
                    if (!this.paused && this.shown) {
                        this.messageIndex = (this.messageIndex + 1) % config.messageCount;
                    }
                }, 4000);
            }
        },

        observeFooter() {
            const footer = document.querySelector('body > footer');

            if (!footer || !('IntersectionObserver' in window)) {
                return;
            }

            new IntersectionObserver(([entry]) => {
                this.nearFooter = entry.isIntersecting;
            }).observe(footer);
        },

        startCountdown(endsAt) {
            const pad = (value) => String(value).padStart(2, '0');
            const tick = () => {
                const seconds = Math.floor((endsAt - Date.now()) / 1000);

                if (seconds <= 0) {
                    clearInterval(timer);
                    this.countdown = '';
                    this.dismissed = true;

                    return;
                }

                const days = Math.floor(seconds / 86400);
                const clock = `${pad(Math.floor((seconds % 86400) / 3600))}h ${pad(Math.floor((seconds % 3600) / 60))}m ${pad(seconds % 60)}s`;

                this.countdown = days > 0 ? `${days}d ${clock}` : clock;
            };

            const timer = setInterval(tick, 1000);
            tick();
        },

        dismiss() {
            this.dismissed = true;
            this.track('promo_bar_dismiss');

            if (config.reshowAfterHours > 0) {
                try {
                    localStorage.setItem(this.storageKey(), String(Date.now() + config.reshowAfterHours * 3600000));
                } catch {}
            }
        },

        reportHeight(shown) {
            document.documentElement.style.setProperty('--promo-bar-offset', `${shown ? this.$el.offsetHeight : 0}px`);
        },

        track(event) {
            if (Array.isArray(window.dataLayer)) {
                window.dataLayer.push({ event, promo_bar_id: config.id, promo_bar_name: config.name });
            }
        },
    }));
});
