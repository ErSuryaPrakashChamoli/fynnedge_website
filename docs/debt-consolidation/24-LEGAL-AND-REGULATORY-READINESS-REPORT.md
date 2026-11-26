# 24 — Legal and regulatory readiness report and decision pack

**For:** Legal (with the DPO and Compliance), and the Owner for the fee question.
**Date:** 26 Nov 2026.

**Engineering prepared this pack. It contains no legal conclusions.** It describes:
- how the product actually works, as built;
- the questions Legal must answer;
- draft wording for Legal to accept, change or reject.

Every proposed text is marked **DRAFT — SUBJECT TO LEGAL APPROVAL**. **None of it has been put on the website or into the application.** The wording now in the application is the starter text from the build, held in *Debt Consolidation Settings → Disclaimers and consent* and in the draft pages. It stays unpublished until LA-02 is approved.

Related approvals: LA-01, LA-02, LA-03, BV-18, OP-01 (retention), HV-00 (`21-…`).

## 0. How the service works, as built (facts for Legal)

| Aspect | As built |
| --- | --- |
| Who the customer deals with | FynnEdge Advisory (OPC) Pvt Ltd: advisory and facilitation. The starter disclaimer says FynnEdge is not a bank or NBFC and does not sanction, approve or disburse loans, set rates or issue NOCs. |
| What the customer gets from FynnEdge | Calculators; an assessment of total cost, foreclosure charges, affordability and a suitability score; an advisor's review; help to apply to lenders; tracking of the old loans' closure and NOCs |
| Lenders | Lenders and offers come from FynnEdge's existing lender directory. A lender is suggested to the customer only after an **advisor shortlists it**. Before any data goes to that lender, the customer must give a **separate consent for that lender**. |
| Lender decision | Recorded by staff from the lender's communication. FynnEdge never shows a lender decision it has not received. |
| Money flows | FynnEdge records **how old loans were paid** (lender direct, customer, other) and the proof. FynnEdge does not receive or route any loan money in the system. |
| Fees to borrowers | **No borrower fee is charged or collected in the system.** The pages describe the assessment as "free". |
| Data collected | Identity and contact details; income or business figures; each existing loan and card; documents (statements, letters, sanction letters, NOCs); consents with their text, version, time, IP and method |
| Storage | Encrypted at rest; India-hosted (**to confirm**, production host); staff access by role; every download is logged |
| Retention as built | Abandoned drafts withdrawn after 180 days (data kept, see `26-…` M3); anonymous calculator records deleted after 90 days; **submitted cases kept with no automatic deletion until Legal decides (OD8)** |
| AI / OCR | None. Calculations are deterministic and versioned. A read-only API for a future analysis service requires the customer's separate consent ("external analysis"). **No screen collects that consent yet**, so the API cannot return any case. |

## A. Regulatory position under the RBI Digital Lending Directions — LA-01

**Question for Legal.** For loans that customers obtain through FynnEdge's debt consolidation service, is FynnEdge acting as a Lending Service Provider (LSP) of the partner banks and NBFCs within the meaning of the RBI's digital lending framework currently in force? If yes, which obligations apply?

**Points engineering cannot decide, for Legal to confirm:**
1. Whether the regulated entities (REs) have declared FynnEdge as their LSP, and whether any DLA (digital lending app or platform) of FynnEdge has to be reported by them.
2. If FynnEdge works with **several** lenders: the rules on showing offers from all matching lenders without bias, and on showing each lender's name, the APR and a link to its Key Fact Statement (KFS). **Today the system shows no offers and no APR to customers.** It shows only an advisor's suggestion of one or more lenders, after a review. Legal should say whether this display must change (opened question OD9).
3. That disbursal and repayment flow only between the borrower's account and the RE. In the system, FynnEdge only **records** closure payments; it receives none.
4. Grievance redressal: whether an LSP nodal grievance officer must be named on the website and in the app, and the escalation path to the RE.

**Engineering note.** If Legal decides that a multi-lender display is required, the portal's lender section would need a change before go-live. That is an engineering task of about 2–3 days once the rules are known.

## B. Operating model and contracts — LA-01, LP-01

**Question for Legal and the Owner.** Under which arrangement does FynnEdge work with each lender: LSP, DSA, referral partner, or another? The answer must rest on the actual signed agreements.

**Needed:**
- a list of signed agreements per lender that cover debt consolidation or balance transfer (`lender-policy/LP-C` P-03);
- for each one, whether it permits FynnEdge to share customer data and documents, and on what terms.

## C. Disclosures and customer consent — LA-02

Consents captured today, each stored with its text, version, time, IP and method:

| Purpose | When | Starter text (in settings now) |
| --- | --- | --- |
| Assessment | Enquiry / application | "I authorise FynnEdge Advisory (OPC) Pvt Ltd to store and assess the information I have provided about my income and existing loans in order to advise me on debt consolidation. I confirm the information is true to the best of my knowledge." |
| Contact | Enquiry / application | "I agree that FynnEdge may contact me by call, SMS, WhatsApp or email about this request, even if my number is registered on the DND/NCPR list." |
| Lender sharing (per lender) | Portal, after the advisor shortlists the lender | "I authorise FynnEdge Advisory (OPC) Pvt Ltd to share my application details and documents with [lender] for the purpose of applying for a loan to consolidate my existing debts. I understand that [lender] alone decides whether to approve the loan." |
| External analysis | **Not collected anywhere yet** (future FynnAI use). The read-only API answers only when this consent exists, so today it returns nothing. | "I agree that FynnEdge may let its analysis service read my validated case data to explain my assessment. It cannot change my figures or decide on any loan." |

**Questions for Legal:**
- Do these texts meet the DPDP Act 2023 and the DPDP Rules for a notice and consent? That covers itemised purposes, the right to withdraw, how to complain, and the Data Protection Board.
- Is the **DND/NCPR wording** acceptable under the TRAI rules (TCCCPR), or must consent be registered through the DLT consent framework?
- Should Hindi versions be approved texts, not translations?

**DRAFT — SUBJECT TO LEGAL APPROVAL: notice shown above the consents**
> We collect your name, contact details, income and details of your existing loans and cards, and any documents you upload, to assess whether consolidating your debts makes financial sense for you and, if you choose, to help you apply to a lender. We use each item only for the purposes you agree to below. You can withdraw any consent at any time from your account, or by writing to [grievance officer, email]. Withdrawing does not affect what was done before you withdrew. We keep your information for [period decided by Legal] and then delete or anonymise it. If you are unhappy with how we handle your data, you can contact our Grievance Officer [name, contact] and, after that, the Data Protection Board of India.

**DRAFT — SUBJECT TO LEGAL APPROVAL: contact consent**
> I agree that FynnEdge may call me, or send me SMS, WhatsApp or email messages, about this debt consolidation request. I can withdraw this consent at any time.

## D. Sharing customer data with banks and NBFCs — LA-02, LP-01

As built:
- Sharing needs a separate, per-lender consent from the customer.
- The consent can be withdrawn from the portal. The team is alerted, and must stop sharing for that purpose (`25-…` SOP-08).
- Nothing is sent to lenders automatically. Staff prepare each lender application.

**Questions for Legal:**
1. Is the per-lender consent enough, or does the lender agreement require more, for example consent to a bureau pull by the lender?
2. What may be shared after consent is withdrawn? For example, information already submitted that the lender keeps under its own legal duties.

## E. Communication with borrowers and the lender's involvement — LA-01, LA-02

As built:
- Customer emails carry the case reference, a title and a message, with no figures and no documents.
- Lender decisions are shown as their status only.
- The website says the lender decides.

**Question for Legal.** Must any communication carry the lender's name and contact details, or a reference to the lender's KFS?

## F and G. Fees: collection, disclosure, and whether borrowers pay any FynnEdge fee — LA-03 (Legal + Owner)

**Facts:**
- the system has no fee field, no payment link and no fee wording;
- the pages say the assessment is "free, no-obligation";
- the Hindi copy says the same.

**Decision required:**
- Does FynnEdge charge borrowers **any** fee for debt consolidation, whether for the assessment, application help or closure support?
- If yes, how much, when, how is it disclosed, and is it permitted under the applicable RBI framework and the lender agreements?

**If any fee is charged:** the "free" statements on the hub, the assessment page and the SEO descriptions must change before go-live, a fee disclosure must be added (see the draft below), and a refund policy is needed (H).

**DRAFT — SUBJECT TO LEGAL APPROVAL: if no borrower fee**
> FynnEdge does not charge you any fee for the debt consolidation assessment or for helping you apply. Lenders charge their own processing fees and other charges, which are shown in their offer and Key Fact Statement.

**DRAFT — SUBJECT TO LEGAL APPROVAL: if a fee is charged**
> FynnEdge charges a fee of [amount/basis] for [service], payable [when], only after you agree to it in writing. This fee is separate from any fee the lender charges. [Refund terms.]

## H. Refund and cancellation policy

**Applies only if a borrower fee is charged (F/G).** The site has no refund or cancellation page today.

**DRAFT — SUBJECT TO LEGAL APPROVAL**
> You can cancel your request at any time before [event]. If you cancel before [event], we refund [amount] within [days] to the original payment method. After [event], [terms].

## I. Retention of applications and documents — OP-01 (OD8), with Legal and the Owner

| Data | Built behaviour | Decision needed |
| --- | --- | --- |
| Enquiries and drafts never submitted | Withdrawn after 180 days (data kept, soft delete) | Keep, anonymise or delete after N days (`26-…` §5) |
| Submitted cases and documents | No automatic deletion | Retention period (lender agreements and record-keeping laws may set a minimum) |
| Consent records | Kept | Keep for N years as evidence of consent |
| Anonymous calculator records | Deleted after 90 days | Confirm |
| Historical validation pack | Not collected | HV-00 (`21-…`) |
| Erasure requests (DPDP) | No erasure function yet (`26-…` M3) | Scope of erasure; engineering then builds it (about 1 day) |

## J. Privacy policy and data-processing obligations — LA-02 (DPO)

The privacy policy, terms, disclaimer and grievance pages **do not mention debt consolidation** (checked on the seeded local copy). Production may differ; **to confirm**.

They need to cover:
- the DC data categories (income, debts, documents);
- the purposes;
- sharing with lenders after consent;
- retention;
- rights and withdrawal;
- the optional external-analysis access.

Processors to list, **to confirm** with Operations:
- hosting provider;
- email provider;
- SMS provider (Areach);
- backup storage.

**DRAFT — SUBJECT TO LEGAL APPROVAL: privacy policy section "Debt consolidation"**
> If you use our debt consolidation service, we process: details of your existing loans and credit cards (lender, outstanding amount, rate, EMI, tenure, overdue amounts, foreclosure charges); your income or, for a business, its revenue and expenses; documents you upload, such as loan statements, foreclosure letters, sanction letters and NOCs; and your consents. We use them to calculate and explain whether consolidation would reduce your total cost, to let an advisor review your case and, only with your separate consent for each lender, to share your application with that lender. Documents are encrypted when stored and are seen only by authorised staff, and every access is logged. We keep this information for [period] after your case closes, unless the law or a lender agreement requires longer, and then delete or anonymise it. You can ask to see, correct or erase your information, or withdraw consent, by writing to [contact].

## K. Consent and disclaimer wording — LA-02 with BV-18 (Compliance)

Every text below is held in settings (English and Hindi) and **must be approved before go-live**:
- the general disclaimer (role);
- the calculator disclaimer;
- the suitability disclaimer;
- the four consents;
- the warning list (BV-18, `06-financial-engine.md` §4);
- the Savings Calculator's assumption note.

The current starter texts are listed in §0 and §C.

**DRAFT — SUBJECT TO LEGAL APPROVAL: general disclaimer (refined)**
> FynnEdge Advisory (OPC) Pvt Ltd provides loan advisory and facilitation services. It is not a bank or a non-banking financial company and does not lend. Loans are offered, approved, priced and disbursed only by the bank or NBFC concerned, under its own policies. Figures shown by FynnEdge are estimates based on the information you provide and on stated assumptions; they are not an offer, an approval or a guarantee of savings.

**DRAFT — SUBJECT TO LEGAL APPROVAL: savings statements**
> "Estimated saving" means the difference between what you would pay in total on your current loans from today and what you would pay in total on the new loan, including foreclosure charges, the lender's fees and GST. It is an estimate. A lower EMI on its own does not mean you save money.

## L. Website statements that could imply guaranteed approval, guaranteed savings or direct lending

**Scan performed** (26 Nov 2026):
- all Blade templates, `lang/` files and seed data, for: guarantee(d), assured/instant approval, 100% approval, pre-approved, lowest rate, "we lend/disburse/sanction/approve", "our loan(s)";
- the local CMS content (pages, FAQs, loan products), for the same phrases;
- every DC page and both calculators, by reading them.

| Finding | Where | Assessment for Legal |
| --- | --- | --- |
| Statements **denying** a guarantee (correct): "FynnEdge does not guarantee approval, disbursal or any particular rate", "nothing on FynnEdge is a guarantee", "not a guarantee of approval" | Lender comparison table, credit score pages, terms, disclaimer | Supportive; no action |
| Savings shown only when the engine finds a net benefit; otherwise "Extra cost" / "Costs ₹X more"; "A lower EMI does not by itself mean you save money" | Savings Calculator, Debt Analyser, assessment | Supportive (`23-…` §3.3) |
| **"Free, no-obligation assessment"** (hub ticks, assessment SEO description) | DC pages | Depends on **LA-03**. Remove "free" if any fee is charged. |
| **"…true net savings"** | Debt Analyser SEO description | Legal to consider whether "true" overstates certainty. Suggest "net saving (after charges)". |
| **"Options from partner banks and NBFCs"** | Hub ticks | True only once partner policies and agreements exist (LP-01, P-03) |
| **"Help closing old loans and collecting NOCs"** | Hub ticks | The disclaimer says FynnEdge does not issue NOCs. Legal to confirm the wording makes clear the lender issues the NOC. |
| "Lowest rate" badge | Lender comparison table (existing site) | Compares lenders' own published rates and never ranks third-party figures. Factual label. Legal may want "Lowest published rate". |
| "Pre-approved …" | Lender product notes (rate-audit data and product names from lenders' sites) | Lender product names; not a FynnEdge claim |
| "We offer competitive interest rates on our loan facilities…" | Rate-audit **evidence** data (a quotation from a lender's website) | Internal evidence record; not shown as FynnEdge copy |
| No statement found that FynnEdge lends, sanctions, approves or disburses | — | — |

**Limitation.** The scan covers the code and the **local** copy of CMS content. Production CMS content may have been edited since. Repeat the scan on production content before go-live (`34-…` G-07).

## Consistency of the website and application with the legal position

These are checked once Legal decides. Engineering will then:
1. Put the approved texts into Debt Consolidation Settings (English and Hindi). They are not code; the consent version must be raised to "2" so new consents record the approved text.
2. Change the pages flagged in L.
3. Change the portal lender display if A.2 requires it.
4. Implement erasure per I.
5. Re-run the wording tests and capture screenshots for Compliance (BV-18).

## Status for the final report

| Item | Owner | Status |
| --- | --- | --- |
| A, B (LA-01) | Legal / Compliance | PENDING APPROVAL |
| C, D, E, J, K (LA-02) | Legal / DPO / Compliance | PENDING APPROVAL |
| F, G, H (LA-03) | Legal + Owner | **Owner decided 26 Nov 2026: no fee to borrowers.** Legal still to confirm the position and the disclosure wording (DRAFT "if no borrower fee"). H (refund policy) is not needed while no fee is charged. | 
| I (OD8) | Legal + Operations + Owner | Erasure method decided by the Owner 26 Nov 2026: anonymise; built and tested. **Retention periods** (drafts 180 days, submitted cases) still PENDING APPROVAL. |
| L | Legal (wording decisions); engineering scan COMPLETE | PENDING APPROVAL |

**Legal and regulatory approval: PENDING APPROVAL.** Nothing here is approved.
