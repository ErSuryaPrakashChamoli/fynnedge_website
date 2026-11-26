# 04 — Customer journey

```
 Visitor ─┬─ Quick enquiry (hub / personal / business pages)
          │    type · name · mobile · city · rough total · rough EMIs · contact consent
          │    → OTP → enquiry recorded (Lead Inbox + FynnOn LMS) → case "Enquiry received"
          │    → signed in to the customer portal → "Continue to detailed assessment"
          │
          ├─ Calculator (/debt-consolidation/calculator) — six views, nothing stored
          │    → "Save these figures…" stores a calculator snapshot and carries the loans
          │
          └─ Free assessment (/debt-consolidation/assessment)
               1 Who it is for (individual / business)
               2 Contact details (+ business name)
               3 Mobile OTP  → case opened (or the open one continued) → signed in
               4 Loans and cards (up to 20; full / partial / keep; overdue, charges) — auto-saved
               5 Income (individual) or cash flow (business)
               6 Employment or business details
               7 Collateral (optional)
               8 Assessment review: total debt, EMIs, average rate, FOIR/DSCR, flags
               9 Consolidation options: net saving, monthly change, months, fees, suitability
              10 Consent (assessment + contact), exact wording recorded
              11 Submit → snapshot stored, reference shown, customer + team notified
```

After submission (staff, Debt Consolidation module):

| Step | Who | What the customer sees |
| --- | --- | --- |
| 12 Reference | System | `DC-XXXXXXXX` on screen, by email (if given) and in the portal |
| 13 Assigned to FynnEdge | Staff | Advisor name on the case page |
| 14 Advisor review | Staff: verify loans and documents, record quotations, recalculate | Status, document requests and messages |
| 15 Lender options | Staff: match lenders, shortlist | "Your advisor suggests…" with a per-lender consent box |
| 16 Application to lender | Staff, only after that lender's consent | Lender application and its status |
| 17 Lender tracking | Staff records each lender update with evidence | Status per lender |
| 18 Disbursement and closure | Staff: payments, closure requests, NOCs | Progress per old loan |
| 19 Final closure | Automatic once every in-scope closure is verified | Case "Completed", closure report |

**Save and resume.** Nothing is stored until the OTP. From then on every step is saved (loans auto-save on each field). The customer resumes by opening the assessment page while signed in, or from My Profile → Debt Consolidation. Signing in uses the existing Existing Customer page (OTP to the same mobile).

**Duplicate detection.** One open, unsubmitted case per verified mobile and applicant type: the enquiry form, the assessment form and repeat visits continue it. A case an advisor opened for the same number is claimed by the customer once they verify it. Repeat enquiries increase the enquiry's repeat count instead of creating new leads.

**Editing after submission.** Locked, except while the case status is "Information pending" (the advisor asked for more), when the customer can update and resubmit. Resubmitting returns the case to "Assessment in progress" and alerts the advisor. A save on a locked case shows a message; submission re-checks that the income and profile steps are complete, so skipping steps cannot submit an empty case.

**Service switched off.** New enquiries, new cases and submissions are refused with a message, even from a form already open in the browser (signed previews included); links to the assessment appear only while that page is published and the service is on. Existing cases stay served in the portal and to staff.

**One case per type for a signed-in customer.** A signed-in customer with an open draft continues that draft; to start a request of the other type they finish or withdraw it first, or an advisor opens it (known limitation).

**Notifications.** Enquiry received, submission, resubmission after an information request, staff status changes, lender status changes, payment confirmed / NOC received / closure verified, case completed, report released — in the portal (English source, shown in the visitor's language) and by email to the address given on the enquiry or application (else the account's) when email updates are on. The team is also alerted when a customer withdraws a consent. Status moves the system makes alongside a lender or closure event (lender processing, closure in progress) are covered by that event's message. SMS updates are not sent (no DLT templates).
