<?php

use App\Modules\Cibil\Services\CibilReportDocument;

it('reads the header, score and score blocks from the report', function () {
    $document = new CibilReportDocument(cibilFixture());

    expect($document->header())->toBe(['control_number' => '9100000001', 'control_numbers' => ['9100000001'], 'records' => 1, 'score_date' => '16102026'])
        ->and($document->score())->toBe(750)
        ->and($document->reportDate()->toDateString())->toBe('2026-10-16')
        ->and($document->clientId())->toBe('credit_report_cibil_TESTFIXTURE0001')
        ->and($document->scores())->toBe([[
            'name' => 'CIBILTUSC3', 'card' => '08', 'version' => '10', 'date' => '16 Oct 2026', 'score' => '00750', 'reasons' => ['14', '02'],
        ]]);
});

it('masks identity numbers and phones but keeps names, addresses and emails readable', function () {
    $document = new CibilReportDocument(cibilFixture());

    expect($document->personalInfo())->toBe(['Name' => 'TEST FIXTURE', 'Date of birth' => '01 Jan 1990', 'Gender (as reported)' => '2'])
        ->and($document->identities())->toBe(['PAN (01)' => ['XXXXXX999Z'], 'Universal ID (Aadhaar) (06)' => ['XXXXXXXX1234']])
        ->and($document->phones())->toBe([
            ['number' => 'XXXXXX0001', 'type' => 'Mobile (01)'],
            ['number' => 'XXXXXXX0000', 'type' => 'Office (03)'],
        ])
        ->and($document->emails())->toBe(['test.fixture@example.test'])
        ->and($document->addresses())->toBe([['address' => '1 FIXTURE LANE, TEST NAGAR, TESTPUR', 'state' => '27', 'postal' => '400001', 'category' => 'Residence (02)', 'reported_on' => '30 Sep 2026']])
        ->and($document->employment())->toBe([['Index' => 'E01', 'Account type' => '10', 'Date reported' => '30092026', 'Occupation code' => '01', 'Income' => '₹50,000']])
        ->and(json_encode([$document->accounts(), $document->enquiries(), $document->providerFields(), $document->otherSections()]))
        ->not->toContain('AAAPZ9999Z')->not->toContain('9000000001')->not->toContain('999900001234');
});

it('lists accounts with labelled types, masked numbers, rupee amounts, decoded payment history and every other field', function () {
    $accounts = (new CibilReportDocument(cibilFixture()))->accounts();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]['fields'])->toMatchArray([
            'Open / closed' => 'Open',
            'Lender' => 'FIXTURE BANK',
            'Account type' => 'Credit card (10)',
            'Account number' => 'XXXXXXXXXX1234',
            'Ownership' => 'Individual (1)',
            'Credit limit' => '₹1,00,000',
            'Current balance' => '₹45,210',
            'Amount overdue' => '₹3,100',
            'Opened on' => '15 Jan 2022',
            'Closed on' => null,
        ])
        ->and($accounts[0]['history'])->toBe([
            ['month' => '09-26', 'status' => '030', 'late' => true],
            ['month' => '08-26', 'status' => '000', 'late' => false],
        ])
        ->and($accounts[0]['extra'])->toBe(['fixtureUnmappedField' => 'kept as sent'])
        ->and($accounts[1]['fields'])->toMatchArray(['Open / closed' => 'Closed', 'Account type' => 'Personal loan (05)', 'Closed on' => '15 Jun 2024', 'EMI' => '₹6,840'])
        ->and($accounts[1]['history'])->toBe([['month' => '06-24', 'status' => '000', 'late' => false], ['month' => '05-24', 'status' => 'STD', 'late' => false]]);
});

it('builds a structured summary and the same insights every bureau gives', function () {
    $this->travelTo('2026-10-20');
    $document = new CibilReportDocument(cibilFixture());

    expect($document->summary())->toBe([
        'score' => 750,
        'control_number' => '9100000001',
        'records' => 1,
        'accounts_total' => '2',
        'accounts_overdue' => '1',
        'accounts_zero_balance' => '1',
        'total_balance' => '45210',
        'total_overdue' => '3100',
        'total_high_credit' => '262000',
        'enquiries_total' => '1',
        'enquiries_past_30_days' => '1',
        'enquiries_past_12_months' => '1',
    ])
        ->and($document->insights())->toBe([
            'accounts_total' => 2,
            'accounts_active' => 1,
            'accounts_overdue' => 1,
            'overdue_amount' => 3100,
            'accounts_written_off' => 0,
            'late_payments_last_12_months' => 1,
            'card_limit' => 100000,
            'card_balance' => 45210,
            'enquiries_last_6_months' => 1,
            'credit_age_months' => 88,
            'outstanding_balance' => 45210,
        ])
        ->and($document->accountsSummary())->toMatchArray(['Total accounts' => '2', 'Overdue balance' => '₹3,100'])
        ->and($document->enquirySummary())->toMatchArray(['Inquiry past 12 months' => '1'])
        ->and($document->enquiries()[0]['fields'])->toBe(['Lender' => 'FIXTURE BANK', 'Date' => '01 Oct 2026', 'Purpose code' => '05', 'Amount' => '₹2,50,000'])
        ->and($document->otherSections())->toBe(['Record 1' => ['fixtureFutureSection' => ['someNewField' => 'kept as sent']]])
        ->and($document->providerFields())->toBe(['client_id' => 'credit_report_cibil_TESTFIXTURE0001', 'mobile' => 'XXXXXX0001', 'pan' => 'XXXXXX999Z', 'name' => 'TEST FIXTURE', 'gender' => 'male'])
        ->and($document->pdfLink())->toBeNull();
});

it('reads every record when credit_report holds more than one', function () {
    $body = cibilFixture();
    $second = ['control_number' => '9100000002', 'ids' => [['idType' => '02', 'idNumber' => 'P1234567']], 'accounts' => [['memberShortName' => 'SECOND LENDER', 'accountType' => '7', 'currentBalance' => '1000']], 'enquiries' => [['enquiryDate' => '2026-09-01', 'memberShortName' => 'SECOND LENDER']]];
    $body['data']['credit_report'][] = $second;

    $document = new CibilReportDocument($body);

    expect($document->header()['control_numbers'])->toBe(['9100000001', '9100000002'])
        ->and($document->header()['records'])->toBe(2)
        ->and($document->accounts())->toHaveCount(3)
        ->and($document->accounts()[2]['fields'])->toMatchArray(['Lender' => 'SECOND LENDER', 'Account type' => 'Gold loan (7)'])
        ->and($document->identities())->toHaveKey('Passport (02)')
        ->and($document->enquiries())->toHaveCount(2)
        ->and($document->enquiries()[1]['fields']['Date'])->toBe('01 Sep 2026')
        ->and($document->summary()['accounts_total'])->toBe('2');
});

it('copes with missing, null, empty and single-object blocks without inventing values', function () {
    $document = new CibilReportDocument([
        'success' => true,
        'data' => [
            'credit_score' => null,
            'credit_report' => ['control_number' => 'ONLY-ONE', 'names' => null, 'accounts' => ['accountType' => '99', 'paymentHistory' => 'XX'], 'scores' => [['score' => '000-1']]],
        ],
    ]);

    expect($document->records())->toHaveCount(1)
        ->and($document->score())->toBeNull()
        ->and($document->rawScore())->toBe('000-1')
        ->and($document->reportDate())->toBeNull()
        ->and($document->accounts())->toHaveCount(1)
        ->and($document->accounts()[0]['fields'])->toMatchArray(['Lender' => null, 'Account type' => '99', 'Open / closed' => 'Open'])
        ->and($document->accounts()[0]['history'])->toBe([])
        ->and($document->personalInfo())->toBe([])
        ->and($document->addresses())->toBe([])
        ->and($document->enquiries())->toBe([])
        ->and($document->summary()['total_balance'])->toBeNull()
        ->and($document->insights()['overdue_amount'])->toBeNull()
        ->and((new CibilReportDocument(['data' => []]))->records())->toBe([]);
});

it('accepts only 300–900 as a CIBIL score, zero padding included', function (?string $raw, ?int $expected) {
    expect((new CibilReportDocument(['data' => ['credit_score' => $raw]]))->score())->toBe($expected);
})->with([
    ['750', 750], ['00750', 750], ['300', 300], ['900', 900], ['-1', null], ['5', null], ['0', null], ['901', null], ['NH', null], ['', null], [null, null],
]);

it('takes only a valid https PDF link or a real Base64 PDF, and keeps the inline PDF out of the stored JSON', function () {
    $pdf = cibilFixture('pdf');
    $inline = ['data' => ['credit_report_base64' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 inline')]];

    expect((new CibilReportDocument($pdf))->pdfLink())->toBe('https://files.surepass.test/cibil/TESTFIXTURE0001.pdf')
        ->and((new CibilReportDocument(['data' => ['credit_report_link' => 'http://files.surepass.test/a.pdf']]))->pdfLink())->toBeNull()
        ->and((new CibilReportDocument(['data' => ['credit_report_link' => 'javascript:alert(1)']]))->pdfLink())->toBeNull()
        ->and((new CibilReportDocument($inline))->inlinePdf())->toBe('%PDF-1.4 inline')
        ->and(CibilReportDocument::decodePdf(base64_encode('not a pdf')))->toBeNull()
        ->and(CibilReportDocument::decodePdf('***'))->toBeNull()
        ->and((new CibilReportDocument($inline))->withoutInlinePdf()['data']['credit_report_base64'])->toBe('[stored as an encrypted PDF file]')
        ->and((new CibilReportDocument($pdf))->withoutInlinePdf())->toBe($pdf);
});
