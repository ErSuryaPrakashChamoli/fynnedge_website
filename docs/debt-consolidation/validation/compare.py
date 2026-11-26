"""
Compares the production engine (engine-results.json) with the independent reference
(reference-results.json) field by field and writes comparison.json.

Tolerance: money must agree to the paisa (0.01) unless listed in LOOSE, where the
reference cannot reproduce the engine's floating-point implied-rate solver to the paisa
(the agreed tolerance there is Rs 1). Percentages/ratios to 0.01, months exactly.

    python3 -I docs/debt-consolidation/validation/compare.py
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
engine = json.load(open(os.path.join(HERE, 'engine-results.json')))
ref = json.load(open(os.path.join(HERE, 'reference-results.json')))['cases']

SCENARIO_FIELDS = [
    ('principal_closed', 'money'), ('dues_cleared', 'money'), ('closure_costs', 'money'),
    ('loan_amount', 'money'), ('processing_fee', 'money'), ('processing_fee_tax', 'money'),
    ('net_disbursal', 'money'), ('upfront_cash', 'money'), ('new_emi', 'money'),
    ('new_loan_total_repayment', 'money'), ('new_loan_interest', 'money'),
    ('existing_monthly_outflow', 'money'), ('proposed_monthly_outflow', 'money'),
    ('existing_total_outflow', 'money'), ('proposed_total_outflow', 'money'),
    ('existing_interest', 'money'), ('proposed_interest', 'money'),
    ('net_financial_benefit', 'money'), ('benefit_percent', 'pct'),
    ('total_consolidation_costs', 'money'), ('existing_months', 'int'), ('proposed_months', 'int'),
    ('cost_break_even_month', 'int'), ('cash_break_even_month', 'int'),
    ('weighted_existing_rate', 'pct'), ('rate_improvement_points', 'pct'),
]
LIABILITY_FIELDS = [
    ('monthly_obligation', 'money'), ('remaining_months', 'int'), ('remaining_repayment', 'money'),
    ('remaining_interest', 'money'), ('effective_annual_rate', 'pct'),
]
LOOSE = {('V10', 'flat')}


def same(kind, a, b, loose):
    if a is None or b is None:
        return a == b
    if kind == 'int':
        return int(a) == int(b)
    if kind == 'pct':
        return abs(round(float(b), 2) - float(a)) <= 0.011
    return abs(float(a) - float(b)) <= (1.0 if loose else 0.005)


rows = []
for key, e in engine.items():
    r = ref[key]
    scen = {s['scenario']['key']: s for s in e['scenarios']}
    for lk, rl in r['liabilities'].items():
        el = e['liabilities'][lk]
        loose = (key, lk) in LOOSE
        rows.append((key, 'liability ' + lk, 'consolidatable_now', el['classification']['consolidatable_now'], rl['consolidatable_now'], el['classification']['consolidatable_now'] == rl['consolidatable_now']))
        for f, kind in LIABILITY_FIELDS:
            rows.append((key, 'liability ' + lk, f, el['projection'][f], rl[f], same(kind, el['projection'][f], rl[f], loose)))
        if el['full_closure']:
            rows.append((key, 'liability ' + lk, 'closure_total', el['full_closure']['total'], rl['closure_total'], same('money', el['full_closure']['total'], rl['closure_total'], False)))
    for f in ('total_outstanding', 'total_monthly_obligation', 'secured_outstanding', 'remaining_repayment', 'remaining_interest'):
        loose = any(k == key for k, _ in LOOSE)
        rows.append((key, 'portfolio', f, e['portfolio'][f], r['portfolio'][f], same('money', e['portfolio'][f], r['portfolio'][f], loose)))
    rows.append((key, 'portfolio', 'weighted_average_rate', e['portfolio']['weighted_average_rate'], r['portfolio']['weighted_average_rate'], same('pct', e['portfolio']['weighted_average_rate'], r['portfolio']['weighted_average_rate'], False)))
    cur = e['current_affordability']
    rk, rv = next(iter(r['current'].items()))
    rows.append((key, 'current affordability', rk, cur['current_value'], rv, same('pct', cur['current_value'], rv, False)))
    for sk, rs in r['scenarios'].items():
        es = scen[sk]
        res = es['result']
        loose = any(k == key for k, _ in LOOSE)
        rows.append((key, 'scenario ' + sk, 'included_keys', res['included_keys'], rs['included'], sorted(res['included_keys']) == sorted(rs['included'])))
        for f, kind in SCENARIO_FIELDS:
            rows.append((key, 'scenario ' + sk, f, res[f], rs[f], same(kind, res[f], rs[f], loose)))
        rows.append((key, 'scenario ' + sk, 'proposed_affordability', es['affordability']['proposed_value'], rs['proposed_affordability'], same('pct', es['affordability']['proposed_value'], rs['proposed_affordability'], False)))
        rows.append((key, 'scenario ' + sk, 'suitability_score', es['suitability']['score'], rs['score'], same('pct', es['suitability']['score'], rs['score'], False)))
        rows.append((key, 'scenario ' + sk, 'suitability_band', es['suitability']['band'], rs['band'], es['suitability']['band'] == rs['band']))

json.dump([dict(zip(('case', 'item', 'field', 'engine', 'reference', 'match'), row)) for row in rows], open(os.path.join(HERE, 'comparison.json'), 'w'), indent=1)
mismatches = [row for row in rows if not row[5]]
print('compared %d figures: %d match, %d differ' % (len(rows), len(rows) - len(mismatches), len(mismatches)))
for row in mismatches:
    print('  DIFF', row[0], row[1], row[2], 'engine=', row[3], 'reference=', row[4])
