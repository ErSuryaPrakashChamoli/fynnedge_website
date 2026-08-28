<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

it('throttles the contact form route', function () {
    $route = collect(Route::getRoutes())->first(fn ($route) => $route->getName() === 'contact.store');

    expect($route->middleware())->toContain('throttle:contact-form');
});

it('throttles the journey and application routes as a group', function () {
    $throttled = ['journey.show', 'journey.update', 'journey.back', 'applications.select', 'applications.show', 'applications.documents.upload', 'applications.submit'];

    foreach ($throttled as $name) {
        $route = collect(Route::getRoutes())->first(fn ($route) => $route->getName() === $name);

        expect($route)->not->toBeNull("Route [{$name}] should exist");
        expect($route->middleware())->toContain('throttle:public-forms');
    }
});

it('defines the named rate limiters with sane, spam-resistant limits', function () {
    $contactLimit = RateLimiter::limiter('contact-form')(request());
    $formsLimit = RateLimiter::limiter('public-forms')(request());

    expect($contactLimit)->toBeInstanceOf(Limit::class);
    expect($formsLimit)->toBeInstanceOf(Limit::class);
});
