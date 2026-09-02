<?php

use Database\Seeders\LegalPageSeeder;

beforeEach(function () {
    $this->seed(LegalPageSeeder::class);
});

it('renders the redesigned Terms of Use page with the new title and subtitle', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('Your Journey With FynnEdge')
        ->assertSee('Clear and straightforward terms for using FynnEdge.')
        ->assertDontSee('Terms &amp; Conditions', false);
});

it('contains all 24 sections in the new order, none of the old section titles', function () {
    $response = $this->get('/terms')->assertOk();

    $expectedInOrder = [
        'About FynnEdge',
        'Accepting These Terms',
        'Starting a Loan Enquiry',
        'Accuracy of Information',
        'How FynnEdge Assists You',
        'Eligibility &amp; Loan Approval',
        'Credit Information',
        'Your FynnEdge Loan Expert',
        'Communication From FynnEdge',
        'Relationship With Banks &amp; NBFCs',
        'Interest Rates, Fees &amp; Loan Calculations',
        'Using Our Website Responsibly',
        'Calculators, Comparisons &amp; Informational Content',
        'Third-Party Platforms',
        'FynnEdge Website &amp; Intellectual Property',
        'Website Availability',
        'No Assurance of Loan Sanction',
        'Your Responsibilities',
        'Privacy &amp; Use of Information',
        'Restriction or Suspension',
        'Responsibility for Third-Party Decisions',
        'Updates to These Terms',
        'Applicable Law',
        'Contact FynnEdge',
    ];

    $content = $response->getContent();
    $lastPosition = -1;

    foreach ($expectedInOrder as $title) {
        $position = mb_strpos($content, $title);
        expect($position)->not->toBeFalse("Missing section title: {$title}");
        expect($position)->toBeGreaterThan($lastPosition, "Section out of order: {$title}");
        $lastPosition = $position;
    }

    // None of the previous generic-template section headings survive.
    $response->assertDontSee('What FynnEdge is — and isn&#039;t', false)
        ->assertDontSee('Eligibility results are indicative')
        ->assertDontSee('Governing law');
});

it('does not make unsupported regulatory or data-collection claims', function () {
    $response = $this->get('/terms')->assertOk();

    $response->assertDontSee('RBI regulated')
        ->assertDontSee('biometric', false)
        ->assertDontSee('fingerprint', false)
        ->assertDontSee('facial recognition', false)
        ->assertDontSee('CIN', false);
});

it('links out to the Privacy Policy, Credit Report Terms, Contact and Grievance pages instead of duplicating them', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('href="/privacy-policy"', false)
        ->assertSee('href="/credit-report-terms"', false)
        ->assertSee('href="/contact"', false)
        ->assertSee('href="/grievance"', false);
});

it('states plainly that FynnEdge does not guarantee loan approval', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('FynnEdge does not guarantee:')
        ->assertSee('Approval of your loan application');
});
