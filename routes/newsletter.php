<?php

use App\Http\Controllers\Site\NewsletterController;
use App\Http\Middleware\ValidateNewsletterLink;
use Illuminate\Support\Facades\Route;

Route::prefix('boletim')->name('site.newsletter.')->controller(NewsletterController::class)->group(function (): void {
    Route::post('/inscrever', 'subscribe')->middleware('throttle:10,1')->name('subscribe');

    Route::middleware(ValidateNewsletterLink::class)->group(function (): void {
        Route::get('/confirmar/{key}', 'confirmation')->name('confirm');
        Route::post('/confirmar/{key}', 'confirm')->name('activate');
        Route::get('/perfil/{key}', 'profile')->name('profile');
        Route::post('/perfil/{key}', 'saveProfile')->name('update');
        Route::get('/cancelar/{key}', 'cancellation')->name('cancel');
        Route::post('/cancelar/{key}', 'unsubscribe')->name('unsubscribe');
    });
});
