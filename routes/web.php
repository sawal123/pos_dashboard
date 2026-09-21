<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('transactions', 'transactions.index')->name('transactions.index');
    Route::view('products', 'products.index')->name('products.index');
    Route::view('stock', 'stock.index')->name('stock.index');
});

require __DIR__.'/settings.php';
