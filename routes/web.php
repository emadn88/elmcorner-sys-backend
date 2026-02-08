<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TrialImageController;
use App\Http\Controllers\ClassReportImageController;

Route::get('/', function () {
    return view('welcome');
});

// Payment page - redirects to frontend Next.js app
// The actual payment page UI is handled by Next.js frontend at /payment/[token]
// Backend only provides API endpoints at /api/external/payment/{token}
Route::get('/payment/{token}', function ($token) {
    return view('payment', ['token' => $token]);
});

// Public test route for trial image generation
Route::get('/test/trial-image', [TrialImageController::class, 'generateTestImage']);
Route::get('/test/class-report-image', [ClassReportImageController::class, 'generateTestImage']);
