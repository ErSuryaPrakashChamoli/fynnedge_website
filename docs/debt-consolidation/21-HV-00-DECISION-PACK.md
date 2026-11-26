# 21 — HV-00 decision pack: use of redacted historical documents for validation

**For:** Legal and the Data Protection Officer (DPO), who decide; the Head of Advisory, who will run the work.
**Decision ID:** HV-00 (`17-BUSINESS-APPROVAL-TRACKER.md`, gate C in `30-RELEASE-GATE-MASTER-REGISTER.md`).
**Status:** **AWAITING DECISION.** No historical document has been requested, collected, copied or processed.

Engineering prepared this pack. It describes what is proposed. It draws no legal conclusion. Whether the proposal is lawful, and on what conditions, is for Legal and the DPO to decide.

## Decision requested

Do you permit FynnEdge staff to use **redacted copies of lender documents from 10 to 20 past, closed debt consolidation cases** to check the new calculation engine against real lender figures? If yes, on what conditions, and for how long may the validation pack be kept?

Record the decision in §10 and on the *HV-00 gate* sheet of `validation/historical/HV-historical-validation-workbook.xlsx`. The gate opens only when all nine points below are decided and each one carries a name, a date and a reference.

---

## 1. Purpose of historical validation

- The engine's arithmetic has already been checked against its own written specification (827 of 827 figures, `validation/DC-financial-validation-workbook.xlsx`). That check cannot show whether the specification matches how lenders actually calculate.
- This exercise compares the engine's figures with real lender documents, to the rupee: foreclosure amounts, remaining repayments, new loan amount, fees, GST and EMI (`18-…` §6, comparisons C1–C11).
- The results are the evidence that Finance and Credit need before signing BV-01, BV-06 and BV-09 (approvals FA-01 and FA-02).
- It is **internal testing only**. It makes no decision about any customer, contacts no customer and changes nothing in any customer's records.

## 2. Documents proposed for use

Only documents FynnEdge already holds from past, closed cases:

| Document | Why it is needed | Comparisons |
| --- | --- | --- |
| Loan statement or repayment schedule | Remaining instalments and interest | C3, C4, C7 |
| Credit card statement | Balance, rate, minimum due | C8 |
| Foreclosure letter or quotation | Closure amount, charge and GST | C1, C2 |
| Sanction letter and disbursal advice of the consolidation loan | Loan amount, fee, GST, net disbursal | C5, C6 |
| Closure payment proof and NOC | Confirms the closure actually happened | evidence only |
| Income documents | **Only** where a lender's FOIR or DSCR is compared | C10 |

Coverage target: 10–20 cases (`18-…` §3). No new document will be requested from any customer.

## 3. Anonymisation method

As set out in `18-…` §4:
- **Removed before anyone enters data**, on every copy:
  - name, PAN, Aadhaar, mobile, email, address, date of birth, employer, photos and signatures;
  - loan, card and bank-account numbers, UTRs, customer IDs and application numbers.
- **Kept:**
  - lender name, product type and branch city;
  - amounts, rates, tenures, EMIs, charges and GST, exactly;
  - document dates (month and year, plus the day only where validity or interest depends on it).
- Each case gets a code, HC-01 to HC-20. The key linking codes to real files is held only by the Head of Advisory, outside the validation pack.
- A second person checks the redaction of every document (*Evidence* and *Second check* sheets).
- The engine tool (`validation/historical/hc-harness.php`) refuses to run on a file:
  - that contains fields named like personal data;
  - that uses codes other than HC-01 to HC-20;
  - that has no HV-00 reference.

**Point for Legal/DPO.** Even redacted, a lender name, city, amounts and dates might allow someone to re-identify a customer. Legal/DPO may wish to decide whether this residual risk is acceptable, and whether to require more, for example:
- rounding dates to the month;
- or dropping the branch city.

Dropping amounts or rates is not possible: the comparison depends on them.

## 4. Who can access the documents

Proposed named group (names to be filled in by the Head of Advisory):

| Role | Access |
| --- | --- |
| Head of Advisory (owner; holds the code key) | Documents and workbook |
| 1–2 advisors (prepare inputs) | Redacted documents and workbook |
| Credit second checker | Redacted documents and workbook |
| Engineer (runs the engine) | Workbook and case pack only (figures, no documents) |

No other staff. No lenders, customers or external parties.

## 5. Storage location

- A restricted folder with access limited to the named people above. It must **not** be:
  - on a shared drive open to all staff;
  - in email threads;
  - in the website code repository;
  - on the website server.
- Operations creates the folder and records its location reference (not its contents) in the workbook.
- The engine runs on a developer machine **against a copy of the case pack in that folder**. The harness refuses to read or write inside the website repository.

## 6. Retention period: options for Legal to decide

| Option | Description | Consideration |
| --- | --- | --- |
| R1 | Delete the documents and the case pack within 30 days of the sign-off (§9 of `18-…`); keep only the summary sheet with no figures | Least data kept. Re-validation for a future formula version would need the documents collected again. |
| R2 | Keep the redacted pack for 12 months after sign-off, then delete | Allows audit questions and one re-validation cycle |
| R3 | Keep the redacted pack while formula version 1.0.0 is in use, plus 12 months | Lets every BV approval be re-traced for the life of the formula |
| R4 | Another period set by Legal/DPO | — |

The summary sheet holds no personal data and no document. Engineering suggests keeping it permanently as approval evidence, but this too is for Legal/DPO to confirm.

## 7. Secure deletion procedure (proposed)

1. On the deletion date, the Head of Advisory deletes the redacted copies, the case pack and the engine outputs from the restricted folder, plus any local copy on the engineer's machine.
2. Empty the trash or recycle bin. Where the folder is a cloud drive, remove the files from its version history and bin as well.
3. Destroy the code key.
4. Record in the workbook *Evidence* sheet ("Delete by" column) and in the tracker:
   - who deleted;
   - what was deleted;
   - when.
5. Original documents in FynnEdge's ordinary case files are not affected. They remain under FynnEdge's existing record-keeping policy.

## 8. Confirmation: no production customer records will be used

- No data will be read from, or written to, the website's production database.
- Validation cases are **not** created in the website admin. This matters because the case form records a customer-consent declaration, so creating one there would produce a false consent record (`18-…` §2).
- The engine runs offline from the case pack and reads no database.

## 9. Confirmation: documents will not be uploaded to the public website or shown to customers

- No document or figure from the validation is uploaded to the website, the admin, the customer portal or any public location.
- No customer receives anything from this exercise.
- The website code repository holds only empty templates and tools (`validation/historical/`).

---

## 10. Decision record

| # | Point | Decision (Approved / Approved with conditions / Rejected / Deferred) | Conditions | Decided by (name, role) | Date | Reference |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Purpose | | | | | |
| 2 | Documents | | | | | |
| 3 | Anonymisation | | | | | |
| 4 | Access | | | | | |
| 5 | Storage | | | | | |
| 6 | Retention (R1 / R2 / R3 / R4) | | | | | |
| 7 | Deletion | | | | | |
| 8 | No production records | | | | | |
| 9 | No website or customer exposure | | | | | |

Signed (Legal): ______________ Date: ________ Signed (DPO): ______________ Date: ________

**If HV-00 is rejected.**
- BV-01, BV-06 and BV-09 cannot receive their final sign-off on historical evidence.
- Finance and Credit would then need to decide whether another source of real lender figures is acceptable, for example:
  - fresh foreclosure quotations obtained with new customers' consent;
  - lender-provided sample schedules.
- That decision belongs to them and is recorded in the tracker.
