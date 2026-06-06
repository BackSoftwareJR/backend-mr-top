<?php

use App\Http\Controllers\Web\EditorialPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/magazine', [EditorialPageController::class, 'hub'])->name('editorial.hub');
Route::get('/magazine/{rubricSlug}/{slug}', [EditorialPageController::class, 'show'])->name('editorial.show');
