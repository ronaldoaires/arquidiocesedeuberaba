<?php

use App\Http\Controllers\Admin\ContactAttachmentController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

Route::get('/storage/{path}', [StorageController::class, 'serve'])
    ->where('path', '.*')
    ->name('media.show');

Route::get('/uploads/{path}', [StorageController::class, 'legacy'])
    ->where('path', '.*')
    ->name('media.legacy');

Route::get('/admin/contact-submissions/{submission}/attachments/{index}', ContactAttachmentController::class)->whereNumber(['submission', 'index'])->name('admin.contact-attachments.download');

require __DIR__.'/site.php';
require __DIR__.'/newsletter.php';
require __DIR__.'/account.php';
