<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Dummy login route to prevent "Route [login] not defined" error
// Laravel redirects here when unauthenticated — we return JSON instead
Route::get('/login', function (Request $request) {
    return response()->json([
        'message' => 'Unauthenticated. Please login first.',
    ], 401);
})->name('login');