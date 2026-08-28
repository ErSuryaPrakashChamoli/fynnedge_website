<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\LoanProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');

Route::get('/loans', [LoanProductController::class, 'index'])->name('loans.index');
Route::get('/loans/{loanProduct:slug}', [LoanProductController::class, 'show'])->name('loans.show');
Route::get('/loans/{loanProduct:slug}/apply', [JourneyController::class, 'start'])->name('loans.apply');

Route::get('/eligibility', [JourneyController::class, 'pickProduct'])->name('eligibility.index');
Route::get('/journey/{session}', [JourneyController::class, 'show'])->name('journey.show');
Route::post('/journey/{session}', [JourneyController::class, 'update'])->name('journey.update');
Route::post('/journey/{session}/back', [JourneyController::class, 'back'])->name('journey.back');

Route::post('/eligibility-results/{eligibilityResult}/select', [ApplicationController::class, 'select'])->name('applications.select');
Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
Route::post('/applications/{application}/documents', [ApplicationController::class, 'uploadDocuments'])->name('applications.documents.upload');
Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');

Route::get('/calculators', CalculatorController::class)->name('calculators.index');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
