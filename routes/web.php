<?php

use App\Http\Controllers\Web\EditorialFeedController;
use App\Http\Controllers\Web\EditorialPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/magazine', [EditorialPageController::class, 'hub'])->name('editorial.hub');
Route::get('/magazine/{rubricSlug}/{slug}', [EditorialPageController::class, 'show'])->name('editorial.show');

Route::get('/feed.xml', [EditorialFeedController::class, 'rss'])->name('editorial.feed');
Route::get('/magazine/feed.xml', [EditorialFeedController::class, 'rss']);
Route::get('/robots.txt', [EditorialFeedController::class, 'robots'])->name('editorial.robots');
Route::get('/llms.txt', [EditorialFeedController::class, 'llms'])->name('editorial.llms');
Route::get('/sitemap.xml', [EditorialFeedController::class, 'sitemap'])->name('editorial.sitemap');
Route::get('/sitemap-index.xml', [EditorialFeedController::class, 'sitemapIndex'])->name('editorial.sitemap-index');
Route::get('/sitemap-{chunk}.xml', [EditorialFeedController::class, 'sitemapChunk'])
    ->whereNumber('chunk')
    ->name('editorial.sitemap-chunk');
