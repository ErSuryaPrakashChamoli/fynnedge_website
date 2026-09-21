<?php

/*
 * Queued work is invisible when it never runs: a Filament CSV import is
 * accepted, the imports row stays at processed_rows=0, and nothing is logged.
 * The container image is the only thing that starts a worker in production,
 * so the program declaration is pinned here rather than left to review.
 */
it('runs a queue worker in the production container', function () {
    $supervisorConfig = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

    expect($supervisorConfig)
        ->toContain('[program:queue-worker]')
        ->toContain('artisan queue:work')
        ->toContain('user=www-data')
        ->toContain('autorestart=true');
});

it('keeps the queue worker in the image the Dockerfile builds', function () {
    expect(file_get_contents(base_path('Dockerfile')))
        ->toContain('COPY docker/supervisor/supervisord.conf');
});
