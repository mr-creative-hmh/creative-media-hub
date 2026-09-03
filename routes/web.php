<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
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

// Home & Media Discovery Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Virtual Movies Hub & Cinema Streaming
Route::get('/movies', [MediaController::class, 'index'])->name('movies.index');
Route::get('/movies/{mediaItem}', [MediaController::class, 'show'])->name('movies.show');
Route::get('/movie/{mediaItem}', [MediaController::class, 'show'])->name('movies.show.slug');
Route::post('/movies/{mediaItem}/favorite', [MediaController::class, 'toggleFavorite'])->name('movies.favorite');
Route::get('/api/media/search-metadata', [MediaController::class, 'searchMetadata'])->name('api.media.search-metadata');
Route::post('/api/media/{mediaItem}/fix-match', [MediaController::class, 'fixMatch'])->name('api.media.fix-match');
Route::post('/api/media/{mediaItem}/update-metadata', [MediaController::class, 'updateMetadata'])->name('api.media.update-metadata');
Route::delete('/api/media/{id}', [ScannerController::class, 'deleteSingleMedia'])->name('api.media.delete');
Route::get('/api/vibes', [MediaController::class, 'getVibes'])->name('api.vibes');
Route::get('/api/person/{person}', [MediaController::class, 'getCastExplorer'])->name('api.person');

// Movie Collections & Boxsets (Harry Potter, MCU, Lord of the Rings, etc.)
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');
Route::get('/collections/{slug}', [CollectionController::class, 'show'])->name('collections.show');

// Virtual TV Series Hub & Metadata Management (Supports ID, Slug, and Season/Episode deep links)
Route::get('/series', [SeriesController::class, 'index'])->name('series.index');
Route::get('/series/{series}', [SeriesController::class, 'show'])->name('series.show');
Route::get('/series/{series}/season/{seasonNumber}', [SeriesController::class, 'showSeason'])->name('series.season.show');
Route::get('/series/{series}/season/{seasonNumber}/episode/{episodeNumber}', [SeriesController::class, 'showEpisode'])->name('series.episode.show');
Route::get('/series/{series}/s/{seasonNumber}/e/{episodeNumber}', [SeriesController::class, 'showEpisode'])->name('series.episode.short');
Route::post('/series/{series}/favorite', [SeriesController::class, 'toggleFavorite'])->name('series.favorite');
Route::get('/api/series/search-metadata', [SeriesController::class, 'searchMetadata'])->name('api.series.search-metadata');
Route::post('/api/series/{series}/fix-match', [SeriesController::class, 'fixMatch'])->name('api.series.fix-match');
Route::post('/api/series/{series}/update-metadata', [SeriesController::class, 'updateMetadata'])->name('api.series.update-metadata');

// Metadata & Cover Studio
Route::get('/metadata', [MetadataManagementController::class, 'index'])->name('metadata.index');
Route::post('/api/metadata/batch-enrich', [MetadataManagementController::class, 'batchEnrich'])->name('api.metadata.batch-enrich');
Route::post('/api/metadata/lookup-id', [MetadataManagementController::class, 'lookupId'])->name('api.metadata.lookup-id');
Route::post('/api/metadata/{type}/{id}/reparse', [MetadataManagementController::class, 'reparseItem'])->name('api.metadata.reparse');
Route::post('/api/metadata/{type}/{id}/convert-type', [MetadataManagementController::class, 'convertType'])->name('api.metadata.convert-type');
Route::post('/api/metadata/{type}/{id}/rename-file', [MetadataManagementController::class, 'renameFile'])->name('api.metadata.rename-file');
Route::delete('/api/metadata/{type}/{id}', [MetadataManagementController::class, 'deleteItem'])->name('api.metadata.delete-item');

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
Route::post('/api/organizer/execute/init', [DiskOrganizerController::class, 'initExecution'])->name('api.organizer.execute-init');
Route::post('/api/organizer/execute/batch', [DiskOrganizerController::class, 'processBatch'])->name('api.organizer.execute-batch');
Route::get('/api/organizer/execute/status', [DiskOrganizerController::class, 'getExecutionStatus'])->name('api.organizer.execute-status');
Route::post('/api/organizer/execute/cancel', [DiskOrganizerController::class, 'cancelExecution'])->name('api.organizer.execute-cancel');

// Free Subtitles Hub, Live Scraper Diagnostic & Downloader
Route::get('/subtitles', [SubtitleController::class, 'index'])->name('subtitles.index');
Route::get('/api/subtitles/search', [SubtitleController::class, 'search'])->name('api.subtitles.search');
Route::post('/api/subtitles/download', [SubtitleController::class, 'downloadForMedia'])->name('api.subtitles.download');
Route::get('/api/subtitles/for-media', [SubtitleController::class, 'forMedia'])->name('api.subtitles.for-media');
Route::get('/api/subtitles/list', [SubtitleController::class, 'forMedia'])->name('api.subtitles.list');
Route::post('/api/subtitles/verify-engine', [SubtitleController::class, 'verifyEngine'])->name('api.subtitles.verify-engine');

// Cinema Video & Subtitle Streaming Engine (HTTP 206 Partial Content + Audio Transcoding)
Route::get('/stream/movie/{mediaItem}', [StreamController::class, 'streamMovie'])->name('stream.movie');
Route::get('/stream/episode/{episode}', [StreamController::class, 'streamEpisode'])->name('stream.episode');
Route::get('/stream/subtitles/{subtitle}', [StreamController::class, 'streamSubtitle'])->name('stream.subtitle');
Route::post('/api/playback/progress', [StreamController::class, 'saveProgress'])->name('api.playback.progress');
Route::post('/api/watch-history/progress', [StreamController::class, 'saveProgress']);
Route::get('/api/continue-watching', [StreamController::class, 'getContinueWatching'])->name('api.continue-watching');
Route::get('/api/media/duration', [StreamController::class, 'getMediaDuration'])->name('api.media.duration');
Route::post('/api/stream/stop', [StreamController::class, 'stopStream'])->name('api.stream.stop');
Route::get('/api/stream/cache-status', [StreamController::class, 'getCacheStatus'])->name('api.stream.cache-status');

// Server-Side Remux Streaming Routes (Instant on-the-fly AAC remuxing for unsupported formats)
Route::get('/stream/remux/movie/{mediaItem}', [StreamController::class, 'streamRemuxMovie'])->name('stream.remux.movie');
Route::get('/stream/remux/episode/{episode}', [StreamController::class, 'streamRemuxEpisode'])->name('stream.remux.episode');

// Storage & Codec Analytics
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

// Real Download & Background Ingestion Center
Route::get('/downloads', [DownloadManagerController::class, 'index'])->name('downloads.index');
Route::get('/api/downloads/list', [DownloadManagerController::class, 'list'])->name('api.downloads.list');
Route::post('/api/downloads', [DownloadManagerController::class, 'store'])->name('api.downloads.store');
Route::post('/api/downloads/inspect', [DownloadManagerController::class, 'inspect'])->name('api.downloads.inspect');
Route::get('/api/downloads/settings', [DownloadManagerController::class, 'getSettings'])->name('api.downloads.settings');
Route::post('/api/downloads/settings', [DownloadManagerController::class, 'saveSettings'])->name('api.downloads.settings.save');
Route::post('/api/downloads/process-batch', [DownloadManagerController::class, 'processBatch'])->name('api.downloads.process-batch');
Route::post('/api/downloads/{id}/pause', [DownloadManagerController::class, 'pause'])->name('api.downloads.pause');
Route::post('/api/downloads/{id}/resume', [DownloadManagerController::class, 'resume'])->name('api.downloads.resume');
Route::post('/api/downloads/{id}/retry', [DownloadManagerController::class, 'retry'])->name('api.downloads.retry');
Route::delete('/api/downloads/{id}', [DownloadManagerController::class, 'destroy'])->name('api.downloads.destroy');

// System Settings & API Providers

// Documentation Hub
Route::get('/docs', function () {
    return Inertia::render('Docs/Index');
})->name('docs.index');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/api/settings', [SettingsController::class, 'update'])->name('api.settings.update');
Route::post('/api/settings/test-provider', [SettingsController::class, 'testProvider'])->name('api.settings.test-provider');

require __DIR__.'/settings.php';
