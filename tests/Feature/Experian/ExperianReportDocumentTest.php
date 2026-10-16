<?php

use App\Modules\Experian\Services\ExperianReportDocument;

it('reads the header, score and score details from the report', function () {
    $document = new ExperianReportDocument(experianFixture());

    expect($document->header())->toMatchArray([
        'report_number' => '1760600000000',
        'report_date' => '20261016',
        'version' => 'V2.4',
        'subscriber' => 'Fixture Subscriber',
        'message' => 'Normal Response',
    ])
        ->and($document->score())->toBe(792)
        ->and($document->scoreDetails())->toBe(['value' => '792', 'confidence' => 'H'])
        ->and($document->reportDate()->toDateString())->toBe('2026-10-16')
        ->and($document->clientId())->toBe('credit_report_experian_TESTFIXTURE0001')
        ->and($document->exactMatch())->toBe('Y');
});

it('masks identity numbers and phones but keeps names, addresses and emails readable', function () {
    $document = new ExperianReportDocument(experianFixture());

    expect($document->personalInfo())->toBe(['First name' => 'TEST', 'Last name' => 'FIXTURE', 'Date of birth' => '01 Jan 1990', 'Gender' => 'Male'])
        ->and($document->identities())->toBe(['PAN' => ['XXXXXX999Z']])
        ->and($document->addresses())->toHaveCount(2)
        ->and($document->addresses()[0])->toBe(['address' => '1 Fixture Lane, Test Nagar, TESTPUR', 'state' => '27', 'postal' => '400001', 'source' => 'Application'])
        ->and($document->addresses()[1])->toBe(['address' => '22 OLD FIXTURE ROAD, SAMPLE COLONY, TESTPUR', 'state' => '27', 'postal' => '400001', 'source' => 'Fixture Bank'])
        ->and($document->phones())->toBe([
            ['number' => 'XXXXXX0001', 'type' => 'Mobile'],
            ['number' => 'XXXXXXX0000', 'type' => 'Telephone (type 01)'],
        ])
        ->and($document->emails())->toBe(['test.fixture@example.test'])
        ->and($document->currentApplication())->toMatchArray(['Amount financed' => '₹2,50,000'])
        ->and(json_encode([$document->accounts(), $document->enquiries(), $document->providerFields(), $document->otherSections()]))
        ->not->toContain('AAAPZ9999Z')->not->toContain('9000000001');
});

it('lists accounts with labelled types, masked numbers, rupee amounts, monthly history and every other field', function () {
    $accounts = (new ExperianReportDocument(experianFixture()))->accounts();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]['fields'])->toMatchArray([
            'Open / closed' => 'Open',
            'Lender' => 'Fixture Bank',
            'Account type' => 'Credit card (10)',
            'Account number' => 'XXXXXXXXXX1234',
            'Portfolio type' => 'Revolving (R)',
            'Account status code' => '11',
            'Credit limit' => '₹1,00,000',
            'Current balance' => '₹45,210',
            'Amount past due' => '₹3,100',
            'Opened on' => '15 Jan 2022',
            'Closed on' => null,
        ])
        ->and($accounts[0]['history'])->toBe([
            ['month' => '09-26', 'status' => '30', 'late' => true],
            ['month' => '08-26', 'status' => '0', 'late' => false],
        ])
        ->and($accounts[0]['extra'])->toMatchArray(['Identification_Number' => 'FIXTURE01', 'CurrencyCode' => 'INR'])
        ->and($accounts[0]['extra']['CAIS_Holder_Details']['Income_TAX_PAN'])->toBe('XXXXXX999Z')
        ->and($accounts[1]['fields'])->toMatchArray(['Open / closed' => 'Closed', 'Account type' => 'Personal loan (5)', 'Closed on' => '15 Jun 2024', 'Instalment' => '₹6,840'])
        ->and($accounts[1]['history'])->toBe([['month' => '06-24', 'status' => '0', 'late' => false]]);
});

it('builds a structured summary, lists enquiries and keeps every unrecognised section', function () {
    $document = new ExperianReportDocument(experianFixture());

    expect($document->summary())->toBe([
        'score' => 792,
        'report_number' => '1760600000000',
        'accounts_total' => '2',
        'accounts_active' => '1',
        'accounts_closed' => '1',
        'accounts_default' => '0',
        'total_outstanding' => '45210',
        'outstanding_secured' => '0',
        'outstanding_unsecured' => '45210',
        'enquiries_last_30_days' => '1',
        'enquiries_last_180_days' => '2',
    ])
        ->and($document->accountsSummary())->toMatchArray(['Credit account total' => '2', 'Outstanding balance all' => '₹45,210', 'Outstanding balance un secured percentage' => '100'])
        ->and($document->enquirySummary())->toMatchArray(['Total CAPS last 180 days' => '2', 'Non credit CAPS last 7 days' => '0'])
        ->and($document->enquiries())->toHaveCount(1)
        ->and($document->enquiries()[0]['fields'])->toBe(['Lender' => 'Fixture Bank', 'Date' => '01 Oct 2026', 'Reason code' => '6', 'Purpose code' => '99', 'Amount' => '₹2,50,000', 'Duration' => '36'])
        ->and($document->nonCreditEnquiries())->toBe([])
        ->and($document->otherSections())->toBe(['Future_Section' => ['Some_New_Field' => 'kept as sent']])
        ->and($document->providerFields())->toBe(['client_id' => 'credit_report_experian_TESTFIXTURE0001', 'name' => 'TEST FIXTURE', 'mobile' => 'XXXXXX0001', 'pan' => 'XXXXXX999Z'])
        ->and($document->pdfLink())->toBeNull();
});

it('copes with a sparse or wrapped response without inventing values', function () {
    $document = new ExperianReportDocument([
        'success' => true,
        'data' => [
            'credit_score' => '-1',
            'credit_report' => ['INProfileResponse' => ['CAIS_Account' => ['CAIS_Account_DETAILS' => ['Account_Type' => '99', 'Date_Closed' => '']]]],
        ],
    ]);

    expect($document->score())->toBeNull()
        ->and($document->rawScore())->toBe('-1')
        ->and($document->reportDate())->toBeNull()
        ->and($document->accounts())->toHaveCount(1)
        ->and($document->accounts()[0]['fields'])->toMatchArray(['Lender' => null, 'Account type' => '99', 'Open / closed' => 'Open'])
        ->and($document->accounts()[0]['history'])->toBe([])
        ->and($document->personalInfo())->toBe([])
        ->and($document->addresses())->toBe([])
        ->and($document->enquiries())->toBe([])
        ->and($document->summary()['accounts_total'])->toBe('1')
        ->and($document->summary()['total_outstanding'])->toBeNull()
        ->and($document->pdfLink())->toBeNull();
});

it('reads the score from SCORE when the envelope has none, and takes only a valid https PDF link', function () {
    expect((new ExperianReportDocument(['data' => ['credit_report' => ['SCORE' => ['BureauScore' => '701']]]]))->score())->toBe(701)
        ->and((new ExperianReportDocument(experianFixture('pdf')))->pdfLink())->toBe('https://files.surepass.test/experian/TESTFIXTURE0001.pdf')
        ->and((new ExperianReportDocument(['data' => ['credit_report_link' => 'http://files.surepass.test/a.pdf']]))->pdfLink())->toBeNull()
        ->and((new ExperianReportDocument(['data' => ['credit_report_link' => 'javascript:alert(1)']]))->pdfLink())->toBeNull();
});
