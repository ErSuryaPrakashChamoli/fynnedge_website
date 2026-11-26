"""
Independent reference calculation for the Debt Consolidation validation cases.

Written from the published specification (docs/debt-consolidation/06-financial-engine.md),
NOT translated from the PHP engine. Exact decimal arithmetic, half-up rounding to the
paisa per period. Where the specification leaves a modelling choice open, the choice made
here is listed in INTERPRETATIONS and in the acceptance report, for business sign-off.

    python3 docs/debt-consolidation/validation/reference.py   -> reference-results.json
"""
import json
import math
import os
from decimal import Decimal as D, ROUND_HALF_UP, ROUND_CEILING, getcontext

getcontext().prec = 50
HERE = os.path.dirname(os.path.abspath(__file__))
GST = D('18')            # config default, OD1
CARD_MIN = D('5')        # config default, OD3
DEFAULT_TENURE = 60      # config default
TOLERANCE = D('1.5')     # rate mismatch tolerance (points)
NPA_DPD = 90
MAX_MONTHS = 480

INTERPRETATIONS = [
    'I1 A revolving balance with no planned payment is amortised over each scenario tenure (like-for-like); outside a scenario over the default 60 months; its FOIR obligation is 5% of the balance.',
    'I2 EMI and months both known: the stated rate drives the schedule when the implied reducing rate is within 1.5 points; otherwise the implied rate is used and the final instalment clears the balance.',
    'I3 Accrued interest is a due cleared at full closure, not a consolidation cost.',
    'I4 A partially paid-down amortising loan keeps its EMI and finishes sooner.',
    'I5 Cost break-even: first month in which cumulative interest saved reaches all consolidation costs. Cash break-even: first month (0 = the day of consolidation) in which cumulative cash under the proposal is no more than under the existing plan.',
    'I6 Weighted existing rate is weighted by the principal being closed.',
    'I7 Fees deducted from the payout: the loan is the smallest whole-rupee amount whose payout after the fee and its GST (each rounded to the paisa) covers the amount to finance.',
]


def r2(x):
    return D(x).quantize(D('0.01'), rounding=ROUND_HALF_UP)


def emi(principal, annual, months):
    principal = D(principal)
    if D(annual) == 0:
        return r2(principal / months)
    i = D(annual) / 1200
    f = (1 + i) ** months
    return r2(principal * i * f / (f - 1))


def schedule(principal, annual, payment, months=None):
    """Monthly (payment, interest) rows; months=None runs until cleared (max 480)."""
    bal = r2(principal)
    i = D(annual) / 1200
    rows = []
    limit = months if months else MAX_MONTHS
    for m in range(1, limit + 1):
        if bal <= 0:
            break
        interest = r2(bal * i)
        if (months and m == months) or D(payment) >= bal + interest or m == limit:
            rows.append((r2(bal + interest), interest))
            bal = D(0)
            break
        rows.append((r2(payment), interest))
        bal = r2(bal + interest - D(payment))
    return rows


def implied_rate(principal, payment, months):
    """Reducing annual rate at which `payment` for `months` exactly repays `principal` (bisection)."""
    lo, hi = D(0), D(200)
    for _ in range(200):
        mid = (lo + hi) / 2
        i = mid / 1200
        exact = D(principal) / months if mid == 0 else D(principal) * i * (1 + i) ** months / ((1 + i) ** months - 1)
        if exact > D(payment):
            hi = mid
        else:
            lo = mid
    return (lo + hi) / 2


def project(l, tenure=None):
    """Returns dict(rows, rate, monthly) for keeping the debt as it is."""
    P = D(l['outstanding_principal'])
    rate = D(str(l.get('annual_interest_rate', 0)))
    pay = l.get('monthly_payment')
    months = l.get('remaining_months')
    structure = l.get('repayment_structure') or default_structure(l['type'])
    if structure == 'interest_only':
        n = months or tenure or DEFAULT_TENURE
        interest = r2(P * rate / 1200)
        rows = [(interest, interest)] * (n - 1) + [(r2(P + interest), interest)]
        return {'rows': rows, 'rate': rate, 'monthly': interest}
    if structure == 'revolving':
        if pay:
            return {'rows': schedule(P, rate, D(str(pay))), 'rate': rate, 'monthly': r2(D(str(pay)))}
        n = tenure or DEFAULT_TENURE
        e = emi(P, rate, n)
        return {'rows': schedule(P, rate, e, n), 'rate': rate, 'monthly': r2(P * CARD_MIN / 100)}
    if pay and months:
        implied = implied_rate(P, D(str(pay)), months)
        use = rate if abs(implied - rate) <= TOLERANCE else implied
        return {'rows': schedule(P, use, D(str(pay)), months), 'rate': use, 'monthly': r2(D(str(pay)))}
    if pay:
        return {'rows': schedule(P, rate, D(str(pay))), 'rate': rate, 'monthly': r2(D(str(pay)))}
    n = months or tenure or DEFAULT_TENURE
    e = emi(P, rate, n)
    return {'rows': schedule(P, rate, e, n), 'rate': rate, 'monthly': e}


def default_structure(t):
    if t == 'credit_card_outstanding':
        return 'revolving'
    if t in ('gold_loan', 'cash_credit', 'overdraft', 'invoice_financing', 'working_capital'):
        return 'interest_only'
    return 'amortising'


SECURED = {'vehicle_loan', 'gold_loan', 'home_loan', 'loan_against_property', 'secured_business_loan', 'machinery_loan', 'commercial_vehicle_loan', 'cash_credit'}


def consolidatable(l, as_of):
    status = l.get('account_status', 'regular')
    if status in ('non_performing', 'settled', 'written_off', 'under_dispute'):
        return False
    if l.get('days_past_due', 0) >= NPA_DPD:
        return False
    if l.get('lock_in_ends_on') and l['lock_in_ends_on'] > as_of:
        return False
    return True


def closure(l, amount, full, as_of):
    P = D(str(amount))
    overdue = D(str(l.get('overdue_amount', 0)))
    pending = D(str(l.get('pending_instalment', 0)))
    accrued = D(str(l.get('accrued_interest', 0))) if full else D(0)
    other = D(str(l.get('other_charges', 0)))
    if full and l.get('quoted_closure_amount') is not None:
        total = r2(D(str(l['quoted_closure_amount'])))
        dues = overdue + pending + accrued
        return {'total': total, 'dues': dues, 'costs': total - P - dues,
                'verified': bool(l.get('quotation_verified')) and (l.get('quotation_valid_until') or '') >= as_of}
    charge = r2(P * D(str(l.get('foreclosure_charge_percent', 0))) / 100) + D(str(l.get('foreclosure_charge_fixed', 0)))
    tax = r2(charge * GST / 100)
    return {'total': P + overdue + pending + accrued + charge + tax + other,
            'dues': overdue + pending + accrued, 'costs': charge + tax + other, 'charge': charge, 'tax': tax, 'verified': False}


def lerp(value, zero_at, full_at):
    value, zero_at, full_at = D(str(value)), D(str(zero_at)), D(str(full_at))
    if full_at > zero_at:
        t = (value - zero_at) / (full_at - zero_at)
    else:
        t = (zero_at - value) / (zero_at - full_at)
    return max(D(0), min(D(1), t)) * 100


def run_case(c):
    as_of = c['as_of']
    libs = c['liabilities']
    out = {'portfolio': {}, 'liabilities': {}, 'scenarios': {}}

    # Portfolio and current affordability (keeping everything as it is).
    base = {l['key']: project(l) for l in libs}
    total_out = sum(D(str(l['outstanding_principal'])) for l in libs)
    out['portfolio'] = {
        'total_outstanding': total_out,
        'total_monthly_obligation': sum(b['monthly'] for b in base.values()),
        'secured_outstanding': sum(D(str(l['outstanding_principal'])) for l in libs if l.get('secured', l['type'] in SECURED)),
        'remaining_repayment': sum(sum(p for p, _ in b['rows']) for b in base.values()),
        'remaining_interest': sum(sum(i for _, i in b['rows']) for b in base.values()),
        'weighted_average_rate': sum(D(str(l['outstanding_principal'])) * base[l['key']]['rate'] for l in libs) / total_out,
    }
    for l in libs:
        b = base[l['key']]
        out['liabilities'][l['key']] = {
            'consolidatable_now': consolidatable(l, as_of),
            'monthly_obligation': b['monthly'],
            'remaining_months': len(b['rows']),
            'remaining_repayment': sum(p for p, _ in b['rows']),
            'remaining_interest': sum(i for _, i in b['rows']),
            'effective_annual_rate': b['rate'],
            'closure_total': closure(l, l['outstanding_principal'], True, as_of)['total'],
        }

    fin = c.get('individual_finances')
    bus = c.get('business_finances')
    if fin:
        income = D(str(fin['net_monthly_income'])) + D(str(fin.get('other_monthly_income', 0)))
        other_ob = D(str(fin.get('other_monthly_obligations', 0)))
    else:
        cash = D(str(bus['average_monthly_revenue'])) - D(str(bus['monthly_operating_expenses']))
        other_ob = D(str(bus.get('other_monthly_obligations', 0)))
    current_service = out['portfolio']['total_monthly_obligation'] + other_ob
    out['current'] = {'foir': current_service / income * 100} if fin else {'dscr': cash / current_service}

    for s in c['scenarios']:
        tenure = s['tenure_months']
        rate = D(str(s['annual_interest_rate']))
        p = D(str(s.get('processing_fee_percent', 0))) / 100
        F = D(str(s.get('processing_fee_fixed', 0)))
        other_up = D(str(s.get('other_upfront_costs', 0)))
        deducted = s.get('fees_deducted_from_disbursal', True)
        financed = s.get('closure_costs_financed', True)
        t = GST / 100
        overrides = s.get('liability_overrides', {})

        included, kept = [], []
        for l in libs:
            mode = overrides.get(l['key'], {}).get('mode', l.get('mode', 'full'))
            if mode == 'exclude' or not consolidatable(l, as_of):
                kept.append(l)
            else:
                included.append((l, mode, overrides.get(l['key'], {}).get('partial_amount', l.get('partial_amount'))))

        existing_rows, proposed_res_rows = [], []
        principal = dues = costs = D(0)
        existing_monthly = residual_monthly = D(0)
        existing_due_now = D(0)
        weight_num = weight_den = D(0)
        for l, mode, part in included:
            pr = project(l, tenure)
            existing_rows.append(pr['rows'])
            existing_monthly += pr['monthly'] if not (default_structure(l['type']) == 'revolving' and not l.get('monthly_payment')) else emi(l['outstanding_principal'], l['annual_interest_rate'], tenure)
            full = mode == 'full'
            amount = D(str(l['outstanding_principal'])) if full else D(str(part))
            cl = closure(l, amount, full, as_of)
            principal += amount
            dues += cl['dues']
            costs += cl['costs']
            existing_due_now += D(str(l.get('overdue_amount', 0))) + D(str(l.get('pending_instalment', 0)))
            weight_num += amount * pr['rate']
            weight_den += amount
            if not full:
                left = D(str(l['outstanding_principal'])) - amount
                st = default_structure(l['type'])
                if st == 'amortising':
                    res = schedule(left, pr['rate'], pr['monthly'])
                elif st == 'revolving':
                    res = schedule(left, pr['rate'], pr['monthly'])
                else:
                    n = len(pr['rows'])
                    ii = r2(left * pr['rate'] / 1200)
                    res = [(ii, ii)] * (n - 1) + [(r2(left + ii), ii)]
                proposed_res_rows.append(res)
                residual_monthly += pr['monthly']

        R = principal + dues + (costs if financed else 0)
        override = s.get('loan_amount_override')
        def payout(amount):
            f = r2(amount * p) + F
            return amount - f - r2(f * t)

        if deducted:
            # Smallest whole rupee whose payout (fee and GST each rounded to the paisa) covers R.
            L = D(math.ceil((R + F * (1 + t)) / (1 - p * (1 + t))))
            while payout(L - 1) >= R:
                L -= 1
            while payout(L) < R:
                L += 1
        else:
            L = D(math.ceil(R))
        if override is not None:
            L = D(str(override))
        fee = r2(L * p) + F
        fee_tax = r2(fee * t)
        net = L - (fee + fee_tax if deducted else 0)
        gap = net - R
        upfront = (0 if financed else costs) + (0 if deducted else fee + fee_tax) + other_up - gap

        new_emi = emi(L, rate, tenure)
        new_rows = schedule(L, rate, new_emi, tenure)

        existing_total = existing_due_now + sum(sum(pp for pp, _ in rows) for rows in existing_rows)
        proposed_total = upfront + sum(pp for pp, _ in new_rows) + sum(sum(pp for pp, _ in rows) for rows in proposed_res_rows)
        existing_interest = sum(sum(ii for _, ii in rows) for rows in existing_rows)
        proposed_interest = sum(ii for _, ii in new_rows) + sum(sum(ii for _, ii in rows) for rows in proposed_res_rows)
        total_costs = costs + fee + fee_tax + other_up
        net_benefit = existing_total - proposed_total
        existing_months = max(len(rows) for rows in existing_rows)
        proposed_months = max([tenure] + [len(rows) for rows in proposed_res_rows])

        def month_series(row_lists, idx, n):
            return [sum((rows[m][idx] if m < len(rows) else 0) for rows in row_lists) for m in range(n)]

        horizon = max(existing_months, proposed_months)
        ex_int = month_series(existing_rows, 1, horizon)
        pr_int = [a + b for a, b in zip(month_series([new_rows], 1, horizon), month_series(proposed_res_rows, 1, horizon))]
        ex_pay = month_series(existing_rows, 0, horizon)
        pr_pay = [a + b for a, b in zip(month_series([new_rows], 0, horizon), month_series(proposed_res_rows, 0, horizon))]
        cost_be = None
        saved = D(0)
        for m in range(horizon):
            saved += ex_int[m] - pr_int[m]
            if saved >= total_costs:
                cost_be = m + 1
                break
        cash_be = 0 if upfront <= existing_due_now else None
        ce, cp = existing_due_now, upfront
        if cash_be is None:
            for m in range(horizon):
                ce += ex_pay[m]
                cp += pr_pay[m]
                if cp <= ce:
                    cash_be = m + 1
                    break

        kept_service = sum(project(l, tenure)['monthly'] for l in kept)
        proposed_service = kept_service + residual_monthly + new_emi + other_ob
        weighted = weight_num / weight_den
        benefit_pct = net_benefit / existing_total * 100
        cost_pct = total_costs / L * 100
        if fin:
            aff_value = proposed_service / income * 100
            aff_score = lerp(aff_value, 70, 40)
        else:
            aff_value = cash / proposed_service
            aff_score = lerp(aff_value, D('1.0'), D('1.75'))
        dims = {
            'financial_benefit': (lerp(benefit_pct, 0, 10), 30),
            'affordability': (aff_score, 25),
            'interest_improvement': (lerp(weighted - rate, 0, 8), 20),
            'cost_efficiency': (lerp(cost_pct, 6, 1), 15),
            'repayment_profile': (lerp(proposed_months - existing_months, 36, 0), 10),
        }
        score = sum(r2(sc * w / 100) for sc, w in dims.values())
        band = 'not_beneficial' if net_benefit <= 0 else ('potentially_suitable' if score >= 70 else ('needs_review' if score >= 50 else 'low_suitability'))

        out['scenarios'][s['key']] = {
            'included': [l['key'] for l, _, _ in included],
            'principal_closed': principal, 'dues_cleared': dues, 'closure_costs': costs,
            'loan_amount': L, 'processing_fee': fee, 'processing_fee_tax': fee_tax, 'net_disbursal': net,
            'upfront_cash': upfront, 'new_emi': new_emi,
            'new_loan_total_repayment': sum(pp for pp, _ in new_rows), 'new_loan_interest': sum(ii for _, ii in new_rows),
            'existing_monthly_outflow': existing_monthly, 'proposed_monthly_outflow': new_emi + residual_monthly,
            'existing_total_outflow': existing_total, 'proposed_total_outflow': proposed_total,
            'existing_interest': existing_interest, 'proposed_interest': proposed_interest,
            'net_financial_benefit': net_benefit, 'benefit_percent': benefit_pct,
            'total_consolidation_costs': total_costs, 'existing_months': existing_months, 'proposed_months': proposed_months,
            'cost_break_even_month': cost_be, 'cash_break_even_month': cash_be,
            'weighted_existing_rate': weighted, 'rate_improvement_points': weighted - rate,
            'proposed_affordability': aff_value, 'score': score, 'band': band,
        }
    return out


def to_json(o):
    if isinstance(o, D):
        return float(o)
    raise TypeError(type(o))


if __name__ == '__main__':
    cases = json.load(open(os.path.join(HERE, 'cases.json')))
    result = {'interpretations': INTERPRETATIONS, 'cases': {c['key']: run_case(c) for c in cases}}
    json.dump(result, open(os.path.join(HERE, 'reference-results.json'), 'w'), default=to_json, indent=2)
    print('reference: %d cases written to reference-results.json' % len(cases))
