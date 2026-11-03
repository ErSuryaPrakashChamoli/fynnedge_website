<?php

/**
 * The credit score gauge's count-up ran on every animation frame for the whole
 * visit, through Alpine state, even scrolled out of sight: ~1.9s of the
 * homepage's main-thread work in Lighthouse mobile (2026-11-03).
 */
function scoreGaugeHtml(): string
{
    $html = test()->get('/')->getContent();
    $start = strpos($html, 'riseDuration');

    return substr($html, $start, strpos($html, 'Score — check yours free', $start) - $start);
}

it('shows the gauge at rest with the needle and the number agreeing before any script runs', function () {
    $gauge = scoreGaugeHtml();

    expect($gauge)
        ->toContain('x-ref="needle" style="transform: rotate(20deg);')
        ->toContain('x-ref="score">760</p>');
});

it('animates the gauge only while it is on screen, writing to the needle and number directly', function () {
    $gauge = scoreGaugeHtml();

    expect($gauge)
        ->toContain('new IntersectionObserver(')
        ->toContain('cancelAnimationFrame(this.frame)')
        ->not->toContain('x-text=')
        ->not->toContain(':style=');
});
