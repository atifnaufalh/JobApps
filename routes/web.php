<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::get('/', [JobController::class, 'index'])->name('home');
Route::redirect('/admin/login', '/?login=admin')->name('admin.login');
Route::get('/admin', [AdminController::class, 'index'])->middleware('admin')->name('admin.dashboard');
Route::get('/applications', [JobController::class, 'applicationsPage'])->name('applications');

Route::prefix('api')->group(function (): void {
    Route::get('/health', [AdminController::class, 'health']);
    Route::get('/jobs', [JobController::class, 'indexJson']);
    Route::get('/applications', [JobController::class, 'applications'])->middleware('auth');
    Route::patch('/applications/{application}', [JobController::class, 'updateApplicationStatus'])->middleware('auth');
    Route::post('/auth/request-otp', [AuthController::class, 'requestOtp'])->middleware('throttle:otp');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth');
    Route::middleware('auth')->group(function (): void {
        Route::post('/jobs', [JobController::class, 'store']);
        Route::post('/jobs/{job}/apply', [JobController::class, 'apply']);
    });
    Route::prefix('admin')->middleware('admin')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'store']);
        Route::patch('/users/{user}', [AdminController::class, 'update']);
        Route::delete('/users/{user}', [AdminController::class, 'destroy']);
    });
});
