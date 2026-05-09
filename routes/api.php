<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\HolidayController;

use App\Http\Controllers\ForgotPasswordController;

use App\Http\Controllers\SlotLockController;

use App\Http\Controllers\FacilityBlockController;

Route::get('/facility-blocks', [FacilityBlockController::class, 'check']);

// ── Public routes ────────────────────────────────────────────────────────────
Route::post('/register',         [AuthController::class, 'register']);
Route::post('/login',            [AuthController::class, 'login']);
Route::post('/forgot-password',  [ForgotPasswordController::class, 'forgotPassword']);
Route::post('/reset-password',   [ForgotPasswordController::class, 'resetPassword']);

Route::get('/facilities',              [FacilityController::class, 'index']);
Route::get('/facilities/{id}',         [FacilityController::class, 'show']);
Route::get('/facilities/{id}/slots',   [FacilityController::class, 'availableSlots']);

// Public holidays — frontend uses to colour calendar
Route::get('/holidays', [HolidayController::class, 'index']);

// ── Authenticated user routes ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::put('/me',      [AuthController::class, 'updateMe']);

    // Slot locking
    Route::post('/slots/lock',   [SlotLockController::class, 'lock']);
    Route::delete('/slots/lock', [SlotLockController::class, 'unlock']);

    // Bookings – user can only see & create their own
    Route::get('/bookings',        [BookingController::class, 'myBookings']);
    Route::post('/bookings',       [BookingController::class, 'store']);
    Route::get('/bookings/{id}',   [BookingController::class, 'show']);

    // ── Admin only routes ──────────────────────────────────────────────────
    Route::middleware('admin')->group(function () {

        // Dashboard stats
        Route::get('/admin/stats',    [AdminController::class, 'stats']);

        // All bookings
        Route::get('/admin/bookings',          [AdminController::class, 'allBookings']);
        Route::delete('/admin/bookings/{id}',  [AdminController::class, 'cancelBooking']);

        // All users
        Route::get('/admin/users',             [AdminController::class, 'allUsers']);
        Route::delete('/admin/users/{id}',     [AdminController::class, 'deleteUser']);
        Route::patch('/admin/users/{id}/role', [AdminController::class, 'changeRole']);

        // Admin holidays management
        Route::get('/admin/holidays',                  [HolidayController::class, 'adminIndex']);
        Route::post('/admin/holidays',                 [HolidayController::class, 'store']);
        Route::post('/admin/holidays/generate/{year}', [HolidayController::class, 'generate']);
        Route::delete('/admin/holidays/{id}',          [HolidayController::class, 'destroy']);

        // Facility blocks (tournament/maintenance)
        Route::get('/admin/facility-blocks',           [FacilityBlockController::class, 'index']);
        Route::post('/admin/facility-blocks',          [FacilityBlockController::class, 'store']);
        Route::delete('/admin/facility-blocks/{id}',   [FacilityBlockController::class, 'destroy']);

        // Admin – facility management
        Route::get('/admin/facilities',               [FacilityController::class, 'adminIndex']);
        Route::get('/admin/slot-info',                [FacilityController::class, 'slotInfo']);
        Route::post('/admin/facilities',              [FacilityController::class, 'store']);
        Route::put('/admin/facilities/{id}',          [FacilityController::class, 'update']);
        Route::delete('/admin/facilities/{id}',       [FacilityController::class, 'destroy']);
        Route::patch('/admin/facilities/{id}/toggle', [FacilityController::class, 'toggleActive']);
    });
});