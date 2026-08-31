<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiskOrganizerController;
use App\Http\Controllers\DownloadManagerController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SubtitleController;
use Illuminate\Support\Facades\Route;

// Cinema Dashboard & Overview Hub
Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Virtual Movies Hub
Route::get('/movies', [MediaController::class, 'index'])->name('movies.index');
Route::get('/movies/{mediaItem}', [MediaController::class, 'show'])->name('movies.show');
Route::post('/movies/{mediaItem}/favorite', [MediaController::class, 'toggleFavorite'])->name('movies.favorite');
Route::get('/api/vibes', [MediaController::class, 'getVibes'])->name('api.vibes');
Route::get('/api/person/{person}', [MediaController::class, 'getCastExplorer'])->name('api.person');

// Virtual TV Series Hub
Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
Route::get('/series/{series}', [SeriesController::class, 'show'])->name('series.show');
Route::post('/series/{series}/favorite', [SeriesController::class, 'toggleFavorite'])->name('series.favorite');

// Cinema Video & Subtitle Streaming Engine (HTTP 206 Partial Content)
Route::get('/stream/movie/{mediaItem}', [StreamController::class, 'streamMovie'])->name('stream.movie');
Route::get('/stream/episode/{episode}', [StreamController::class, 'streamEpisode'])->name('stream.episode');
Route::get('/stream/subtitles/{subtitle}', [StreamController::class, 'streamSubtitle'])->name('stream.subtitle');
Route::post('/api/playback/progress', [StreamController::class, 'saveProgress'])->name('api.playback.progress');
Route::get('/api/continue-watching', [StreamController::class, 'getContinueWatching'])->name('api.continue-watching');

// Physical Disk Organizer Studio & Dry-Run
Route::get('/organizer', [DiskOrganizerController::class, 'index'])->name('organizer.index');
Route::post('/api/organizer/scan', [DiskOrganizerController::class, 'scan'])->name('api.organizer.scan');
Route::post('/api/organizer/dry-run', [DiskOrganizerController::class, 'dryRun'])->name('api.organizer.dry-run');
Route::post('/api/organizer/execute', [DiskOrganizerController::class, 'execute'])->name('api.organizer.execute');

// Subtitles Hub & Downloader
Route::get('/subtitles', [SubtitleController::class, 'index'])->name('subtitles.index');
Route::get('/api/subtitles/search', [SubtitleController::class, 'search'])->name('api.subtitles.search');
Route::post('/api/subtitles/download', [SubtitleController::class, 'downloadForMedia'])->name('api.subtitles.download');

// Storage & Codec Analytics
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

// Download & Watch Folder Center
Route::get('/downloads', [DownloadManagerController::class, 'index'])->name('downloads.index');
Route::post('/api/downloads', [DownloadManagerController::class, 'store'])->name('api.downloads.store');

if (file_exists(__DIR__.'/settings.php')) {
    require __DIR__.'/settings.php';
}
