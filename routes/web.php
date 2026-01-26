<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Payment page - redirects to frontend Next.js app
// The actual payment page UI is handled by Next.js frontend at /payment/[token]
// Backend only provides API endpoints at /api/external/payment/{token}
Route::get('/payment/{token}', function ($token) {
    return view('payment', ['token' => $token]);
});
