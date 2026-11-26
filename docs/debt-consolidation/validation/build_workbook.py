"""
Builds DC-financial-validation-workbook.xlsx from cases.json, engine-results.json,
reference-results.json and comparison.json. Needs openpyxl.

    <python-with-openpyxl> docs/debt-consolidation/validation/build_workbook.py
"""
import json
import os

from openpyxl import Workbook
from openpyxl.comments import Comment
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

HERE = os.path.dirname(os.path.abspath(__file__))
cases = json.load(open(os.path.join(HERE, 'cases.json')))
engine = json.load(open(os.path.join(HERE, 'engine-results.json')))
reference = json.load(open(os.path.join(HERE, 'reference-results.json')))
comparison = json.load(open(os.path.join(HERE, 'comparison.json')))

FONT = 'Arial'
BLUE = Font(name=FONT, color='0000FF')
BLACK = Font(name=FONT)
BOLD = Font(name=FONT, bold=True)
TITLE = Font(name=FONT, bold=True, size=14)
HEAD = Font(name=FONT, bold=True, color='FFFFFF')
HEAD_FILL = PatternFill('solid', fgColor='1F3864')
YELLOW = PatternFill('solid', fgColor='FFFF00')
GREY = PatternFill('solid', fgColor='F2F2F2')
THIN = Border(bottom=Side(style='thin', color='BFBFBF'))
INR = '#,##0.00;(#,##0.00);"-"'
INR0 = '#,##0;(#,##0);"-"'
PCT = '0.00%'
WRAP = Alignment(wrap_text=True, vertical='top')

wb = Workbook()


def sheet(title, widths):
    ws = wb.create_sheet(title)
    for i, w in enumerate(widths, start=1):
        ws.column_dimensions[get_column_letter(i)].width = w
    return ws


def header(ws, row, labels):
    for i, label in enumerate(labels, start=1):
        c = ws.cell(row=row, column=i, value=label)
        c.font, c.fill, c.alignment = HEAD, HEAD_FILL, Alignment(wrap_text=True, vertical='center')
    ws.freeze_panes = ws.cell(row=row + 1, column=1)


def put(ws, ref, value, font=BLACK, fmt=None, fill=None, note=None):
    c = ws[ref]
    c.value = value
    c.font = font
    if fmt:
        c.number_format = fmt
    if fill:
        c.fill = fill
    if note:
        c.comment = Comment(note, 'FynnEdge validation')
    return c


def scen(case_key, scenario_key):
    return next(s for s in engine[case_key]['scenarios'] if s['scenario']['key'] == scenario_key)


# ---------------------------------------------------------------- Read me
ws = wb.active
ws.title = 'Read me'
ws.column_dimensions['A'].width = 120
lines = [
    ('Debt Consolidation — financial validation workbook', TITLE),
    ('Engine formula version ' + engine['V01']['formula_version'] + ' · cases as of 25 Nov 2026 · prepared for business sign-off before real applications are accepted.', BOLD),
    ('', BLACK),
    ('What this is', BOLD),
    ('Ten representative cases (V01–V10) were run through the production calculation engine and through an independent reference calculation written separately from the published specification (docs/debt-consolidation/06-financial-engine.md), using exact decimal arithmetic. Every figure is compared on the "All figures" sheet.', BLACK),
    ('The cases use HYPOTHETICAL customer figures. Loan terms are the indicative defaults shipped in Debt Consolidation Settings (OD7), which are themselves awaiting business approval. Foreclosure charge percentages are test inputs, NOT any lender\'s policy. No lender policy was invented.', BLACK),
    ('', BLACK),
    ('What agreement proves — and what it does not', BOLD),
    ('Agreement shows the engine computes exactly what the specification says. It does NOT prove the specification matches how your lenders and customers actually calculate. That is the purpose of the "Business validation" sheet and of the historical case check (BV-13): compare these methods with real lender schedules, foreclosure letters and sanction letters.', BLACK),
    ('', BLACK),
    ('Sheets', BOLD),
    ('Business validation — every calculation and assumption that needs a named business approver. Fill in the yellow cells.', BLACK),
    ('Results summary — one row per case and option: outcome, key figures, warnings, and the engine-versus-reference difference.', BLACK),
    ('Case inputs — the debts, finances and options used.', BLACK),
    ('All figures — every compared figure (engine, independent reference, difference, result).', BLACK),
    ('Check A … Check D — worked examples with live Excel formulas, so a reviewer can recompute an amortisation schedule, a foreclosure amount, the new loan amount and fees, the savings comparison, affordability and the suitability score by hand. Blue cells are inputs you may change; black cells are formulas; the "Engine" column holds the production engine\'s output for the original inputs.', BLACK),
    ('', BLACK),
    ('Tolerance', BOLD),
    ('Money must agree to the paisa. Percentages and ratios to 0.01. Month counts exactly. The only exception is the flat-rate loan in V10, whose implied rate the engine solves in floating point; there the tolerance is Rs 1.', BLACK),
    ('', BLACK),
    ('Reproduce', BOLD),
    ('php docs/debt-consolidation/validation/engine-harness.php  →  python3 -I docs/debt-consolidation/validation/reference.py  →  python3 -I docs/debt-consolidation/validation/compare.py', BLACK),
]
for i, (text, font) in enumerate(lines, start=1):
    c = ws.cell(row=i, column=1, value=text)
    c.font, c.alignment = font, Alignment(wrap_text=True, vertical='top')

# ---------------------------------------------------------------- Business validation
ws = sheet('Business validation', [8, 22, 46, 40, 30, 42, 22, 12, 14, 30])
put(ws, 'A1', 'Calculations and assumptions that need business approval before real applications', TITLE)
put(ws, 'A2', 'Status, Approved by, Date and Notes (yellow) are for the approver. Example of a completed row: "Approved · A. Sharma (Head of Credit) · 02-Dec-2026 · checked against 3 HDFC and 2 Bajaj statements".', BLACK)
header(ws, 4, ['ID', 'Area', 'Exact calculation / rule in the engine', 'Current value (default)', 'Where it is set', 'How to validate', 'Approver', 'Status', 'Date', 'Approved by / notes'])
items = [
    ('BV-01', 'EMI and per-month rounding', 'EMI = P·i·(1+i)^n / ((1+i)^n − 1), i = annual rate ÷ 1200, rounded half-up to the paisa; each month interest = balance × i rounded to the paisa; the last instalment clears the balance exactly.', 'Fixed formula (version 1.0.0)', 'Code (formula version)', 'Recompute "Check A"; compare with 3 real lender repayment schedules (total interest within Rs 1).', 'Credit / Finance'),
    ('BV-02', 'Existing loan: EMI and months known', 'The reducing rate implied by EMI × months is solved. If it is within 1.5 points of the stated rate, the stated rate drives the schedule; otherwise the implied rate is used and "Rate mismatch" is flagged (typical of flat-rate quotes).', 'Tolerance 1.5 points', 'config/debt_consolidation.php (code review)', 'Check V10 (flat 10%) against a real flat-rate NBFC statement.', 'Credit'),
    ('BV-03', 'Existing loan: only EMI, only months, or neither', 'EMI only: paid until cleared. Months only: EMI derived from rate and months. Neither: the default tenure is assumed and "Repayment plan assumed" is flagged.', 'Default tenure 60 months', 'config/debt_consolidation.php', 'Confirm the assumed tenure is acceptable for advice purposes.', 'Credit'),
    ('BV-04', 'Credit cards', 'With a planned payment: paid until cleared. Without: amortised over each option\'s tenure (like-for-like) and flagged; FOIR uses 5% of the balance as the monthly obligation.', '5% minimum (OD3)', 'Debt Consolidation Settings', 'Compare with 2 card statements (minimum due %); confirm the like-for-like assumption.', 'Credit'),
    ('BV-05', 'Interest-only debts (overdraft, cash credit, gold bullet, invoice, working capital)', 'Interest each month on the full balance; the principal is repaid in the last month.', 'By debt type', 'Code (LiabilityType)', 'Check against an overdraft statement and a gold-loan bullet schedule.', 'Credit'),
    ('BV-06', 'Foreclosure amount (estimate)', 'principal closed + overdue + pending instalment + accrued interest (full closure only) + charge + GST on charge + other charges; charge = principal closed × charge % + fixed fee.', 'GST 18% (OD1)', 'Settings (GST); charge % per debt as entered', 'Recompute "Check B"; compare with 5 real foreclosure letters (BV-13).', 'Finance / Legal (GST)'),
    ('BV-07', 'Part-prepayment', 'Charged only on the amount paid down; overdue and pending dues paid; no accrued interest; the loan keeps its EMI and finishes sooner.', 'Fixed rule', 'Code', 'Confirm with 2 lenders\' part-prepayment practice (some reduce the EMI instead).', 'Credit'),
    ('BV-08', 'Lender quotation', 'For a full closure a quotation replaces the estimate; counted as verified only if staff verified it and it is within its validity date.', 'Fixed rule', 'Admin (Foreclosure quotations)', 'Confirm the operating procedure for verifying quotations.', 'Operations'),
    ('BV-09', 'New loan amount and processing fee', 'Fee deducted from payout: smallest whole-rupee loan L whose payout L − fee − GST on fee covers the amount to finance (≈ (R + F·(1+t)) ÷ (1 − p·(1+t)), rounded up). Fee paid separately: L = amount to finance rounded up. A lower sanction creates a shortfall paid upfront.', 'GST 18% on fee; fee % per option', 'Settings (standard options, OD7) / scenario', 'Recompute "Check C"; compare with 3 real sanction letters (fee, GST, net disbursal).', 'Credit / Finance'),
    ('BV-10', 'Savings comparison (net financial benefit)', 'Existing total = overdue + pending dues now + all remaining payments of the debts in scope. Proposed total = upfront cash + all new-loan payments + payments left on partially closed debts. Net benefit = existing − proposed. Principal counted once on each side; debts kept are on neither side.', 'Fixed rule', 'Code', 'Recompute "Check C"; agree the definition is how FynnEdge wants to present savings.', 'Head of Advisory'),
    ('BV-11', 'Break-even', 'Cost break-even: first month in which cumulative interest saved reaches all consolidation costs (charges, GST, fees, other costs), plus whether this still holds when both plans end. Cash break-even (staff screen only): first month (0 = immediately) in which cumulative cash paid under the proposal is no more than under the existing plan.', 'Fixed rule', 'Code', 'Confirm both definitions and the staff label "Month 0" for "from day one".', 'Head of Advisory'),
    ('BV-12', 'Affordability', 'FOIR % = (all monthly debt obligations + other fixed obligations) ÷ (net income + other income) × 100. DSCR = (average monthly revenue − operating expenses) ÷ monthly debt service.', 'FOIR bands ≤40 / ≤50 / ≤65; DSCR ≥1.75 / ≥1.4 / ≥1.2 (OD2)', 'Scoring configuration (versioned)', 'Recompute "Check D"; compare bands with partner lenders\' FOIR/DSCR norms.', 'Credit'),
    ('BV-13', 'Historical case validation (parallel run)', 'Run 10–20 anonymised past cases with known foreclosure letters, sanction letters and repayment schedules through the engine; record differences above Rs 1 and their cause.', 'Not yet done', 'Admin (create the case, Recalculate)', 'Results to be attached to this workbook.', 'Head of Advisory + Credit'),
    ('BV-14', 'Suitability score', 'Weights 30 / 25 / 20 / 15 / 10. Each dimension scored 0–100 on a straight line between its thresholds (benefit 0–10%; FOIR 70–40% or DSCR 1.0–1.75; rate gain 0–8 points; costs 6–1% of the loan; extra months 36–0). Total = sum of the weighted parts (each rounded to 0.01). Bands ≥70 potentially suitable, ≥50 borderline, else low. Any option with no net benefit is "Not financially beneficial" whatever its score.', 'Version 1 (default thresholds)', 'Scoring configuration (maker-checker)', 'Recompute "Check D"; review V01–V10 bands for reasonableness.', 'Head of Advisory + Compliance'),
    ('BV-15', 'Not consolidatable now', 'Kept out of every option and flagged: non-performing, settled, written off, disputed, ≥90 days past due, or within a foreclosure lock-in.', '90 days past due', 'config/debt_consolidation.php', 'Confirm against partner lenders\' acceptance rules.', 'Credit'),
    ('BV-16', 'Which loans each route can take over', 'Route → debt-type matrix (e.g. home loans only by balance transfer; vehicle loans by personal loan, LAP or refinance). Lender policies narrow it further.', 'config route_liability_types', 'config/debt_consolidation.php', 'Review the matrix with Credit; enter real lender policies (OD4).', 'Credit'),
    ('BV-17', 'Indicative comparison options', 'Standard options shown before a lender quotes: PL 14%/60 m/2%, LAP 10.5%/120 m/1%, UBL 17%/48 m/2%, SBL 11.5%/84 m/1%.', 'OD7 defaults', 'Debt Consolidation Settings', 'Replace with current market ranges from partner lenders.', 'Credit'),
    ('BV-18', 'Warnings never hidden by a lower EMI', 'Higher total cost, lower EMI but higher cost, longer tenure, rate not lower, unsecured debt becoming secured, possible tax-benefit loss (home/education loans), shortfall, extra borrowing.', 'Fixed rule', 'Code', 'Confirm the wording shown to customers and staff.', 'Compliance'),
]
for r, item in enumerate(items, start=5):
    for col, value in enumerate(item, start=1):
        c = ws.cell(row=r, column=col, value=value)
        c.font, c.alignment = BLACK, WRAP
    ws.cell(row=r, column=8, value='Pending').font = BLACK
    for col in (8, 9, 10):
        ws.cell(row=r, column=col).fill = YELLOW
        ws.cell(row=r, column=col).alignment = WRAP
last_item = 4 + len(items)
put(ws, f'B{last_item + 2}', 'Items approved', BOLD)
put(ws, f'C{last_item + 2}', f'=COUNTIF(H5:H{last_item},"Approved")&" of "&COUNTA(A5:A{last_item})', BLACK)

# ---------------------------------------------------------------- Case inputs
ws = sheet('Case inputs', [7, 12, 26, 15, 14, 9, 11, 9, 11, 9, 10, 10, 10, 10, 10, 10, 12, 9, 12, 14])
put(ws, 'A1', 'Case inputs (hypothetical customers; loan terms are the shipped indicative defaults, foreclosure % are test inputs)', TITLE)
header(ws, 3, ['Case', 'Debt', 'Type', 'Lender', 'Outstanding (Rs)', 'Rate %', 'EMI / payment (Rs)', 'Months left', 'Status', 'DPD', 'Overdue (Rs)', 'Pending EMI (Rs)', 'Accrued int. (Rs)', 'Charge %', 'Fixed charge (Rs)', 'Other charges (Rs)', 'Quotation (Rs)', 'Mode', 'Part amount (Rs)', 'Lock-in until'])
row = 4
for c in cases:
    for l in c['liabilities']:
        values = [c['key'], l['key'], l['type'], l.get('lender_name'), l['outstanding_principal'], l.get('annual_interest_rate'), l.get('monthly_payment'), l.get('remaining_months'), l.get('account_status', 'regular'), l.get('days_past_due', 0), l.get('overdue_amount', 0), l.get('pending_instalment', 0), l.get('accrued_interest', 0), l.get('foreclosure_charge_percent', 0), l.get('foreclosure_charge_fixed', 0), l.get('other_charges', 0), l.get('quoted_closure_amount'), l.get('mode', 'full'), l.get('partial_amount'), l.get('lock_in_ends_on')]
        for col, v in enumerate(values, start=1):
            cell = ws.cell(row=row, column=col, value=v)
            cell.font = BLUE if col >= 5 else BLACK
            if col in (5, 7, 11, 12, 13, 15, 16, 17, 19) and v is not None:
                cell.number_format = INR0
        row += 1
row += 1
put(ws, f'A{row}', 'Options compared', BOLD)
row += 1
for col, label in enumerate(['Case', 'Option', 'Description', 'Route', 'Rate %', 'Months', 'Fee %', 'Fixed fee (Rs)', 'Fee deducted', 'Closure costs financed', 'Sanction override (Rs)'], start=1):
    cell = ws.cell(row=row, column=col, value=label)
    cell.font, cell.fill = HEAD, HEAD_FILL
row += 1
for c in cases:
    for s in c['scenarios']:
        values = [c['key'], s['key'], s['label'], s.get('route'), s['annual_interest_rate'], s['tenure_months'], s.get('processing_fee_percent', 0), s.get('processing_fee_fixed', 0), 'Yes' if s.get('fees_deducted_from_disbursal', True) else 'No', 'Yes' if s.get('closure_costs_financed', True) else 'No', s.get('loan_amount_override')]
        for col, v in enumerate(values, start=1):
            ws.cell(row=row, column=col, value=v).font = BLUE if col >= 5 else BLACK
        row += 1
row += 1
put(ws, f'A{row}', 'Finances', BOLD)
row += 1
for c in cases:
    f = c.get('individual_finances') or c.get('business_finances')
    ws.cell(row=row, column=1, value=c['key']).font = BLACK
    ws.cell(row=row, column=2, value=c['applicant_type']).font = BLACK
    ws.cell(row=row, column=3, value=', '.join(f'{k.replace("_", " ")} {v:,}' for k, v in f.items())).font = BLUE
    row += 1

# ---------------------------------------------------------------- Results summary
ws = sheet('Results summary', [6, 40, 14, 16, 14, 16, 16, 16, 10, 14, 14, 12, 16, 10, 26, 46, 16, 14, 12])
put(ws, 'A1', 'Results per case and option (production engine output; independent reference alongside)', TITLE)
header(ws, 3, ['Case', 'Option', 'Debts in scope', 'New loan (Rs)', 'New EMI (Rs)', 'Existing total (Rs)', 'Proposed total (Rs)', 'Net benefit (Rs)', 'Benefit %', 'Monthly change (Rs)', 'Costs (Rs)', 'Cost break-even', 'After: FOIR % / DSCR', 'Score', 'Result', 'Warnings', 'Reference net benefit (Rs)', 'Difference (Rs)', 'Check'])
row = 4
labels = {'potentially_suitable': 'Potentially suitable', 'needs_review': 'Borderline — advisor review', 'low_suitability': 'Low suitability', 'not_beneficial': 'Not financially beneficial'}
for c in cases:
    for s in c['scenarios']:
        es = scen(c['key'], s['key'])
        r = es['result']
        rs = reference['cases'][c['key']]['scenarios'][s['key']]
        warnings = sorted({f['flag'].replace('_', ' ') for f in r['flags'] if f['severity'] != 'info'})
        be = 'Not recovered' if r['cost_break_even_month'] is None else ('Month %d' % r['cost_break_even_month']) + ('' if r['cost_recovery_holds'] else ' (not sustained)')
        values = [c['key'], s['label'], ', '.join(r['included_keys']), r['loan_amount'], r['new_emi'], r['existing_total_outflow'], r['proposed_total_outflow'], r['net_financial_benefit'], (r['benefit_percent'] or 0) / 100, r['monthly_cash_flow_difference'], r['total_consolidation_costs'], be, es['affordability']['proposed_value'], es['suitability']['score'], labels[es['suitability']['band']], '; '.join(warnings) or '—', rs['net_financial_benefit']]
        for col, v in enumerate(values, start=1):
            cell = ws.cell(row=row, column=col, value=v)
            cell.font, cell.alignment = BLACK, WRAP
            if col in (4, 5, 6, 7, 8, 10, 11, 17):
                cell.number_format = INR
            if col == 9:
                cell.number_format = PCT
        ws.cell(row=row, column=18, value=f'=H{row}-Q{row}').number_format = INR
        ws.cell(row=row, column=19, value=f'=IF(ABS(R{row})<=IF(A{row}="V10",1,0.005),"Match","DIFFERENT")')
        row += 1
put(ws, f'A{row + 1}', 'Negative net benefit = the option costs more in total than keeping the debts. "Monthly change" is existing minus proposed monthly outflow: a positive figure with a negative net benefit is exactly the "lower EMI, higher total cost" case the engine refuses to present as a saving.', BLACK)
ws.merge_cells(start_row=row + 1, start_column=1, end_row=row + 1, end_column=16)
ws.row_dimensions[row + 1].height = 30
ws.cell(row=row + 1, column=1).alignment = WRAP

# ---------------------------------------------------------------- All figures
ws = sheet('All figures', [6, 22, 28, 18, 18, 14, 12])
put(ws, 'A1', 'Every compared figure — production engine vs independent reference', TITLE)
header(ws, 3, ['Case', 'Item', 'Figure', 'Engine', 'Reference', 'Difference', 'Result'])
row = 4
for item in comparison:
    e, r = item['engine'], item['reference']
    numeric = isinstance(e, (int, float)) and not isinstance(e, bool) and isinstance(r, (int, float)) and not isinstance(r, bool)
    values = [item['case'], item['item'], item['field'], e if (numeric or e is None or isinstance(e, bool)) else json.dumps(e), r if (numeric or r is None or isinstance(r, bool)) else json.dumps(r)]
    for col, v in enumerate(values, start=1):
        ws.cell(row=row, column=col, value=v).font = BLACK
    if numeric:
        ws.cell(row=row, column=6, value=f'=D{row}-E{row}').number_format = '0.00;-0.00;"-"'
        tol = '1' if item['case'] == 'V10' and 'flat' in item['item'] else ('0.011' if any(k in item['field'] for k in ('percent', 'rate', 'points', 'affordability', 'score', 'foir', 'dscr')) else '0.005')
        ws.cell(row=row, column=7, value=f'=IF(ABS(F{row})<={tol},"Match","DIFFERENT")')
    else:
        ws.cell(row=row, column=7, value='Match' if item['match'] else 'DIFFERENT')
    row += 1
summary_row = 2
put(ws, f'D{summary_row}', f'=COUNTIF(G4:G{row - 1},"Match")&" of "&COUNTA(C4:C{row - 1})&" figures match"', BOLD)

# ---------------------------------------------------------------- Check A: amortisation
v01 = engine['V01']
pl_a = v01['liabilities']['pl_a']['projection']
ws = sheet('Check A - schedule', [26, 16, 16, 16, 16, 16])
put(ws, 'A1', 'Check A — repayment schedule of V01 "pl_a" (Bank A personal loan): EMI, monthly interest, total', TITLE)
put(ws, 'A3', 'Outstanding principal (Rs)', BOLD); put(ws, 'B3', 320000, BLUE, INR)
put(ws, 'A4', 'Annual rate', BOLD); put(ws, 'B4', 0.165, BLUE, '0.00%')
put(ws, 'A5', 'Months remaining', BOLD); put(ws, 'B5', 30, BLUE, '0')
put(ws, 'A6', 'EMI (Rs)', BOLD); put(ws, 'B6', '=ROUND(PMT(B4/12,B5,-B3),2)', BLACK, INR, note='Excel ROUND rounds halves away from zero, i.e. half-up for these positive amounts — the engine\'s rule.')
put(ws, 'C2', 'This sheet', BOLD); put(ws, 'D2', 'Engine', BOLD); put(ws, 'E2', 'Difference', BOLD)
put(ws, 'C6', '=B6', BLACK, INR); put(ws, 'D6', pl_a['monthly_obligation'], BLACK, INR, note='Production engine output for the original inputs (engine-results.json).'); put(ws, 'E6', '=C6-D6', BLACK, INR)
put(ws, 'A7', 'Total repayment (Rs)', BOLD); put(ws, 'C7', '=SUM(D11:D40)', BLACK, INR); put(ws, 'D7', pl_a['remaining_repayment'], BLACK, INR); put(ws, 'E7', '=C7-D7', BLACK, INR)
put(ws, 'A8', 'Total interest (Rs)', BOLD); put(ws, 'C8', '=SUM(C11:C40)', BLACK, INR); put(ws, 'D8', pl_a['remaining_interest'], BLACK, INR); put(ws, 'E8', '=C8-D8', BLACK, INR)
header(ws, 10, ['Month', 'Opening balance (Rs)', 'Interest (Rs)', 'Payment (Rs)', 'Closing balance (Rs)'])
ws.freeze_panes = None
for m in range(1, 31):
    r = 10 + m
    ws.cell(row=r, column=1, value=m).font = BLACK
    ws.cell(row=r, column=2, value='=$B$3' if m == 1 else f'=E{r - 1}').number_format = INR
    ws.cell(row=r, column=3, value=f'=ROUND(B{r}*$B$4/12,2)').number_format = INR
    ws.cell(row=r, column=4, value=f'=IF(A{r}=$B$5,B{r}+C{r},$B$6)').number_format = INR
    ws.cell(row=r, column=5, value=f'=ROUND(B{r}+C{r}-D{r},2)').number_format = INR
put(ws, 'A42', 'The last payment clears the balance exactly (it may differ from the EMI by a few paise), as on a lender\'s schedule.', BLACK)

# ---------------------------------------------------------------- Check B: foreclosure
v07 = engine['V07']
fc = v07['liabilities']['pl']['full_closure']
ws = sheet('Check B - foreclosure', [40, 18, 18, 14])
put(ws, 'A1', 'Check B — foreclosure amount of V07 "pl" (overdue personal loan, full closure)', TITLE)
put(ws, 'B2', 'This sheet', BOLD); put(ws, 'C2', 'Engine', BOLD); put(ws, 'D2', 'Difference', BOLD)
inputs = [('Principal closed (Rs)', 250000, INR), ('Overdue amount (Rs)', 8500, INR), ('Pending instalment (Rs)', 12481, INR), ('Accrued interest since last EMI (Rs)', 1850, INR), ('Foreclosure charge %', 0.04, '0.00%'), ('Fixed foreclosure fee (Rs)', 500, INR), ('Other charges, e.g. stamp duty (Rs)', 1200, INR), ('GST on the charge', 0.18, '0.00%')]
for i, (label, value, fmt) in enumerate(inputs, start=3):
    put(ws, f'A{i}', label, BOLD); put(ws, f'B{i}', value, BLUE, fmt)
put(ws, 'B5', 12481, BLUE, INR)
put(ws, 'A12', 'Foreclosure charge = ROUND(principal × %, 2) + fixed (Rs)', BOLD); put(ws, 'B12', '=ROUND(B3*B7,2)+B8', BLACK, INR); put(ws, 'C12', fc['charges'], BLACK, INR); put(ws, 'D12', '=B12-C12', BLACK, INR)
put(ws, 'A13', 'GST on the charge (Rs)', BOLD); put(ws, 'B13', '=ROUND(B12*B10,2)', BLACK, INR); put(ws, 'C13', fc['tax'], BLACK, INR); put(ws, 'D13', '=B13-C13', BLACK, INR)
put(ws, 'A14', 'Closure costs = charge + GST + other (Rs)', BOLD); put(ws, 'B14', '=B12+B13+B9', BLACK, INR); put(ws, 'C14', fc['closure_costs'], BLACK, INR); put(ws, 'D14', '=B14-C14', BLACK, INR)
put(ws, 'A15', 'Total to close the loan (Rs)', BOLD); put(ws, 'B15', '=B3+B4+B5+B6+B14', BLACK, INR); put(ws, 'C15', fc['total'], BLACK, INR); put(ws, 'D15', '=B15-C15', BLACK, INR)
put(ws, 'A17', 'Accrued interest is a due (interest already earned by the lender), not a consolidation cost. It is charged only on a full closure. Compare with the lender\'s own foreclosure letter (BV-06, BV-13).', BLACK)

# ---------------------------------------------------------------- Check C: new loan and savings
s1 = scen('V01', 'pl60')['result']
ws = sheet('Check C - new loan', [46, 18, 18, 14, 3, 8, 16, 14, 14, 16])
put(ws, 'A1', 'Check C — V01 option "Personal loan 14% / 60 m / 2% fee deducted": loan amount, fee, GST, EMI and net benefit', TITLE)
put(ws, 'B2', 'This sheet', BOLD); put(ws, 'C2', 'Engine', BOLD); put(ws, 'D2', 'Difference', BOLD)
closures = [('pl_a closure: 320,000 + 4% charge + GST', '=320000+ROUND(320000*4%,2)+ROUND(ROUND(320000*4%,2)*18%,2)'), ('pl_b closure: 180,000 + (5% + Rs 1,000) + GST', '=180000+(ROUND(180000*5%,2)+1000)+ROUND((ROUND(180000*5%,2)+1000)*18%,2)'), ('pl_c closure: 95,000 + 3% charge + GST', '=95000+ROUND(95000*3%,2)+ROUND(ROUND(95000*3%,2)*18%,2)')]
for i, (label, formula) in enumerate(closures, start=3):
    put(ws, f'A{i}', label, BOLD); put(ws, f'B{i}', formula, BLACK, INR)
put(ws, 'A6', 'Amount to finance R (closure costs financed) (Rs)', BOLD); put(ws, 'B6', '=SUM(B3:B5)', BLACK, INR); put(ws, 'C6', s1['closure_requirement'], BLACK, INR); put(ws, 'D6', '=B6-C6', BLACK, INR)
put(ws, 'A7', 'Processing fee rate p', BOLD); put(ws, 'B7', 0.02, BLUE, '0.00%')
put(ws, 'A8', 'Fixed fee F (Rs)', BOLD); put(ws, 'B8', 0, BLUE, INR)
put(ws, 'A9', 'GST t', BOLD); put(ws, 'B9', 0.18, BLUE, '0.00%')
put(ws, 'A10', 'New loan rate', BOLD); put(ws, 'B10', 0.14, BLUE, '0.00%')
put(ws, 'A11', 'New loan months', BOLD); put(ws, 'B11', 60, BLUE, '0')
put(ws, 'A12', 'Gross-up (R + F·(1+t)) ÷ (1 − p·(1+t)), rounded up (Rs)', BOLD); put(ws, 'B12', '=ROUNDUP((B6+B8*(1+B9))/(1-B7*(1+B9)),0)', BLACK, INR0)
put(ws, 'A13', 'Payout of one rupee less (Rs)', BOLD); put(ws, 'B13', '=(B12-1)-(ROUND((B12-1)*B7,2)+B8)-ROUND((ROUND((B12-1)*B7,2)+B8)*B9,2)', BLACK, INR)
put(ws, 'A14', 'Payout of the gross-up (Rs)', BOLD); put(ws, 'B14', '=B12-(ROUND(B12*B7,2)+B8)-ROUND((ROUND(B12*B7,2)+B8)*B9,2)', BLACK, INR)
put(ws, 'A15', 'Loan amount L = smallest whole rupee whose payout covers R (Rs)', BOLD); put(ws, 'B15', '=IF(B13>=B6,B12-1,IF(B14>=B6,B12,B12+1))', BLACK, INR0, note='Fee and GST are each rounded to the paisa, so the payout of the rounded-up amount can land a paisa either side of R. The loan is the smallest whole-rupee amount whose actual payout covers R.'); put(ws, 'C15', s1['loan_amount'], BLACK, INR0); put(ws, 'D15', '=B15-C15', BLACK, INR)
put(ws, 'A16', 'Processing fee = ROUND(L × p, 2) + F (Rs)', BOLD); put(ws, 'B16', '=ROUND(B15*B7,2)+B8', BLACK, INR); put(ws, 'C16', s1['processing_fee'], BLACK, INR); put(ws, 'D16', '=B16-C16', BLACK, INR)
put(ws, 'A17', 'GST on fee (Rs)', BOLD); put(ws, 'B17', '=ROUND(B16*B9,2)', BLACK, INR); put(ws, 'C17', s1['processing_fee_tax'], BLACK, INR); put(ws, 'D17', '=B17-C17', BLACK, INR)
put(ws, 'A18', 'Net disbursal (Rs)', BOLD); put(ws, 'B18', '=B15-B16-B17', BLACK, INR); put(ws, 'C18', s1['net_disbursal'], BLACK, INR); put(ws, 'D18', '=B18-C18', BLACK, INR)
put(ws, 'A19', 'Upfront cash (negative = small surplus to customer) (Rs)', BOLD); put(ws, 'B19', '=B6-B18', BLACK, INR); put(ws, 'C19', s1['upfront_cash'], BLACK, INR); put(ws, 'D19', '=B19-C19', BLACK, INR)
put(ws, 'A20', 'New EMI (Rs)', BOLD); put(ws, 'B20', '=ROUND(PMT(B10/12,B11,-B15),2)', BLACK, INR); put(ws, 'C20', s1['new_emi'], BLACK, INR); put(ws, 'D20', '=B20-C20', BLACK, INR)
put(ws, 'A21', 'New loan total repayment (schedule at right) (Rs)', BOLD); put(ws, 'B21', '=SUM(H4:H63)', BLACK, INR); put(ws, 'C21', s1['new_loan_total_repayment'], BLACK, INR); put(ws, 'D21', '=B21-C21', BLACK, INR)
put(ws, 'A22', 'Proposed total = upfront + new loan repayment (Rs)', BOLD); put(ws, 'B22', '=B19+B21', BLACK, INR); put(ws, 'C22', s1['proposed_total_outflow'], BLACK, INR); put(ws, 'D22', '=B22-C22', BLACK, INR)
lines_by_key = {line['key']: line for line in s1['lines']}
put(ws, 'A23', 'Existing: pl_a remaining repayment (from Check A) (Rs)', BOLD); put(ws, 'B23', "='Check A - schedule'!C7", Font(name=FONT, color='008000'), INR)
put(ws, 'A24', 'Existing: pl_b remaining repayment (engine schedule) (Rs)', BOLD); put(ws, 'B24', lines_by_key['pl_b']['existing_remaining_repayment'], BLACK, INR, note='Engine output: EMI Rs 8,157 for 27 months at the stated 18% (the implied rate is within 1.5 points), last instalment adjusted.')
put(ws, 'A25', 'Existing: pl_c remaining repayment (engine schedule) (Rs)', BOLD); put(ws, 'B25', lines_by_key['pl_c']['existing_remaining_repayment'], BLACK, INR, note='Engine output: Rs 4,500 a month at 24% until cleared.')
put(ws, 'A26', 'Existing total (no overdue dues here) (Rs)', BOLD); put(ws, 'B26', '=SUM(B23:B25)', BLACK, INR); put(ws, 'C26', s1['existing_total_outflow'], BLACK, INR); put(ws, 'D26', '=B26-C26', BLACK, INR)
put(ws, 'A27', 'Net financial benefit = existing − proposed (Rs)', BOLD); put(ws, 'B27', '=B26-B22', BLACK, INR); put(ws, 'C27', s1['net_financial_benefit'], BLACK, INR); put(ws, 'D27', '=B27-C27', BLACK, INR)
put(ws, 'A28', 'Monthly outgo: existing EMIs − new EMI (Rs)', BOLD); put(ws, 'B28', "='Check A - schedule'!B6+8157+4500-B20", BLACK, INR); put(ws, 'C28', s1['monthly_cash_flow_difference'], BLACK, INR); put(ws, 'D28', '=B28-C28', BLACK, INR)
put(ws, 'A30', 'Reading: the EMI falls by about Rs 10,800 a month, but the customer repays about Rs 1.56 lakh MORE in total. The engine reports this as "Not financially beneficial" with a "lower EMI, higher total cost" warning — never as a saving.', BLACK)
ws.merge_cells('A30:D31'); ws['A30'].alignment = WRAP
for col, label in zip('FGHIJ', ['Month', 'Opening (Rs)', 'Payment (Rs)', 'Interest (Rs)', 'Closing (Rs)']):
    put(ws, f'{col}3', label, HEAD, fill=HEAD_FILL)
for m in range(1, 61):
    r = 3 + m
    ws[f'F{r}'] = m
    ws[f'G{r}'] = '=$B$15' if m == 1 else f'=J{r - 1}'
    ws[f'I{r}'] = f'=ROUND(G{r}*$B$10/12,2)'
    ws[f'H{r}'] = f'=IF(F{r}=$B$11,G{r}+I{r},$B$20)'
    ws[f'J{r}'] = f'=ROUND(G{r}+I{r}-H{r},2)'
    for col in 'GHIJ':
        ws[f'{col}{r}'].number_format = INR
        ws[f'{col}{r}'].font = BLACK
    ws[f'F{r}'].font = BLACK

# ---------------------------------------------------------------- Check D: affordability and score
v05 = scen('V05', 'pl60')
r5 = v05['result']
lines5 = {line['key']: line for line in r5['lines']}
home = engine['V05']['liabilities']['home']['projection']['monthly_obligation']
ws = sheet('Check D - score', [52, 16, 14, 14, 14, 14, 14])
put(ws, 'A1', 'Check D — V05 (partial consolidation): FOIR after consolidation and the suitability score', TITLE)
put(ws, 'B2', 'This sheet', BOLD); put(ws, 'C2', 'Engine', BOLD); put(ws, 'D2', 'Difference', BOLD)
put(ws, 'A3', 'Net monthly income (Rs)', BOLD); put(ws, 'B3', 180000, BLUE, INR)
put(ws, 'A4', 'Home loan EMI — kept as it is (Rs)', BOLD); put(ws, 'B4', '=ROUND(PMT(8.6%/12,180,-3000000),2)', BLACK, INR); put(ws, 'C4', home, BLACK, INR); put(ws, 'D4', '=B4-C4', BLACK, INR)
put(ws, 'A5', 'Personal loan EMI — kept on the Rs 3,00,000 left after part-prepayment (Rs)', BOLD); put(ws, 'B5', '=ROUND(PMT(17%/12,48,-500000),2)', BLACK, INR, note='A part-prepaid loan keeps its EMI and finishes sooner (BV-07).')
put(ws, 'A6', 'New loan EMI (Rs)', BOLD); put(ws, 'B6', r5['new_emi'], BLACK, INR, note='Engine output; recompute as in Check C if wanted.')
put(ws, 'A7', 'FOIR after = (B4 + B5 + B6) ÷ income', BOLD); put(ws, 'B7', '=(B4+B5+B6)/B3', BLACK, PCT); put(ws, 'C7', v05['affordability']['proposed_value'] / 100, BLACK, PCT); put(ws, 'D7', '=B7-C7', BLACK, '0.00%')
put(ws, 'A9', 'Suitability dimension', HEAD, fill=HEAD_FILL)
for col, label in zip('BCDEFG', ['Measure', 'Zero at', 'Full at', 'Score 0–100', 'Weight %', 'Weighted']):
    put(ws, f'{col}9', label, HEAD, fill=HEAD_FILL)
dims = [
    ('Net benefit as % of existing total', r5['benefit_percent'], 0, 10, 30),
    ('FOIR after (%)', '=B7*100', 70, 40, 25),
    ('Rate improvement (points) = weighted old rate − new rate', r5['rate_improvement_points'], 0, 8, 20),
    ('Costs as % of the new loan', f"=ROUND({r5['total_consolidation_costs']}/{r5['loan_amount']}*100,2)", 6, 1, 15),
    ('Extra months of repayment', r5['tenure_change_months'], 36, 0, 10),
]
for i, (label, measure, zero, full, weight) in enumerate(dims, start=10):
    put(ws, f'A{i}', label, BOLD)
    put(ws, f'B{i}', measure, BLACK if isinstance(measure, str) else BLUE, '0.00')
    put(ws, f'C{i}', zero, BLUE, '0.00'); put(ws, f'D{i}', full, BLUE, '0.00')
    put(ws, f'E{i}', f'=ROUND(MAX(0,MIN(1,(B{i}-C{i})/(D{i}-C{i})))*100,2)', BLACK, '0.00')
    put(ws, f'F{i}', weight, BLUE, '0')
    put(ws, f'G{i}', f'=ROUND(E{i}*F{i}/100,2)', BLACK, '0.00')
put(ws, 'A15', 'Total score', BOLD); put(ws, 'B15', '=SUM(G10:G14)', BLACK, '0.00'); put(ws, 'C15', v05['suitability']['score'], BLACK, '0.00'); put(ws, 'D15', '=B15-C15', BLACK, '0.00')
put(ws, 'A16', 'Net financial benefit (Rs)', BOLD); put(ws, 'B16', r5['net_financial_benefit'], BLACK, INR)
put(ws, 'A17', 'Result', BOLD); put(ws, 'B17', '=IF(B16<=0,"Not financially beneficial",IF(B15>=70,"Potentially suitable",IF(B15>=50,"Borderline — advisor review","Low suitability")))', BLACK); put(ws, 'C17', labels[v05['suitability']['band']], BLACK)
put(ws, 'A19', 'Measures in blue are the engine\'s results for V05 (change them to test the bands). Zero-at / full-at and weights are scoring configuration version 1 (BV-14). The score is a guide for the advisor, never a loan approval.', BLACK)
ws.merge_cells('A19:G20'); ws['A19'].alignment = WRAP

out = os.path.join(HERE, 'DC-financial-validation-workbook.xlsx')
wb.save(out)
print('written', out)
