---
paths:
  - 'resources/views/components/ui/input-affix.blade.php,resources/views/components/site/loan-enquiry-form.blade.php,resources/views/components/site/quick-enquiry.blade.php'
---

# Components Site Views Components Site

## Every affixed input uses x-ui.input-affix at one fixed width
The leading box inside a bordered input group (+91 on phone, ₹ on amount, a person icon on name, @ on email) is always <x-ui.input-affix>, never a hand-rolled <span class="... px-3 ...">. It is `w-11 justify-center` on purpose: stacked in the enquiry form, every field's placeholder must start at the same x, and padding-sized affixes drift apart as their content changes width.

When adding an affix to a field that had none, move the border/tone classes ($inputTone / $inputToneError, plus the Alpine :class) onto the wrapper div and leave the input `w-full bg-transparent` with no border — otherwise you get a box inside a box. In loan-enquiry-form.blade.php the wrapper and inner-input class strings are the $groupClasses / $inputClasses vars; reuse them rather than retyping.

tests/Feature/LoanEnquiryTest.php counts the affix boxes on a rendered loan page (expects 4) — add a field with an affix there and update that count.
