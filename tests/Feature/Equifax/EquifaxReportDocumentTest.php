<?php

use App\Modules\Equifax\Services\EquifaxReportDocument;

it('reads the header, score and score details from the nested report', function () {
    $document = new EquifaxReportDocument(equifaxReportFixture());

    expect($document->header())->toMatchArray([
        'order_number' => 'FIXTURE-ORDER-0001',
        'client_id' => 'FIXTURE-CLIENT',
        'product_code' => 'PCS',
        'date' => '2026-10-16',
    ])
        ->and($document->score())->toBe(761)
        ->and($document->reportDate()->toDateString())->toBe('2026-10-16')
        ->and($document->clientId())->toBe('credit_report_v2_TESTFIXTURE0001')
        ->and($document->scores()[0])->toMatchArray(['type' => 'ERS', 'version' => '4.0', 'name' => 'M3', 'value' => '761'])
        ->and($document->scores()[0]['elements'][0]['Description'])->toBe('Fixture scoring factor');
});

it('masks identity numbers and phones but keeps names, addresses and emails readable', function () {
    $document = new EquifaxReportDocument(equifaxReportFixture());

    expect($document->personalInfo())->toMatchArray(['Full name' => 'TEST FIXTURE', 'Date of birth' => '1990-01-01', 'Age' => '36'])
        ->and($document->identities()['PAN id'][0]['number'])->toBe('XXXXXX999Z')
        ->and($document->identities()['Passport ID'][0]['number'])->toBe('XXXX0001')
        ->and($document->addresses())->toHaveCount(2)
        ->and($document->addresses()[1])->toMatchArray(['address' => '2 SAMPLE LANE', 'state' => 'MH', 'type' => 'Office'])
        ->and($document->phones()[0])->toBe(['number' => 'XXXXXX0001', 'type' => 'Mobile', 'reported_on' => '2026-09-30'])
        ->and($document->emails()[0]['email'])->toBe('fixture@example.test')
        ->and(json_encode($document->requestInfo()))->not->toContain('AAAPZ9999Z')->not->toContain('9000000001');
});

it('lists accounts with masked numbers, rupee amounts, 48-month history and unmapped fields', function () {
    $accounts = (new EquifaxReportDocument(equifaxReportFixture()))->accounts();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]['fields'])->toMatchArray([
            'Lender' => 'FIXTURE BANK',
            'Account number' => 'XXXXXXXXXXX2345',
            'Current balance' => '₹2,68,889',
            'Sanctioned amount' => '₹3,72,000',
            'Past due amount' => '₹9,108',
            'Credit limit' => null,
        ])
        ->and($accounts[0]['history'][0])->toBe(['month' => '09-26', 'status' => '030', 'late' => true])
        ->and($accounts[0]['history'][1]['late'])->toBeFalse()
        ->and($accounts[0]['extra'])->toBe(['FixtureUnmappedField' => 'kept as sent'])
        ->and($accounts[1]['fields']['Closed on'])->toBe('2025-06-15');
});

it('builds a structured summary and keeps every unrecognised section', function () {
    $document = new EquifaxReportDocument(equifaxReportFixture());

    expect($document->summary())->toMatchArray([
        'score' => 761,
        'report_order_number' => 'FIXTURE-ORDER-0001',
        'accounts_total' => '2',
        'accounts_active' => '1',
        'total_past_due' => '9108',
        'enquiries_total' => '2',
    ])
        ->and($document->accountsSummary())->toMatchArray(['No of accounts' => '2', 'Total past due' => '₹9,108'])
        ->and($document->enquirySummary())->toMatchArray(['Past 12 months' => '2', 'Past 30 days' => '0'])
        ->and($document->enquiries()[0]['fields'])->toMatchArray(['Institution' => 'FIXTURE NBFC', 'Amount' => '₹2,00,000'])
        ->and($document->otherSections())->toBe(['OtherKeyInd' => ['AgeOfOldestTrade' => '35', 'FixtureFutureFlag' => 'Y']])
        ->and($document->pdfLink())->toBeNull();
});

it('copes with a sparse or differently wrapped response without inventing values', function () {
    $document = new EquifaxReportDocument([
        'success' => true,
        'data' => [
            'credit_score' => '-1',
            'credit_report' => [['CCRResponse' => ['CIRReportDataLst' => [['CIRReportData' => ['RetailAccountDetails' => ['AccountType' => 'Gold Loan']]]]]]],
            'credit_report_link' => 'https://files.example.test/report.pdf',
        ],
    ]);

    expect($document->score())->toBeNull()
        ->and($document->rawScore())->toBe('-1')
        ->and($document->reportDate())->toBeNull()
        ->and($document->accounts())->toHaveCount(1)
        ->and($document->accounts()[0]['fields']['Lender'])->toBeNull()
        ->and($document->personalInfo())->toBe([])
        ->and($document->addresses())->toBe([])
        ->and($document->summary()['accounts_total'])->toBe('1')
        ->and($document->summary()['total_balance'])->toBeNull()
        ->and($document->pdfLink())->toBe('https://files.example.test/report.pdf');
});
