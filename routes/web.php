<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
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
    // send each role to its own home page (only the admin area exists for now)
    Route::get('/', function () {
        abort_unless(auth()->user()->isAdmin(), 403);

        return redirect()->route('admin.dashboard');
    })->name('dashboard');

    // admin pages
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return view('pages.admin.dashboard', ['title' => 'Dashboard']);
        })->name('dashboard');
    });
});
