<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::resource('tenants', TenantController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/leases', [LeaseController::class, 'store'])->name('leases.store');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::post('/leases/{lease}/approve', [LeaseController::class, 'approve'])->name('leases.approve');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');
});
