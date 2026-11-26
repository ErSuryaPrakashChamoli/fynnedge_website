"""
Builds HV-historical-validation-workbook.xlsx: the empty execution workbook
for the historical case validation (18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md,
BV-13 / HV-01). It holds NO case data. Copy it into the restricted validation
folder before entering anything.

    <python-with-openpyxl> docs/debt-consolidation/validation/historical/build_hv_workbook.py

Sheets: Read me · HV-00 gate · Case register · Coverage · Case inputs ·
New loan inputs · Second check · Comparison · Discrepancies · Evidence ·
Approvals · Summary.
"""
import os

from openpyxl import Workbook
from openpyxl.formatting.rule import FormulaRule
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'HV-historical-validation-workbook.xlsx')

FONT = 'Arial'
BLUE = Font(name=FONT, color='0000FF')
BLACK = Font(name=FONT)
BOLD = Font(name=FONT, bold=True)
TITLE = Font(name=FONT, bold=True, size=14)
HEAD = Font(name=FONT, bold=True, color='FFFFFF')
HEAD_FILL = PatternFill('solid', fgColor='1F3864')
INPUT_FILL = PatternFill('solid', fgColor='FFF9E6')
RED_FILL = PatternFill('solid', fgColor='F8CBAD')
GREEN_FILL = PatternFill('solid', fgColor='C6EFCE')
WRAP = Alignment(wrap_text=True, vertical='top')
INR = '#,##0.00;-#,##0.00;"-"'

CASES = [f'HC-{n:02d}' for n in range(1, 21)]
DEBT_ROWS = 160
NEW_LOAN_ROWS = 40
COMPARISON_ROWS = 600
DISCREPANCY_ROWS = 200
EVIDENCE_ROWS = 160

wb = Workbook()
wb.remove(wb.active)


def sheet(title, widths):
    ws = wb.create_sheet(title)
    for index, width in enumerate(widths, start=1):
        ws.column_dimensions[get_column_letter(index)].width = width
    return ws


def header(ws, row, labels):
    for col, label in enumerate(labels, start=1):
        cell = ws.cell(row=row, column=col, value=label)
        cell.font = HEAD
        cell.fill = HEAD_FILL
        cell.alignment = WRAP
    ws.freeze_panes = ws.cell(row=row + 1, column=1)


def input_cells(ws, first_row, last_row, columns):
    for row in range(first_row, last_row + 1):
        for col in columns:
            cell = ws.cell(row=row, column=col)
            cell.font = BLUE
            cell.fill = INPUT_FILL


def choice(ws, options, ref):
    validation = DataValidation(type='list', formula1='"' + ','.join(options) + '"', allow_blank=True)
    ws.add_data_validation(validation)
    validation.add(ref)


# ---------------------------------------------------------------- Read me
ws = sheet('Read me', [110])
lines = [
    ('Historical case validation workbook (BV-13 / HV-01)', TITLE),
    ('Procedure: docs/debt-consolidation/18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md. Execution report: 22-HISTORICAL-VALIDATION-EXECUTION-REPORT.md.', BLACK),
    ('', BLACK),
    ('STATUS WHEN ISSUED: EMPTY. NOT STARTED. Blocked until HV-00 (Legal/DPO permission) is recorded on the "HV-00 gate" sheet.', BOLD),
    ('', BLACK),
    ('Rules', BOLD),
    ('1. Do not collect, copy or enter any historical document or figure until the HV-00 gate shows OPEN.', BLACK),
    ('2. Keep this workbook only in the restricted validation folder (named people only). Never in the website repository, never on a shared drive open to all staff.', BLACK),
    ('3. Never enter names, PAN, Aadhaar, mobile, email, address, date of birth, employer, account, card or loan numbers, or UTRs. Use HC-01 to HC-20 codes only; the key linking codes to real files stays with the Head of Advisory.', BLACK),
    ('4. Do not create validation cases in the website admin or run them on production (the case form records customer consent).', BLACK),
    ('5. Do not invent cases or figures. A case that lacks a document is recorded with that gap (class D2), not filled in.', BLACK),
    ('6. Every D4 (engine defect) blocks sign-off until engineering fixes it and the affected cases are re-run. No D4 may be waived.', BLACK),
    ('', BLACK),
    ('Workflow', BOLD),
    ('a. Legal/DPO record HV-00 → gate OPEN.  b. Head of Advisory names the team (Approvals sheet) and registers cases (Case register).', BLACK),
    ('c. Advisor copies figures from redacted documents into Case inputs / New loan inputs.  d. A second person checks each input row (Second check).', BLACK),
    ('e. Engineer copies the inputs into a case pack (validation/historical/hc-pack-template.json, kept in the restricted folder) and runs:', BLACK),
    ('     php docs/debt-consolidation/validation/historical/hc-harness.php <pack.json> <output-folder>', BLACK),
    ('   The harness refuses to run without the HV-00 reference, outside the restricted folder, or with personal-data fields.', BLACK),
    ('f. Paste hc-engine-extract.csv rows into Comparison (columns A–C and E) and enter the document values (column D).', BLACK),
    ('g. Classify every row that is not within tolerance; log each one in Discrepancies.  h. Summary shows whether the acceptance criteria (procedure §9) are met.', BLACK),
    ('i. Credit reviews and the Head of Advisory approves (Approvals). Record HV-01 / BV-13 in 17-BUSINESS-APPROVAL-TRACKER.md.', BLACK),
    ('', BLACK),
    ('Colour key: blue text on cream = input; black = formula. Comparison tolerance: ₹1 for money (≤ ₹1.00 is a match), exact for months, 0.01 for FOIR points and DSCR.', BLACK),
]
for row, (text, font) in enumerate(lines, start=1):
    cell = ws.cell(row=row, column=1, value=text)
    cell.font = font
    cell.alignment = WRAP

# ---------------------------------------------------------------- HV-00 gate
ws = sheet('HV-00 gate', [6, 62, 26, 22, 14, 30])
ws['A1'] = 'HV-00: Legal/DPO permission to use redacted historical documents (decision pack: 21-HV-00-DECISION-PACK.md)'
ws['A1'].font = TITLE
header(ws, 3, ['#', 'Decision point', 'Decision', 'Decided by (name, role)', 'Date', 'Reference (email/memo ID)'])
points = [
    'Purpose of historical validation (internal test of the engine against real lender documents; no customer decision is made)',
    'Documents proposed for use (statements, card statements, foreclosure letters, sanction letters, disbursal advices, NOCs, income documents only where FOIR/DSCR is compared)',
    'Anonymisation method (procedure §4: remove identifiers, keep amounts/rates/dates; HC codes; key held by Head of Advisory)',
    'Who can access the documents (named people only)',
    'Storage location (restricted folder outside the website and outside shared drives)',
    'Retention period (Legal to choose an option in the decision pack)',
    'Secure deletion procedure',
    'Confirmation that no production customer records (website database) will be used',
    'Confirmation that documents will not be uploaded to the public website or exposed to customers',
]
for index, text in enumerate(points, start=1):
    row = 3 + index
    ws.cell(row=row, column=1, value=index).font = BLACK
    ws.cell(row=row, column=2, value=text).alignment = WRAP
    ws.row_dimensions[row].height = 45
input_cells(ws, 4, 12, [3, 4, 5, 6])
choice(ws, ['Approved', 'Approved with conditions', 'Rejected', 'Deferred'], 'C4:C12')
ws['B14'] = 'Conditions set by Legal/DPO (if any)'
ws['B14'].font = BOLD
ws['C14'].font = BLUE
ws['C14'].fill = INPUT_FILL
ws['B16'] = 'GATE STATUS'
ws['B16'].font = BOLD
ws['C16'] = ('=IF(COUNTIF(C4:C12,"Approved")+COUNTIF(C4:C12,"Approved with conditions")=9,'
             'IF(AND(COUNTA(D4:D12)=9,COUNTA(E4:E12)=9,COUNTA(F4:F12)=9),"OPEN: processing may start",'
             '"CLOSED: decisions lack a name, date or reference"),"CLOSED: do not collect or process documents")')
ws['C16'].font = BOLD
ws.conditional_formatting.add('C16', FormulaRule(formula=['LEFT($C$16,4)="OPEN"'], fill=GREEN_FILL))
ws.conditional_formatting.add('C16', FormulaRule(formula=['LEFT($C$16,6)="CLOSED"'], fill=RED_FILL))

# ---------------------------------------------------------------- Case register
coverage_tags = [
    ('Personal loans (2+)', 3), ('Credit cards', 2), ('Business TL + CC/OD', 2), ('Secured debt → LAP/secured BL', 1),
    ('Part-prepayment', 1), ('Flat-rate NBFC', 1), ('Overdue at closure', 1), ('Not beneficial / not done', 1),
    ('Full consolidation', 1), ('Partial consolidation', 1),
    ('Foreclosure letter with breakdown', 5), ('Sanction + disbursal advice', 3),
]
register_head = ['Case code', 'Applicant type', 'Status'] + [tag for tag, _ in coverage_tags] + [
    'Advisor', 'Inputs entered (date)', 'Second checker', 'Checked (date)', 'Engine run (date)', 'C11 reviewed by', 'Notes (no personal data)']
ws = sheet('Case register', [10, 13, 15] + [11] * len(coverage_tags) + [16, 13, 16, 13, 13, 16, 40])
ws['A1'] = 'Case register: anonymised. One row per case. Tick coverage with Y.'
ws['A1'].font = TITLE
header(ws, 3, register_head)
ws.row_dimensions[3].height = 60
for index, code in enumerate(CASES):
    row = 4 + index
    ws.cell(row=row, column=1, value=code).font = BOLD
input_cells(ws, 4, 23, range(2, len(register_head) + 1))
choice(ws, ['Individual', 'Business'], 'B4:B23')
choice(ws, ['Not started', 'Documents requested', 'Inputs entered', 'Inputs checked', 'Engine run', 'Compared', 'Complete', 'Excluded'], 'C4:C23')
first_tag_col = 4
last_tag_col = first_tag_col + len(coverage_tags) - 1
choice(ws, ['Y', 'N'], f'{get_column_letter(first_tag_col)}4:{get_column_letter(last_tag_col)}23')

# ---------------------------------------------------------------- Coverage
ws = sheet('Coverage', [42, 14, 14, 12])
ws['A1'] = 'Coverage against procedure §3 (counts only cases with status Complete)'
ws['A1'].font = TITLE
header(ws, 3, ['Coverage', 'Minimum cases', 'Complete cases', 'Met?'])
for index, (tag, minimum) in enumerate(coverage_tags):
    row = 4 + index
    col = get_column_letter(first_tag_col + index)
    ws.cell(row=row, column=1, value=tag)
    ws.cell(row=row, column=2, value=minimum)
    ws.cell(row=row, column=3, value=f"=COUNTIFS('Case register'!{col}4:{col}23,\"Y\",'Case register'!C4:C23,\"Complete\")")
    ws.cell(row=row, column=4, value=f'=IF(C{row}>=B{row},"Yes","No")')
last_cov = 3 + len(coverage_tags)
ws.cell(row=last_cov + 2, column=1, value='Cases complete (minimum 10)').font = BOLD
ws.cell(row=last_cov + 2, column=2, value=10)
ws.cell(row=last_cov + 2, column=3, value="=COUNTIF('Case register'!C4:C23,\"Complete\")")
ws.cell(row=last_cov + 2, column=4, value=f'=IF(C{last_cov + 2}>=B{last_cov + 2},"Yes","No")')
ws.cell(row=last_cov + 3, column=1, value='ALL COVERAGE MET').font = BOLD
ws.cell(row=last_cov + 3, column=4, value=f'=IF(COUNTIF(D4:D{last_cov + 2},"No")=0,"Yes","No")').font = BOLD

# ---------------------------------------------------------------- Case inputs (debts)
debt_head = ['Case', 'Debt #', 'Debt type', 'Lender (name only)', 'Closure date', 'Outstanding principal (₹)', 'Rate % a year (as stated)',
             'Flat rate? (Y/N)', 'EMI / monthly payment (₹)', 'Months remaining', 'Days past due', 'Overdue (₹)', 'Instalment due before closure (₹)',
             'Accrued interest (₹)', 'Foreclosure charge %', 'Fixed fee (₹)', 'Other charges (₹)', 'Mode (full/partial/exclude)', 'Amount paid down (₹)',
             'Source document(s)', 'Entered by', 'Entered (date)']
ws = sheet('Case inputs', [9, 7, 20, 18, 12] + [13] * 14 + [24, 14, 12])
ws['A1'] = 'Existing debts: one row per loan or card. Copy figures exactly from the redacted documents. Leave a cell blank if the document does not state it.'
ws['A1'].font = BOLD
header(ws, 3, debt_head)
ws.row_dimensions[3].height = 48
input_cells(ws, 4, 3 + DEBT_ROWS, range(1, len(debt_head) + 1))
choice(ws, CASES, f'A4:A{3 + DEBT_ROWS}')
choice(ws, ['Y', 'N'], f'H4:H{3 + DEBT_ROWS}')
choice(ws, ['full', 'partial', 'exclude'], f'R4:R{3 + DEBT_ROWS}')
for row in range(4, 4 + DEBT_ROWS):
    for col in [6, 9, 12, 13, 14, 16, 17, 19]:
        ws.cell(row=row, column=col).number_format = INR

# ---------------------------------------------------------------- New loan inputs
loan_head = ['Case', 'Route', 'Rate % a year', 'Tenure (months)', 'Processing fee %', 'Fixed fee (₹)', 'Other upfront costs (₹)',
             'Fee deducted from payout? (Y/N)', 'Closure charges paid from loan? (Y/N)', 'Sanctioned amount (₹, only if fixed by lender)',
             'Source document(s)', 'Entered by', 'Entered (date)']
ws = sheet('New loan inputs', [9, 22, 11, 11, 11, 12, 13, 14, 14, 18, 26, 14, 12])
ws['A1'] = 'Consolidation loan, from the sanction letter and disbursal advice. One row per case.'
ws['A1'].font = BOLD
header(ws, 3, loan_head)
ws.row_dimensions[3].height = 48
input_cells(ws, 4, 3 + NEW_LOAN_ROWS, range(1, len(loan_head) + 1))
choice(ws, CASES, f'A4:A{3 + NEW_LOAN_ROWS}')
choice(ws, ['Y', 'N'], f'H4:I{3 + NEW_LOAN_ROWS}')

# ---------------------------------------------------------------- Second check
check_head = ['Case', 'Sheet (Case inputs / New loan inputs)', 'Row number checked', 'Every figure matches the document? (Y/N)',
              'Redaction complete? (Y/N)', 'Corrections made (describe; no personal data)', 'Checked by (not the person who entered)', 'Checked (date)']
ws = sheet('Second check', [9, 22, 12, 16, 14, 44, 22, 12])
ws['A1'] = 'Second-checker verification: one row per input row. The checker must not be the person who entered it.'
ws['A1'].font = BOLD
header(ws, 3, check_head)
ws.row_dimensions[3].height = 48
input_cells(ws, 4, 3 + DEBT_ROWS + NEW_LOAN_ROWS, range(1, len(check_head) + 1))
choice(ws, CASES, f'A4:A{3 + DEBT_ROWS + NEW_LOAN_ROWS}')
choice(ws, ['Case inputs', 'New loan inputs'], f'B4:B{3 + DEBT_ROWS + NEW_LOAN_ROWS}')
choice(ws, ['Y', 'N'], f'D4:E{3 + DEBT_ROWS + NEW_LOAN_ROWS}')

# ---------------------------------------------------------------- Comparison
ws = sheet('Comparison', [9, 7, 40, 15, 15, 13, 10, 11, 10, 40, 22, 12])
ws['A1'] = 'Comparison: paste hc-engine-extract.csv into A, B, C and E; enter the document value in D. Class must be entered for every row not within tolerance.'
ws['A1'].font = BOLD
comp_head = ['Case', 'Comparison (C1–C11)', 'Item', 'Document value', 'Engine value', 'Difference', 'Tolerance', 'Within?',
             'Class (M, D1–D5)', 'Explanation', 'Action / owner', 'Resolved (date)']
header(ws, 3, comp_head)
ws.row_dimensions[3].height = 36
for row in range(4, 4 + COMPARISON_ROWS):
    ws.cell(row=row, column=6, value=f'=IF(OR(D{row}="",E{row}=""),"",E{row}-D{row})').number_format = INR
    ws.cell(row=row, column=7, value=f'=IF(B{row}="","",IF(B{row}="C8",0,IF(B{row}="C10",0.01,IF(B{row}="C11","review",1))))')
    ws.cell(row=row, column=8, value=f'=IF(F{row}="","",IF(G{row}="review","review",IF(ABS(F{row})<=G{row}+0.000001,"Y","N")))')
    for col in [4, 5]:
        ws.cell(row=row, column=col).number_format = INR
input_cells(ws, 4, 3 + COMPARISON_ROWS, [1, 2, 3, 4, 5, 9, 10, 11, 12])
choice(ws, CASES, f'A4:A{3 + COMPARISON_ROWS}')
choice(ws, [f'C{n}' for n in range(1, 12)], f'B4:B{3 + COMPARISON_ROWS}')
choice(ws, ['M', 'D1', 'D2', 'D3', 'D4', 'D5'], f'I4:I{3 + COMPARISON_ROWS}')
ws.conditional_formatting.add(f'H4:H{3 + COMPARISON_ROWS}', FormulaRule(formula=['$H4="N"'], fill=RED_FILL))
ws.conditional_formatting.add(f'I4:I{3 + COMPARISON_ROWS}', FormulaRule(formula=['AND($H4="N",$I4="")'], fill=RED_FILL))
ws.conditional_formatting.add(f'I4:I{3 + COMPARISON_ROWS}', FormulaRule(formula=['AND($H4="N",$I4="M")'], fill=RED_FILL))

# ---------------------------------------------------------------- Discrepancies
ws = sheet('Discrepancies', [9, 9, 12, 8, 44, 30, 40, 22, 12, 14, 12])
ws['A1'] = 'Discrepancy register: every comparison row that is not a match (procedure §7). D4 blocks sign-off and can never be waived.'
ws['A1'].font = BOLD
header(ws, 3, ['ID', 'Case', 'Comparison row (sheet row no.)', 'Class', 'Description', 'Size and effect on customers',
               'Decision (accept as known variance / method change / input added / excluded / engineering fix)',
               'Decided by (name, role)', 'Decided (date)', 'Status', 'Closed (date)'])
ws.row_dimensions[3].height = 60
for index in range(DISCREPANCY_ROWS):
    ws.cell(row=4 + index, column=1, value=f'HD-{index + 1:03d}')
input_cells(ws, 4, 3 + DISCREPANCY_ROWS, range(2, 12))
choice(ws, CASES, f'B4:B{3 + DISCREPANCY_ROWS}')
choice(ws, ['D1', 'D2', 'D3', 'D4', 'D5'], f'D4:D{3 + DISCREPANCY_ROWS}')
choice(ws, ['Open', 'Resolved', 'Re-run needed'], f'J4:J{3 + DISCREPANCY_ROWS}')

# ---------------------------------------------------------------- Evidence
ws = sheet('Evidence', [9, 30, 10, 36, 20, 12, 14])
ws['A1'] = 'Evidence tracking: where each redacted document is kept (restricted folder reference only; never attach documents here).'
ws['A1'].font = BOLD
header(ws, 3, ['Case', 'Document type', 'Held? (Y/N)', 'Location reference in restricted folder', 'Redaction checked by', 'Checked (date)', 'Delete by (per HV-00)'])
ws.row_dimensions[3].height = 36
input_cells(ws, 4, 3 + EVIDENCE_ROWS, range(1, 8))
choice(ws, CASES, f'A4:A{3 + EVIDENCE_ROWS}')
choice(ws, ['Loan statement / repayment schedule', 'Credit card statement', 'Foreclosure letter / quotation', 'Sanction letter',
            'Disbursal advice', 'Closure payment proof', 'NOC', 'Income document'], f'B4:B{3 + EVIDENCE_ROWS}')
choice(ws, ['Y', 'N'], f'C4:C{3 + EVIDENCE_ROWS}')

# ---------------------------------------------------------------- Approvals
ws = sheet('Approvals', [34, 24, 34, 12, 30])
ws['A1'] = 'Team and sign-off (procedure §2 and §9). Nothing here is approved until a named person decides.'
ws['A1'].font = TITLE
header(ws, 3, ['Role', 'Name', 'Decision', 'Date', 'Signature / reference'])
roles = [
    ('Owner of the validation (Head of Advisory)', ''), ('Advisor 1 (prepares inputs)', ''), ('Advisor 2 (prepares inputs)', ''),
    ('Second checker (Credit)', ''), ('Engineer (runs the engine)', ''), ('Restricted folder created by (Operations)', ''),
    ('Target dates agreed by (Owner)', ''),
    ('Prepared by (advisor)', ''), ('Inputs checked by (second person)', ''), ('Engine run by (engineer)', ''),
    ('Reviewed by (Credit)', 'Accept / Accept with conditions / Reject'),
    ('Approved by (Head of Advisory)', 'Accept / Accept with conditions / Reject'),
]
for index, (role, hint) in enumerate(roles):
    ws.cell(row=4 + index, column=1, value=role + (f' ({hint})' if hint else ''))
input_cells(ws, 4, 3 + len(roles), [2, 3, 4, 5])
choice(ws, ['Accept', 'Accept with conditions', 'Reject'], f'C{4 + len(roles) - 2}:C{3 + len(roles)}')

# ---------------------------------------------------------------- Summary
ws = sheet('Summary', [62, 22])
ws['A1'] = 'Summary for sign-off (procedure §8.3 and §9)'
ws['A1'].font = TITLE
last_comp = 3 + COMPARISON_ROWS
last_disc = 3 + DISCREPANCY_ROWS
summary = [
    ('HV-00 gate', "='HV-00 gate'!C16"),
    ('Cases complete', "=COUNTIF('Case register'!C4:C23,\"Complete\")"),
    ('Coverage met (procedure §3)', f"=Coverage!D{last_cov + 3}"),
    ('Comparisons made', f"=COUNTIFS(Comparison!A4:A{last_comp},\"<>\",Comparison!H4:H{last_comp},\"<>\")"),
    ('Within tolerance (M)', f"=COUNTIF(Comparison!H4:H{last_comp},\"Y\")"),
    ('Outside tolerance', f"=COUNTIF(Comparison!H4:H{last_comp},\"N\")"),
    ('Outside tolerance and not yet classified', f"=COUNTIFS(Comparison!H4:H{last_comp},\"N\",Comparison!I4:I{last_comp},\"\")+COUNTIFS(Comparison!H4:H{last_comp},\"N\",Comparison!I4:I{last_comp},\"M\")"),
    ('C11 reasonableness rows awaiting review', f"=COUNTIFS(Comparison!H4:H{last_comp},\"review\",Comparison!L4:L{last_comp},\"\")"),
    ('D1 data-entry errors (corrected)', f"=COUNTIF(Discrepancies!D4:D{last_disc},\"D1\")"),
    ('D2 document gaps (excluded with reasons)', f"=COUNTIF(Discrepancies!D4:D{last_disc},\"D2\")"),
    ('D3 method differences', f"=COUNTIF(Discrepancies!D4:D{last_disc},\"D3\")"),
    ('D3 without a recorded decision', f"=COUNTIFS(Discrepancies!D4:D{last_disc},\"D3\",Discrepancies!G4:G{last_disc},\"\")"),
    ('D4 engine defects', f"=COUNTIF(Discrepancies!D4:D{last_disc},\"D4\")"),
    ('D4 unresolved (must be 0)', f"=COUNTIFS(Discrepancies!D4:D{last_disc},\"D4\",Discrepancies!J4:J{last_disc},\"<>Resolved\")"),
    ('D5 not modelled', f"=COUNTIF(Discrepancies!D4:D{last_disc},\"D5\")"),
    ('D5 without a recorded decision', f"=COUNTIFS(Discrepancies!D4:D{last_disc},\"D5\",Discrepancies!G4:G{last_disc},\"\")"),
]
header(ws, 3, ['Measure', 'Result'])
for index, (label, formula) in enumerate(summary):
    row = 4 + index
    ws.cell(row=row, column=1, value=label)
    ws.cell(row=row, column=2, value=formula)
end = 3 + len(summary)
ws.cell(row=end + 2, column=1, value='ACCEPTANCE CRITERIA (procedure §9)').font = BOLD
ws.cell(row=end + 2, column=2, value=(
    f'=IF(LEFT(B4,4)<>"OPEN","NOT STARTED: HV-00 not recorded",'
    f'IF(B5=0,"NOT STARTED",'
    f'IF(AND(B5>=10,B6="Yes",B10=0,B11=0,B15=0,B17=0,B19=0),"MET: ready for Credit review and Head of Advisory approval","NOT MET")))'))
ws.cell(row=end + 2, column=2).font = BOLD
ws.cell(row=end + 3, column=1, value='Meeting the criteria is evidence for sign-off, not the sign-off itself (Approvals sheet).').font = Font(name=FONT, italic=True)

for worksheet in wb.worksheets:
    for row in worksheet.iter_rows():
        for cell in row:
            if cell.font == Font() or cell.font is None:
                cell.font = BLACK

wb.save(OUT)
print(f'Wrote {OUT}')
