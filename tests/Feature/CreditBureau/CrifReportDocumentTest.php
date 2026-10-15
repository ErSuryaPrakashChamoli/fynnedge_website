<?php

use App\Modules\CreditBureau\Services\CrifReportDocument;

it('reads the header, score and summary of a full report', function () {
    $document = new CrifReportDocument(crifReportFixture());

    expect($document->header())->toBe([
        'report_id' => 'FIXTURE-REPORT-0001',
        'inquiry_id' => 'FIXTURE-INQ-77',
        'batch_id' => 'FIXTURE-BATCH-1',
        'status' => 'SUCCESS',
        'requested_on' => '14-10-2026',
        'issued_on' => '15-10-2026',
    ])
        ->and($document->score())->toBe(742)
        ->and($document->issuedOn()->toDateString())->toBe('2026-10-15')
        ->and($document->summary())->toMatchArray([
            'Total accounts' => '3',
            'Active accounts' => '2',
            'Closed accounts' => '1',
            'Overdue accounts' => '1',
            'Secured accounts' => '1',
            'Unsecured accounts' => '2',
            'Current outstanding balance' => '₹2,68,889',
            'Total sanctioned amount' => '₹5,00,000',
            'Total disbursed amount' => '₹3,72,000',
            'Total overdue amount' => '₹9,108',
            'Inquiries in last 6 months' => '2',
            'Inquiries listed in report' => '2',
            'Credit history length' => '6 yrs 4 mths',
            'Average account age' => '3 yrs 1 mths',
            'Accounts with late payments in history' => '1',
        ]);
});

it('lists every account with masked numbers, parsed history and unmapped fields kept', function () {
    $accounts = (new CrifReportDocument(crifReportFixture()))->accounts();

    expect($accounts)->toHaveCount(3)
        ->and($accounts[0]['fields'])->toMatchArray([
            'Lender' => 'FIXTURE BANK',
            'Account number' => 'XXXXXXXXXXX2345',
            'Instalment' => '₹4,554/Monthly',
            'Interest rate' => '10.2',
            'Sanctioned amount' => null,
        ])
        ->and($accounts[0]['payment_history'][0])->toBe(['month' => 'Sep:2026', 'value' => '030/SMA', 'late' => true])
        ->and($accounts[0]['payment_history'][2]['late'])->toBeFalse()
        ->and($accounts[0]['extra'])->toBe(['FIXTURE-UNMAPPED-FIELD' => 'kept as sent'])
        ->and($accounts[1]['fields']['Closed on'])->toBe('15-06-2025');
});

it('masks identity numbers in variations but keeps names and addresses readable', function () {
    $variations = (new CrifReportDocument(crifReportFixture()))->variations();

    expect($variations['Name'])->toHaveCount(2)
        ->and($variations['Address'][0]['value'])->toBe('1 FIXTURE ROAD TESTNAGAR 000000')
        ->and($variations['PAN'][0]['value'])->toBe('XXXXXX999Z')
        ->and($variations['Phone'][0]['value'])->toBe('XXXXXX0001')
        ->and($variations)->not->toHaveKey('Driving licence');
});

it('returns inquiries, scores with factor codes, employment and every unrecognised section', function () {
    $document = new CrifReportDocument(crifReportFixture());

    expect($document->inquiries()[0]['fields'])->toMatchArray(['Institution' => 'FIXTURE NBFC', 'Amount' => '₹2,00,000', 'Remark' => null])
        ->and($document->inquiries()[1]['fields']['Amount'])->toBeNull()
        ->and($document->scores())->toBe([['type' => 'PERFORM CONSUMER 2.2', 'value' => '742', 'comments' => null, 'factors' => ['SF05', 'SF11', 'SF33']]])
        ->and($document->employment())->toBe([['EMPLOYER-NAME' => 'FIXTURE EMPLOYER LTD', 'OCCUPATION' => 'SALARIED']])
        ->and($document->otherSections())->toBe(['FIXTURE-FUTURE-SECTION' => ['NEW-FLAG' => 'Y'], 'ccirFlag' => 'N'])
        ->and($document->providerFields())->toMatchArray(['client_id' => 'credit_report_crif_TESTFIXTURE0001', 'pan' => 'XXXXXX999Z', 'mobile' => 'XXXXXX0001']);
});

it('copes with a sparse report without inventing values', function () {
    $document = new CrifReportDocument([
        'success' => true,
        'data' => [
            'credit_score' => '',
            'credit_report' => [
                'HEADER' => ['REPORT-ID' => 'SPARSE-1'],
                'PERSONAL-INFO-VARIATION' => '',
                'ACCOUNTS-SUMMARY' => '',
                'RESPONSES' => ['RESPONSE' => ['LOAN-DETAILS' => ['ACCT-TYPE' => 'Gold Loan']]],
                'INQUIRY-HISTORY' => '',
                'SCORES' => '',
            ],
        ],
    ]);

    expect($document->score())->toBeNull()
        ->and($document->rawScore())->toBeNull()
        ->and($document->issuedOn())->toBeNull()
        ->and($document->accounts())->toHaveCount(1)
        ->and($document->accounts()[0]['fields']['Lender'])->toBeNull()
        ->and($document->summary()['Total accounts'])->toBeNull()
        ->and($document->summary()['Total overdue amount'])->toBeNull()
        ->and($document->summary()['Closed accounts'])->toBe('0')
        ->and($document->variations())->toBe([])
        ->and($document->inquiries())->toBe([])
        ->and($document->scores())->toBe([])
        ->and($document->secondarySummary())->toBe([]);
});

it('finds the report under a wrapper or inside a list', function (array $creditReport) {
    $document = new CrifReportDocument(['data' => ['credit_report' => $creditReport]]);

    expect($document->header()['report_id'])->toBe('WRAPPED-1');
})->with([
    'B2C wrapper' => [['B2C-REPORT' => ['HEADER' => ['REPORT-ID' => 'WRAPPED-1']]]],
    'list of reports' => [[['HEADER' => ['REPORT-ID' => 'WRAPPED-1']]]],
]);
