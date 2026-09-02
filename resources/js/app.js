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
