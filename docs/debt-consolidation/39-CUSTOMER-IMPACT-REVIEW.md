# 39 — Customer impact review: the demo lender result and the 26 Nov DC enquiry

**Status (27 Nov 2026):** investigation complete, from the production export of 27 Nov 06:31 UTC. **No customer has been contacted.** The draft message in §7 waits for the Owner's (and Legal's) approval.
**Scope:** Owner decision 4 of 27 Nov. Incident background is in `36-…`.
**Personal data:** this document identifies the customer only by internal IDs. Name, mobile, e-mail and city stay in the production database and the private export.

## 1. Summary

One customer was affected:
- They ran a personal loan eligibility check on the **duplicate** personal loan product that the seeder created.
- The only lender on it was the test entry **"DEMO Lender Inc 171"** (offer #359), with test rules (rule set #3).
- They were shown **"not eligible"**.
- Three minutes later they sent a **debt consolidation enquiry**.

No real lender assessed them. No credit bureau was contacted. Their details were not sent to any lender.

| Record | ID |
| --- | --- |
| Customer | #136 (portal account created during the check) |
| Eligibility journey | #1437 on product #14 (duplicate `personal-loan`), completed |
| Eligibility result | #1468: offer #359 (DEMO Lender Inc 171), rule set #3, **not eligible**, FOIR 60.65 % |
| DC enquiry | Application #1, reference `DC-RLTMX6SD`, status `enquiry_received`, contact enquiry #53 |
| Consent | DC consent #1, purpose `contact` (call, SMS, WhatsApp or e-mail **about this request**), web checkbox, text version 1 |

## 2. Timeline

| UTC (26 Nov) | IST (27 Nov) | Event |
| --- | --- | --- |
| 20:45:38 | 02:15 | Starts an eligibility journey on `/loans/personal-loan` (the duplicate product); verifies mobile by one-time code. |
| 20:46:17 | 02:16 | Customer record and portal account created. |
| 20:49:31 | 02:19 | Journey completed (19 answers). The engine evaluates the only active offer on product #14: DEMO offer #359. Result #1468: not eligible. |
| 20:52:28 | 02:22 | Debt consolidation enquiry from `/debt-consolidation`: declared total outstanding ₹3,00,000 and monthly EMIs ₹22,000; mobile verified; contact consent recorded. Status moves `draft` → `enquiry_received`. |

As of the export (27 Nov 06:31 UTC) nothing had happened on the case since. It was unassigned, with no liabilities, assessment, lender match, report or notes. Re-check on production before contacting the customer.

## 3. What the customer was shown

The result was for one lender, "DEMO Lender Inc 171". The rules and reasons recorded with the result (#1468) are:

| Rule (test data) | Priority | Passed | Message shown with it |
| --- | --- | --- | --- |
| Age between 21 and 60 | mandatory | yes | "You meet the age requirement for this lender." |
| Minimum monthly income ₹25,000 | mandatory | yes | "Your income meets this lender's minimum requirement." |
| Employment vintage ≥ 1 year | mandatory | yes | "Your employment history meets the minimum requirement." |
| **FOIR ≤ 50 %** | mandatory | **no** (60.65 %) | "Your existing obligations are within this lender's permitted range." |
| Employer category A or B | preferred | no | "Your employer is in this lender's preferred category." |
| Has existing EMIs | warning | yes | "You have existing loan EMIs — this may affect the final offer." |

Two things are wrong with what they saw:
1. **The lender and the rules were test data**, not any real lender's policy.
2. **The failed rules' messages read as passes.** Each rule stores one message, and the demo rules' messages are written in the affirmative. So "not eligible" appeared next to "Your existing obligations are within this lender's permitted range."

Because they came in through the duplicate product, **they were never evaluated against the real personal loan lenders** on `/loans/personal-loans`.

## 4. What did not happen

Checked in the export:
- **No credit bureau check** for this customer: no CIBIL, Experian, Equifax or CRIF request, no credit-score check, no credit consent. The eligibility check uses only what the customer typed, so their credit score is unaffected.
- **Their details were not sent to any lender:** no loan application and no DC lender application or match.
- **No in-app notification** was stored. Whether an e-mail went out about the DC enquiry (customer e-mail updates are on in the DC settings) shows only in production's mail log. Check before contacting them.

## 5. Other visitors

- 4 other journeys ran on the duplicate product (#1436, #1438, #1441, #1442). All are still in progress and none produced an eligibility result, so **no one else was shown a demo result**.
- Result #1468 is the only eligibility result against a demo offer or demo rule set (#3, #4) since the incident.
- No other DC application exists.

## 6. Preservation

Nothing here is changed or deleted by the restore. The production runbook (`release/content-restore-2026-11-27/`) checks before and after that:
- DC application #1 is still there, with its consent, 2 status changes and audit trail;
- the 5 journeys are still there;
- eligibility result #1468 is still there;
- no row is lost from customers, enquiries, applications, debt, journey, eligibility or credit-score tables.

The restore makes product #14, its pages and the DEMO offer non-public. The rows themselves stay.

## 7. Draft corrective communication (for review; not sent)

**Constraints:**
- The customer's contact consent covers messages "about this request".
- Customer SMS needs a DLT-registered template, and only the OTP template exists (`00-…` "External dependencies"). So use a **call by an advisor**, followed by an **e-mail if one is on file**.
- Any promise in the message, such as an advisor following up, needs the Owner's decision while new DC applications are paused.

**Call script (advisor):**
> Hello, this is [advisor name] from FynnEdge Advisory. I'm calling about the personal loan eligibility check and the debt consolidation request you sent us on the night of 26 November.
> Because of a technical error on our website that night, the eligibility result you saw came from a **test lender entry**, not from a real bank or NBFC. Please disregard it. It was not a lending decision.
> No lender was contacted, no credit bureau was checked, and your credit score has not been affected.
> We are sorry for the confusion. [If the Owner approves: An advisor will look at your debt consolidation request and can check your eligibility with our real lending partners. When would be a good time?]

**E-mail:**
> **Subject:** About your eligibility check on fynnedge.com on 26 November
>
> Dear [customer name],
>
> On the night of 26 November you checked your personal loan eligibility on fynnedge.com and then asked us about debt consolidation.
>
> Because of a technical error on our website at the time, the eligibility result you saw came from a test lender entry, not from a real bank or NBFC. Please disregard it: it was not a lending decision, and some of the explanations shown with it were also incorrect.
>
> - No lender received your details.
> - No credit bureau was contacted, and your credit score has not been affected.
> - Your debt consolidation request (reference DC-RLTMX6SD) is safely recorded.
>
> [If the Owner approves: One of our advisors will contact you to review your request and, if you wish, check your eligibility with our real lending partners.] [Otherwise: We will contact you as soon as we are able to review new requests.]
>
> We apologise for the confusion.
>
> FynnEdge Advisory (OPC) Pvt Ltd · [contact details] · [grievance link]

## 8. Decisions for the Owner

1. Approve the message (wording, channel, and which bracketed option), with Legal's review.
2. Who contacts the customer, and when.
3. Whether this enquiry is reviewed while new applications stay paused. Pausing blocks **new** requests only; staff can still work existing cases.
4. Product #14: hide it with its journeys kept, or keep it. Staff edited it on 27 Nov 09:16 UTC, so the restore leaves it live for this decision.
