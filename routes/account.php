<?php

use Illuminate\Support\Facades\Route;

Route::prefix('account')->name('account.')->middleware('auth')->group(function (): void {
    // Register authenticated account routes here.
});
