# 25 — Operations readiness report and standard operating procedures

**For:**
- the Operations lead and the Head of Advisory, who approve these;
- advisors and Credit, who follow them.

**Date:** 26 Nov 2026.

**Status:** the SOPs below are **drafts for Operations approval** (OP-01; SOP-05 is BV-08). The time limits marked "proposed" are suggestions. Operations sets the real ones.

Screens named below are in the admin under **Debt Consolidation**. Field names are the labels staff see. The full screen guide is `13-operational-user-guide.md`.

## 1. Capability check

| Need (mandate §8) | What the system provides | Gap / decision | Status |
| --- | --- | --- | --- |
| Team notification email | One team address in *Debt Consolidation Settings → Team alert email*, plus the assigned advisor. Alerts on a new enquiry, a submitted assessment, information received, and a withdrawn consent. Alerts carry no personal data. | Address not yet set (OP-01). Production mail not yet confirmed (PR-02). | PENDING (Operations) |
| Internal assignment | *Assign* action on the case: advisor and follow-up date. *Assigned to me* and *Advisor* filters on the case list. | — | READY |
| Advisor follow-up | Case follow-up date; notes of type *Follow-up* with a due date and *Mark done*; the dashboard and case list show open follow-ups | — | READY |
| Foreclosure quotation verification | Quotations recorded per liability, with amount, date and validity; "verified" flag; expiry flagged; the engine uses a verified quotation instead of its estimate | SOP-05 approval = BV-08 | PENDING APPROVAL |
| Customer communication | Portal notifications plus email (when the customer gave an address); notes record calls, SMS, WhatsApp, email and meetings; notes can be marked visible to the customer | Wording under LA-02 | READY (wording pending) |
| SMS notifications | **None for DC case updates.** SMS is used only for OTP (existing provider). | Decision: are DLT case-update templates needed? (OP-01) | PENDING DECISION |
| Case escalation | No separate escalation module. Done by reassigning, setting a follow-up date and a *Follow-up* note to the Head of Advisory (SOP-13) | Acceptable as a procedure. A dedicated flag would be new work. | READY (procedure) |
| Document verification | *Verify* or *Reject (reason shown to the customer)* per document; replacement = new version; every download logged | — | READY |
| Lender application tracking | Lender applications: Not submitted → Submitted → Under review → Additional documents → Credit assessment → Approved / Rejected → Sanction letter → Disbursed / Withdrawn. Submitting needs the customer's consent for that lender. The lender decision is recorded with evidence. | — | READY |
| Debt closure follow-up | One closure record per old loan: Planned → Quotation requested / received → Payment initiated / confirmed → Closure requested → NOC awaited / received → Closure verified (or On hold / Cancelled). *Closure pending* count on the dashboard. | — | READY |
| NOC verification | A full closure cannot reach *NOC received* without an NOC document, or *Closure verified* unless that NOC is **verified**. The NOC must be on the same case, for the same loan, uploaded as an NOC, and not reused. A verified closure cannot change. | — | READY |
| Customer complaints | No complaints module in DC. The site's grievance page and contact route apply. SOP-13 records the complaint as a note on the case. | Grievance officer details (LA-01/LA-02) | READY (procedure) |
| Second-person checks on quotations, closure payments and closure verification | **Enforced by the system** (Owner decision OPS-D1, 26 Nov 2026): mandatory and independent, no exemption, the super admin included. The person who recorded or changed the record, or moved it through its statuses, is refused. | — | READY |
| Failed application handling | A lender rejection is recorded with its reason. The case stays open to try another shortlisted lender, or moves to *Not proceeding*. The customer is notified of status changes. | — | READY |
| Audit trail | Audit history tab (who changed which field, when); insert-only status history; activity timeline (consents, documents, downloads, reports, API reads) | — | READY |
| Operations dashboard | *Debt Consolidation → Dashboard*: total and new enquiries, applications in progress, individual vs business, total debt assessed, under assessment, potentially suitable, lender submissions, lender-confirmed approvals, disbursements, closures pending, completed | — | READY |
| Staff training | `13-operational-user-guide.md`; roles *Debt Advisor* (works cases) and second-person approvers | Training must use the final approved wording (after LA-02); record kept | PENDING (after LA-02) |

**Rule enforced by the system: a new loan's disbursal never closes an old loan.**
- Recording *Disbursed* opens closure tracking for each consolidated loan, in status *Planned*.
- Each closure then needs its own payment proof and, for a full closure, a verified NOC.
- Test: *records the lender decision with evidence and opens closure tracking on disbursement without closing any loan*.

---

## 2. Standard operating procedures

Each SOP covers: when, who, steps, controls, records and exceptions.

### SOP-01 New enquiry to application
- **When:** a team alert "new enquiry" arrives, or a case appears with status *Enquiry received*.
- **Who:** the Debt Advisor on duty.
- **Steps:**
  1. Open the case. Check the OTP-verified mobile and the consents tab.
  2. *Assign* it to yourself and set a follow-up date (proposed: the same working day).
  3. Call the customer and record the call as a *Call* note.
  4. Set status to *Contacted*.
  5. If the customer wants a full assessment, ask them to add their loans in the portal ("Start my free assessment"), or record them on the case with them on the phone.
  6. If they do not want to proceed, set *Not proceeding* with the reason.
- **Controls:** a case opened by staff needs a documented consent (*Create case* asks for it).
- **Record:** status history; notes.
- **Exceptions:** wrong number → SOP-02 step 4.

### SOP-02 Customer consent and verification
- **When:** before any assessment, and before any data goes to a lender.
- **Who:** the Debt Advisor.
- **Steps:**
  1. Check the *Consents* tab: assessment and contact are required. Lender sharing is per lender (SOP-07).
  2. Check the mobile is OTP-verified (shown on the case).
  3. For a case opened by phone, read the number back to the caller before saving. The case is attached to whoever next proves that number by OTP (`26-…` I1).
  4. Wrong or disputed number: do not share anything. Set *Not proceeding*, with the reason "contact not verified".
- **Controls:** the system refuses a lender submission without that lender's consent.
- **Record:** consent rows (text, version, time, IP, method).
- **Exceptions:** consent withdrawn → team alert. Stop using the data for that purpose at once; if it was lender sharing, tell that lender in writing.

### SOP-03 Liability entry and validation
- **When:** loans have been entered by the customer or the advisor.
- **Who:** the Debt Advisor.
- **Steps:**
  1. In *Existing liabilities*, compare each loan with its statement: outstanding principal, rate, EMI, months left, overdue amounts.
  2. Use *Verify* with *Verified* or *Discrepancy* and a note. Correct figures with *Edit*; the change is audit-logged.
  3. Check the flags: flat-rate suspected, repayment assumed, not consolidatable now (NPA, settled, written off, disputed, 90+ DPD, lock-in).
  4. *Recalculate* after any correction.
- **Controls:** every edit is in the audit history; snapshots are immutable.
- **Exceptions:** the customer disputes a figure → keep *Discrepancy* until a statement is received.

### SOP-04 Document collection
- **When:** after SOP-03.
- **Who:** the Debt Advisor.
- **Steps:**
  1. Ask for the documents the case needs. The customer uploads them in the portal, or staff upload them in *Documents → Upload*.
  2. Open each document and *Verify* it, or *Reject* it with a reason the customer can understand.
  3. A replacement becomes a new version; the old one is kept.
- **Controls:** PDF, JPG or PNG only, up to 10 MB, type checked from the file's contents; encrypted at rest; every download logged.
- **Exceptions:** never accept documents by personal email or WhatsApp for storage. If one arrives that way, upload it to the case and delete the local copy.

### SOP-05 Foreclosure quotation verification (BV-08)
- **When:** before any figure is relied on for a lender application or a closure payment.
- **Who:** the Debt Advisor records it; **a second person (Credit) verifies it**. The system enforces this: the person who recorded or changed the quotation cannot verify it (OPS-D1, no exemption).
- **Steps:**
  1. Request a foreclosure letter or quotation from each old lender (closure status *Quotation requested*).
  2. Record it on the liability: amount, quotation date, **valid until**, and the breakdown (principal, interest, charges, GST) if given. Attach the letter as a document.
  3. The second person compares the record with the letter and marks it verified.
  4. Check the validity date. The system flags an expired quotation (default validity 15 days, set in Settings). Get a fresh one if it will expire before payment.
- **Controls:** the engine uses a **verified** quotation in place of its own estimate. Closure payments are confirmed only against a known amount: the final amount, or the quotation for a full closure.
- **Record:** quotation row; document; audit log.

### SOP-06 Financial assessment
- **When:** loans have been validated and income entered.
- **Who:** the Debt Advisor.
- **Steps:**
  1. *Recalculate*.
  2. Review, in order:
     - the **Savings comparison**: the net financial benefit, not the EMI;
     - break-even;
     - **affordability**: FOIR or DSCR, before and after;
     - the **suitability** score and band;
     - every warning.
  3. If the result is "Not financially beneficial", say so plainly to the customer. Do not search for a scenario that hides it.
  4. Generate the *Advisor assessment* (internal) and, if appropriate, the customer report. Release a report to the customer only after review.
- **Controls:**
  - suitability, affordability, lender eligibility and lender approval are kept separate (`23-…` §3.4);
  - a lower EMI is never presented as a saving.

### SOP-07 Lender matching
- **When:** after SOP-06, if consolidation is suitable.
- **Who:** the Debt Advisor; Credit for policy questions.
- **Steps:**
  1. *Match lenders*. Only **approved, in-force** lender policies are used.
  2. Read each reason line. Resolve *More information needed* items.
  3. Check by hand any lender criteria the policy cannot hold (`lender-policy/LP-A` §3, "N" rows).
  4. *Shortlist* or *Reject* each match with a note.
  5. Shortlisted lenders appear to the customer as "Your advisor suggests…", with a per-lender consent.
- **Controls:**
  - a match is not an approval and is never described to the customer as one;
  - lender policies are entered by one person and approved by another (LP-01).
- **Note:** no policy is configured yet, so this step returns no lenders until LP-01 is done.

### SOP-08 Lender application submission
- **When:** the customer has consented for that lender.
- **Who:** the Debt Advisor.
- **Steps:**
  1. *Lender applications → Create* for the shortlisted offer, with the amount.
  2. Send the application through the lender's own channel. Record *Submitted*, with the lender reference.
  3. Record each lender update as it arrives, with evidence: *Additional documents required*, *Credit assessment*, *Approved* / *Rejected* (with reason), *Sanction letter issued* (attach it), *Disbursed* (amount and date).
- **Controls:**
  - submission is refused without that lender's consent;
  - if consent is withdrawn after submission, record only what the lender decides, and share nothing further (the team is alerted).

### SOP-09 Disbursal tracking
- **When:** the lender confirms disbursal.
- **Who:** the Debt Advisor.
- **Steps:**
  1. Record *Disbursed* with the amount, the date and the disbursal advice.
  2. The system opens a closure record for each consolidated loan, in status *Planned*.
  3. Check net disbursal against the case's closure requirement. Raise any shortfall with the customer at once.
- **Control:** **disbursal does not close any old loan.**

### SOP-10 Old liability repayment
- **When:** after disbursal, for each closure record.
- **Who:** the Debt Advisor records the payment; a second person confirms it. Enforced: the person who recorded or changed the payment cannot confirm it (OPS-D1, no exemption).
- **Steps:**
  1. Record each payment: amount, date, mode (lender direct, customer, other), who paid, and the UTR or reference. Attach the payment proof.
  2. *Confirm* the payment only after checking the proof. Confirmation is checked against the final amount or the verified quotation.
  3. Move the closure to *Payment confirmed*, then *Closure requested*.
- **Exceptions:** the paid amount differs from the quotation → put the closure *On hold* and get a revised quotation.

### SOP-11 Closure confirmation and NOC verification
- **When:** the old lender confirms closure.
- **Who:** the Debt Advisor; a second person verifies the closure. Enforced: anyone who changed the closure or recorded one of its status steps cannot verify it (OPS-D1, no exemption).
- **Steps:**
  1. Ask the old lender for the NOC or closure letter (closure status *NOC awaited*).
  2. Upload it as type **NOC / closure letter**, linked to that loan.
  3. *Verify* the document, then set *NOC received*.
  4. Check that the credit bureau later shows the account as closed (advisor follow-up, proposed after 45 days).
  5. Set *Closure verified*. This is final.
  6. When every closure is verified, set the case to *Completed*.
- **Controls:**
  - full closures cannot be verified without a **verified** NOC for that loan;
  - an NOC cannot be reused;
  - a verified closure cannot change.

### SOP-12 Customer communication
- **Who:** all staff on the case.
- **Rules:**
  1. Use the portal and email for updates. The system sends status updates automatically.
  2. Record every call, SMS, WhatsApp message, email and meeting as a note.
  3. Mark a note *visible to customer* only if its wording is fit for the customer.
  4. Never send figures or documents by unencrypted personal channels.
  5. Never promise approval, a rate, or savings beyond what the assessment shows.
  6. Use only the approved wording (LA-02) in templates.
- **Time limits (proposed):**
  - first call within one working day of an enquiry;
  - reply to a customer message within one working day;
  - share a lender's decision within one working day of receiving it.

### SOP-13 Complaints and escalation
- **When:** a customer complains, or a case is stuck. Proposed triggers: no progress for 5 working days, a lender silent for 10 working days, or a closure without an NOC 30 days after payment.
- **Who:** the Debt Advisor; the Head of Advisory; the Grievance Officer.
- **Steps:**
  1. Record the complaint as a note: date, channel, what the customer said.
  2. Reassign the case to the Head of Advisory, or add them, with a follow-up date.
  3. Acknowledge to the customer (proposed: within 2 working days) and resolve (proposed: within 15 days), or escalate to the Grievance Officer named on the site's grievance page.
  4. If the complaint is about a lender's decision, give the customer the lender's own grievance contact. FynnEdge cannot overturn it.
  5. Record the outcome and close the follow-up.
- **Record:** notes and the audit trail. Review open complaints monthly.

---

## 3. Approval

| Item | Approver | Decision | Date | Reference |
| --- | --- | --- | --- | --- |
| SOP-01 to SOP-13 | Operations lead + Head of Advisory | | | |
| SOP-05 (BV-08) | Operations | | | |
| Team alert email set | Operations | | | |
| SMS case-update templates: needed? | Operations + Owner | | | |
| Time limits | Operations | | | |
| OPS-D1: enforce second-person checks in the system for quotations, payments and closure verification? | Owner | **Decided: mandatory independent maker-checker, no exemption; implemented and tested** | 26 Nov 2026 | Owner, chat |
| Advisors trained (names, dates) | Operations | | | |

**Operations readiness: PENDING APPROVAL.** The system support is complete and tested. The SOPs, the team email, training and the SMS decision await Operations, and training follows LA-02.
