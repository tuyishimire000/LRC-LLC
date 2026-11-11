<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\RentBillingController;
use App\Http\Controllers\OwnerPayoutController;
use App\Http\Controllers\PropertyExpenseController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:super_admin,property_manager'])->group(function () {
    Route::resource('tenants', TenantController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/create', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/leases', [LeaseController::class, 'store'])->name('leases.store');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::post('/leases/{lease}/approve', [LeaseController::class, 'approve'])->name('leases.approve');
    Route::post('/leases/{lease}/terminate', [LeaseController::class, 'terminate'])->name('leases.terminate');
});

Route::middleware(['auth', 'role:super_admin,finance_manager'])->group(function () {
    Route::get('/billing', [RentBillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/payments/create', [RentBillingController::class, 'createPayment'])->name('billing.payment.create');
    Route::post('/billing/payments', [RentBillingController::class, 'storePayment'])->name('billing.payment.store');
    Route::get('/billing/payments/{payment}/verify', [RentBillingController::class, 'showVerificationForm'])->name('billing.payment.verify.form');
    Route::post('/billing/payments/{payment}/verify', [RentBillingController::class, 'verify'])->name('billing.payment.verify');
});

Route::middleware(['auth', 'role:super_admin,finance_manager'])->group(function () {
    Route::get('/accounting/expenses', [PropertyExpenseController::class, 'index'])->name('accounting.expenses.index');
    Route::get('/accounting/expenses/create', [PropertyExpenseController::class, 'create'])->name('accounting.expenses.create');
    Route::post('/accounting/expenses', [PropertyExpenseController::class, 'store'])->name('accounting.expenses.store');
    Route::get('/accounting/payouts', [OwnerPayoutController::class, 'index'])->name('accounting.payouts.index');
    Route::get('/accounting/payouts/create', [OwnerPayoutController::class, 'create'])->name('accounting.payouts.create');
    Route::post('/accounting/payouts', [OwnerPayoutController::class, 'store'])->name('accounting.payouts.store');
    Route::post('/accounting/payouts/{payout}/transfer', [OwnerPayoutController::class, 'recordTransfer'])->name('accounting.payouts.transfer');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::post('/accounting/expenses/{expense}/approve', [PropertyExpenseController::class, 'approve'])->name('accounting.expenses.approve');
    Route::post('/accounting/payouts/{payout}/approve', [OwnerPayoutController::class, 'approve'])->name('accounting.payouts.approve');
});

Route::middleware(['auth', 'role:super_admin,finance_manager'])->group(function () {
    Route::get('/accounting/reports', [FinancialReportController::class, 'index'])->name('accounting.reports.index');
});
