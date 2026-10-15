<?php

use App\Http\Controllers\WalletController;
use App\Http\Middleware\EnsureWalletLoggedIn;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/create', [WalletController::class, 'showCreate'])->name('create');
Route::post('/create', [WalletController::class, 'create'])->middleware('throttle:5,1');

Route::get('/login', [WalletController::class, 'showLogin'])->name('login');
Route::post('/login', [WalletController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [WalletController::class, 'logout'])->name('logout');

Route::middleware(EnsureWalletLoggedIn::class)->group(function () {
    Route::get('/dashboard', [WalletController::class, 'dashboard'])->name('dashboard');
    Route::post('/send/preview', [WalletController::class, 'preview'])->name('send.preview')->middleware('throttle:10,1');
    Route::post('/send/confirm', [WalletController::class, 'confirm'])->name('send.confirm');
});
