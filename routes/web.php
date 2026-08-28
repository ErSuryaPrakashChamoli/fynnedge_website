<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoanProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');

Route::get('/loans', [LoanProductController::class, 'index'])->name('loans.index');
Route::get('/loans/{loanProduct:slug}', [LoanProductController::class, 'show'])->name('loans.show');

Route::get('/calculators', CalculatorController::class)->name('calculators.index');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
