<?php

namespace App\Support\Localization;

/**
 * The visitor-facing English that resources/js/app.js writes into the page
 * itself (OTP and enquiry form errors, the promo bar countdown, Livewire's
 * "page expired" prompt), handed to the script in the visitor's language.
 *
 * The layout prints forCurrentLocale() once as
 * <script type="application/json" id="site-translations">, and app.js's t()
 * looks each English string up there, falling back to the English itself.
 * So English pages carry no block at all and behave exactly as before, and
 * the Hindi lives in lang/hi.json beside every other interface string.
 *
 * Every string app.js passes to t() must be listed here, spelled exactly as
 * in the script (":name" placeholders included), or it stays English.
 */
final class ScriptTranslations
{
    /** @var list<string> */
    public const KEYS = [
        // Shared by the OTP and enquiry forms
        'Something went wrong. Please try again.',
        'Please enter a valid 10-digit mobile number.',
        'Please enter the :length-digit code we sent you.',
        'That code is incorrect or has expired.',
        'That code is incorrect or has expired. You can request a new one.',
        'Too many attempts. Please try again in a few minutes.',
        'Could not send the code. Please check the number and try again.',

        // Contact page phone verification (contactPhoneOtp)
        'Please enter a valid 10-digit mobile number, or leave the phone blank.',
        'We could not send a code. Please try again.',
        'Enter the code we texted you to confirm this number, or clear the phone field.',
        'Tap "Send code" to confirm this number, or clear the phone field.',

        // Loan enquiry form (loanEnquiryForm)
        'Please select a loan type.',
        'Please enter your name.',
        'Please enter a valid email address.',
        'Please enter the loan amount you need.',

        // Promo bar countdown unit labels (promoBar)
        'd',
        'h',
        'm',
        's',

        // Livewire's own prompts, re-shown translated by app.js
        "This page has expired.\nWould you like to refresh the page?",
        "The server returned an empty response.\nWould you like to refresh the page?",
    ];

    /**
     * English => translation for every key that has one in the visitor's
     * language. Empty in English, so English pages print nothing.
     *
     * @return array<string, string>
     */
    public static function forCurrentLocale(): array
    {
        if (! SiteLocale::isTranslating()) {
            return [];
        }

        $translations = [];

        foreach (self::KEYS as $english) {
            $translated = __($english);

            if (is_string($translated) && $translated !== $english) {
                $translations[$english] = $translated;
            }
        }

        return $translations;
    }

    /**
     * The JSON for the layout's <script type="application/json"> block.
     * JSON_HEX_TAG keeps a "</script>" inside a string from closing the tag.
     *
     * @param  array<string, string>  $translations
     */
    public static function toJson(array $translations): string
    {
        return json_encode($translations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
