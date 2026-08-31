<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiskOrganizerController;
use App\Http\Controllers\DownloadManagerController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MetadataManagementController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SubtitleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Cinema Dashboard & Overview Hub
Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Documentation & User Guide
Route::get('/docs', function () { return Inertia::render('Docs/Index'); })->name('docs.index');
Route::get('/guide', function () { return Inertia::render('Docs/Index'); })->name('guide.index');

// Standalone Library Metadata Management Studio
Route::get('/metadata', [MetadataManagementController::class, 'index'])->name('metadata.index');
Route::post('/api/metadata/batch-enrich', [MetadataManagementController::class, 'batchEnrich'])->name('api.metadata.batch-enrich');

// Virtual Movies Hub & Metadata Management
Route::get('/movies', [MediaController::class, 'index'])->name('movies.index');
Route::get('/movies/{mediaItem}', [MediaController::class, 'show'])->name('movies.show');
Route::post('/movies/{mediaItem}/favorite', [MediaController::class, 'toggleFavorite'])->name('movies.favorite');
Route::get('/api/media/search-metadata', [MediaController::class, 'searchMetadata'])->name('api.media.search-metadata');
Route::post('/api/media/{mediaItem}/fix-match', [MediaController::class, 'fixMatch'])->name('api.media.fix-match');
Route::post('/api/media/{mediaItem}/update-metadata', [MediaController::class, 'updateMetadata'])->name('api.media.update-metadata');
Route::delete('/api/media/{id}', [ScannerController::class, 'deleteSingleMedia'])->name('api.media.delete');
Route::get('/api/vibes', [MediaController::class, 'getVibes'])->name('api.vibes');
Route::get('/api/person/{person}', [MediaController::class, 'getCastExplorer'])->name('api.person');

// Virtual TV Series Hub & Metadata Management
Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
Route::get('/series/{series}', [SeriesController::class, 'show'])->name('series.show');
Route::post('/series/{series}/favorite', [SeriesController::class, 'toggleFavorite'])->name('series.favorite');
Route::get('/api/series/search-metadata', [SeriesController::class, 'searchMetadata'])->name('api.series.search-metadata');
Route::post('/api/series/{series}/fix-match', [SeriesController::class, 'fixMatch'])->name('api.series.fix-match');
Route::post('/api/series/{series}/update-metadata', [SeriesController::class, 'updateMetadata'])->name('api.series.update-metadata');

// Virtual Media Scanner & Background Job Control Center
Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner.index');
Route::post('/api/scanner/directories', [ScannerController::class, 'addDirectory'])->name('api.scanner.directories.add');
Route::delete('/api/scanner/directories/{index?}', [ScannerController::class, 'removeDirectory'])->name('api.scanner.directories.remove');
Route::post('/api/scanner/start', [ScannerController::class, 'startScan'])->name('api.scanner.start');
Route::post('/api/scanner/rescan-fresh', [ScannerController::class, 'rescanFresh'])->name('api.scanner.rescan-fresh');
Route::post('/api/scanner/scan-folder', [ScannerController::class, 'scanFolder'])->name('api.scanner.scan-folder');
Route::post('/api/scanner/process-batch', [ScannerController::class, 'processBatch'])->name('api.scanner.process-batch');
Route::post('/api/scanner/pause', [ScannerController::class, 'pauseScan'])->name('api.scanner.pause');
Route::post('/api/scanner/resume', [ScannerController::class, 'resumeScan'])->name('api.scanner.resume');
Route::post('/api/scanner/cancel', [ScannerController::class, 'cancelScan'])->name('api.scanner.cancel');
Route::get('/api/scanner/status', [ScannerController::class, 'getStatus'])->name('api.scanner.status');
Route::post('/api/library/enrich-missing', [ScannerController::class, 'enrichMissing'])->name('api.library.enrich-missing');
Route::post('/api/library/clear-demo', [ScannerController::class, 'clearDemoCatalog'])->name('api.library.clear-demo');
Route::post('/api/scanner/clear-catalog', [ScannerController::class, 'clearDemoCatalog'])->name('api.scanner.clear-catalog');

// Physical Disk Organizer Studio & Dry-Run
Route::get('/organizer', [DiskOrganizerController::class, 'index'])->name('organizer.index');
Route::get('/api/organizer/load-virtual', [DiskOrganizerController::class, 'loadFromVirtualLibrary'])->name('api.organizer.load-virtual');
Route::post('/api/organizer/scan', [DiskOrganizerController::class, 'scan'])->name('api.organizer.scan');
Route::post('/api/organizer/dry-run', [DiskOrganizerController::class, 'dryRun'])->name('api.organizer.dry-run');
Route::post('/api/organizer/execute', [DiskOrganizerController::class, 'execute'])->name('api.organizer.execute');

// Free Subtitles Hub, Live Scraper Diagnostic & Downloader
Route::get('/subtitles', [SubtitleController::class, 'index'])->name('subtitles.index');
Route::get('/api/subtitles/search', [SubtitleController::class, 'search'])->name('api.subtitles.search');
Route::post('/api/subtitles/download', [SubtitleController::class, 'downloadForMedia'])->name('api.subtitles.download');
Route::post('/api/subtitles/verify-engine', [SubtitleController::class, 'verifyEngine'])->name('api.subtitles.verify-engine');

// Cinema Video & Subtitle Streaming Engine (HTTP 206 Partial Content + Audio Transcoding)
Route::get('/stream/movie/{mediaItem}', [StreamController::class, 'streamMovie'])->name('stream.movie');
Route::get('/stream/episode/{episode}', [StreamController::class, 'streamEpisode'])->name('stream.episode');
Route::get('/stream/subtitles/{subtitle}', [StreamController::class, 'streamSubtitle'])->name('stream.subtitle');
Route::post('/api/playback/progress', [StreamController::class, 'saveProgress'])->name('api.playback.progress');
Route::get('/api/continue-watching', [StreamController::class, 'getContinueWatching'])->name('api.continue-watching');

// Storage & Codec Analytics
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

// Real Download & Background Ingestion Center
Route::get('/downloads', [DownloadManagerController::class, 'index'])->name('downloads.index');
Route::get('/api/downloads/list', [DownloadManagerController::class, 'list'])->name('api.downloads.list');
Route::post('/api/downloads', [DownloadManagerController::class, 'store'])->name('api.downloads.store');
Route::post('/api/downloads/process-batch', [DownloadManagerController::class, 'processBatch'])->name('api.downloads.process-batch');
Route::post('/api/downloads/{id}/pause', [DownloadManagerController::class, 'pause'])->name('api.downloads.pause');
Route::post('/api/downloads/{id}/resume', [DownloadManagerController::class, 'resume'])->name('api.downloads.resume');
Route::post('/api/downloads/{id}/retry', [DownloadManagerController::class, 'retry'])->name('api.downloads.retry');
Route::delete('/api/downloads/{id}', [DownloadManagerController::class, 'destroy'])->name('api.downloads.destroy');

// System Settings & API Providers
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/api/settings', [SettingsController::class, 'update'])->name('api.settings.update');
Route::post('/api/settings/test-provider', [SettingsController::class, 'testProvider'])->name('api.settings.test-provider');
