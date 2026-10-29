<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Master\ClauseController;
use App\Http\Controllers\NonConfirmities\NonConfirmitiesController;
use Illuminate\Support\Facades\Route;

// authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::redirect('/signin', '/login');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function () {
    // send each role to its own home page
    Route::get('/', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('progress-monitoring');
    })->name('dashboard');

    // SMK3 pages (all roles)
    Route::get('/progress-monitoring', function () {
        return view('pages.smk3.progress-monitoring', ['title' => __('smk3.progressMonitoring')]);
    })->name('progress-monitoring');

    Route::get('/non-conformities', [NonConfirmitiesController::class, 'index'])->name('non-conformities');
    Route::post('/non-conformities', [NonConfirmitiesController::class, 'store'])
        ->middleware('role:admin,auditor')
        ->name('non-conformities.store');

    // master data (admin and auditor)
    Route::middleware('role:admin,auditor')->prefix('master')->name('master.')->group(function () {
        Route::get('/clauses', [ClauseController::class, 'index'])->name('clauses');
        Route::post('/clauses', [ClauseController::class, 'store'])->name('clauses.store');
        Route::get('/clauses/template', [ClauseController::class, 'template'])->name('clauses.template');
        Route::post('/clauses/import', [ClauseController::class, 'import'])->name('clauses.import');
        Route::put('/clauses/{criterion}', [ClauseController::class, 'update'])->name('clauses.update');
        Route::delete('/clauses/{criterion}', [ClauseController::class, 'destroy'])->name('clauses.destroy');
        Route::patch('/clauses/{criterion}/category', [ClauseController::class, 'toggleCategory'])->name('clauses.category');
    });

    // admin pages
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return view('pages.admin.dashboard', ['title' => 'Dashboard']);
        })->name('dashboard');
    });
});
