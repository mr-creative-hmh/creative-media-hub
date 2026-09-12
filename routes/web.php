<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\DiskOrganizerController;
use App\Http\Controllers\DownloadManagerController;
use App\Http\Controllers\FixMatchCollectionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaScoutController;
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
Route::get('/api/fix-match/collections', [FixMatchCollectionController::class, 'getCollections'])->name('api.fix-match.collections');
Route::post('/api/fix-match/collection', [FixMatchCollectionController::class, 'updateCollection'])->name('api.fix-match.update-collection');
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
Route::post('/api/metadata/{type}/{id}/verify-file', [MetadataManagementController::class, 'verifyFile'])->name('api.metadata.verify-file');
Route::post('/api/metadata/{type}/{id}/relocate-file', [MetadataManagementController::class, 'relocateFile'])->name('api.metadata.relocate-file');
Route::get('/api/collections/list', [MetadataManagementController::class, 'listCollections'])->name('api.collections.list');
Route::post('/api/metadata/movie/{id}/collection', [MetadataManagementController::class, 'updateMovieCollection'])->name('api.metadata.movie.collection');
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
Route::get('/api/organizer/browse-directory', [DiskOrganizerController::class, 'browseDirectory'])->name('api.organizer.browse-directory');
Route::post('/api/organizer/plan/start', [DiskOrganizerController::class, 'startPlan'])->name('api.organizer.plan.start');
Route::post('/api/organizer/plan/init', [DiskOrganizerController::class, 'startPlan'])->name('api.organizer.plan.init');
Route::post('/api/organizer/plan/process-batch', [DiskOrganizerController::class, 'processPlanBatch'])->name('api.organizer.plan.process-batch');
Route::post('/api/organizer/plan/batch', [DiskOrganizerController::class, 'processPlanBatch'])->name('api.organizer.plan.batch');
Route::get('/api/organizer/plan/status', [DiskOrganizerController::class, 'getPlanStatus'])->name('api.organizer.plan.status');
Route::post('/api/organizer/plan/pause', [DiskOrganizerController::class, 'pausePlan'])->name('api.organizer.plan.pause');
Route::post('/api/organizer/plan/resume', [DiskOrganizerController::class, 'resumePlan'])->name('api.organizer.plan.resume');
Route::post('/api/organizer/plan/cancel', [DiskOrganizerController::class, 'cancelPlan'])->name('api.organizer.plan.cancel');
Route::post('/api/organizer/dry-run', [DiskOrganizerController::class, 'dryRun'])->name('api.organizer.dry-run');
Route::post('/api/organizer/execute', [DiskOrganizerController::class, 'execute'])->name('api.organizer.execute');
Route::post('/api/organizer/execute/init', [DiskOrganizerController::class, 'initExecution'])->name('api.organizer.execute-init');
Route::post('/api/organizer/execute/batch', [DiskOrganizerController::class, 'processBatch'])->name('api.organizer.execute-batch');
Route::get('/api/organizer/execute/status', [DiskOrganizerController::class, 'getExecutionStatus'])->name('api.organizer.execute-status');
Route::post('/api/organizer/execute/pause', [DiskOrganizerController::class, 'pauseExecution'])->name('api.organizer.execute-pause');
Route::post('/api/organizer/execute/resume', [DiskOrganizerController::class, 'resumeExecution'])->name('api.organizer.execute-resume');
Route::post('/api/organizer/execute/cancel', [DiskOrganizerController::class, 'cancelExecution'])->name('api.organizer.execute-cancel');
Route::get('/api/organizer/watcher', [DiskOrganizerController::class, 'getWatcherStatus'])->name('api.organizer.watcher.status');
Route::post('/api/organizer/watcher/toggle', [DiskOrganizerController::class, 'toggleWatcher'])->name('api.organizer.watcher.toggle');
Route::post('/api/organizer/watcher/folder', [DiskOrganizerController::class, 'updateWatchedFolders'])->name('api.organizer.watcher.folder');
Route::post('/api/organizer/watcher/run-now', [DiskOrganizerController::class, 'runWatcherNow'])->name('api.organizer.watcher.run-now');

// Free Subtitles Hub, Live Scraper Diagnostic & Downloader
Route::get('/subtitles', [SubtitleController::class, 'index'])->name('subtitles.index');
Route::get('/api/subtitles/search', [SubtitleController::class, 'search'])->name('api.subtitles.search');
Route::post('/api/subtitles/download', [SubtitleController::class, 'downloadForMedia'])->name('api.subtitles.download');
Route::get('/api/subtitles/for-media', [SubtitleController::class, 'forMedia'])->name('api.subtitles.for-media');
Route::get('/api/subtitles/list', [SubtitleController::class, 'forMedia'])->name('api.subtitles.list');
Route::post('/api/subtitles/verify-engine', [SubtitleController::class, 'verifyEngine'])->name('api.subtitles.verify-engine');
Route::post('/api/subtitles/check', [SubtitleController::class, 'checkHealth'])->name('api.subtitles.check');
Route::post('/api/subtitles/health-check/start', [SubtitleController::class, 'startHealthJob'])->name('api.subtitles.health-start');
Route::post('/api/subtitles/health-check/batch', [SubtitleController::class, 'processHealthBatch'])->name('api.subtitles.health-batch');
Route::get('/api/subtitles/health-check/status', [SubtitleController::class, 'getHealthJobStatus'])->name('api.subtitles.health-status');
Route::post('/api/subtitles/health-check/pause', [SubtitleController::class, 'pauseHealthJob'])->name('api.subtitles.health-pause');
Route::post('/api/subtitles/health-check/resume', [SubtitleController::class, 'resumeHealthJob'])->name('api.subtitles.health-resume');
Route::post('/api/subtitles/health-check/cancel', [SubtitleController::class, 'cancelHealthJob'])->name('api.subtitles.health-cancel');

// Cinema Video & Subtitle Streaming Engine (HTTP 206 Partial Content + Audio Transcoding)
Route::get('/stream/movie/{mediaItem}', [StreamController::class, 'streamMovie'])->name('stream.movie');
Route::get('/stream/episode/{episode}', [StreamController::class, 'streamEpisode'])->name('stream.episode');
Route::get('/api/stream/episode/{episode}', [StreamController::class, 'streamEpisode'])->name('api.stream.episode');
Route::get('/stream/subtitles/{subtitle}', [StreamController::class, 'streamSubtitle'])->name('stream.subtitle');
Route::post('/api/playback/progress', [StreamController::class, 'saveProgress'])->name('api.playback.progress');
Route::post('/api/watch-history/progress', [StreamController::class, 'saveProgress']);
// Watch History Hub & Playback APIs
Route::get('/watch-history', [StreamController::class, 'watchHistoryPage'])->name('watch-history.index');
Route::get('/api/watch-history', [StreamController::class, 'getWatchHistory'])->name('api.watch-history.index');
Route::delete('/api/watch-history/{id}', [StreamController::class, 'deleteWatchHistory'])->name('api.watch-history.delete');
Route::delete('/api/watch-history', [StreamController::class, 'clearWatchHistory'])->name('api.watch-history.clear');
Route::post('/api/watch-history/clear-all', [StreamController::class, 'clearWatchHistory']);
Route::get('/api/media/duration', [StreamController::class, 'getMediaDuration'])->name('api.media.duration');
Route::post('/api/stream/stop', [StreamController::class, 'stopStream'])->name('api.stream.stop');
Route::get('/api/stream/cache-status', [StreamController::class, 'getCacheStatus'])->name('api.stream.cache-status');
Route::get('/api/stream/playlist', [StreamController::class, 'getPlaylist'])->name('api.stream.playlist');

// Server-Side Remux Streaming Routes (Instant on-the-fly AAC remuxing for unsupported formats)
Route::get('/stream/remux/movie/{mediaItem}', [StreamController::class, 'streamRemuxMovie'])->name('stream.remux.movie');
Route::get('/stream/remux/episode/{episode}', [StreamController::class, 'streamRemuxEpisode'])->name('stream.remux.episode');

// Storage & Codec Analytics
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

// Media Scout (Missing Content & Fast Library Acquisition)
Route::get('/scout', [MediaScoutController::class, 'index'])->name('scout.index');
Route::get('/api/scout/gaps', [MediaScoutController::class, 'getGaps'])->name('api.scout.gaps');
Route::post('/api/scout/refresh', [MediaScoutController::class, 'refresh'])->name('api.scout.refresh');
Route::get('/api/scout/torrents', [MediaScoutController::class, 'getTorrents'])->name('api.scout.torrents');
Route::post('/api/scout/download', [MediaScoutController::class, 'download'])->name('api.scout.download');
Route::post('/api/scout/organize-and-scan', [MediaScoutController::class, 'organizeAndScan'])->name('api.scout.organize-and-scan');

// Real Download & Background Ingestion Center
Route::get('/downloads', [DownloadManagerController::class, 'index'])->name('downloads.index');
Route::get('/api/downloads/list', [DownloadManagerController::class, 'list'])->name('api.downloads.list');
Route::get('/api/downloads/daemon/status', [DownloadManagerController::class, 'daemonStatus'])->name('api.downloads.daemon.status');
Route::post('/api/downloads/daemon/stop', [DownloadManagerController::class, 'stopDaemon'])->name('api.downloads.daemon.stop');
Route::post('/api/downloads', [DownloadManagerController::class, 'store'])->name('api.downloads.store');
Route::post('/api/downloads/inspect', [DownloadManagerController::class, 'inspect'])->name('api.downloads.inspect');
Route::get('/api/downloads/settings', [DownloadManagerController::class, 'getSettings'])->name('api.downloads.settings');
Route::post('/api/downloads/settings', [DownloadManagerController::class, 'saveSettings'])->name('api.downloads.settings.save');
Route::post('/api/downloads/process-batch', [DownloadManagerController::class, 'processBatch'])->name('api.downloads.process-batch');
Route::post('/api/downloads/{id}/pause', [DownloadManagerController::class, 'pause'])->name('api.downloads.pause');
Route::post('/api/downloads/{id}/resume', [DownloadManagerController::class, 'resume'])->name('api.downloads.resume');
Route::post('/api/downloads/{id}/retry', [DownloadManagerController::class, 'retry'])->name('api.downloads.retry');
Route::delete('/api/downloads/{id}', [DownloadManagerController::class, 'destroy'])->name('api.downloads.destroy');

// Documentation Hub
Route::get('/docs', function () {
    return Inertia::render('Docs/Index');
})->name('docs.index');

// System Settings & API Providers
Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/api/settings', [SettingsController::class, 'update'])->name('api.settings.update');
Route::post('/api/settings/test-provider', [SettingsController::class, 'testProvider'])->name('api.settings.test-provider');
Route::get('/api/settings/media-cache-stats', [SettingsController::class, 'getMediaCacheStats'])->name('api.settings.media-cache-stats');
Route::post('/api/settings/clear-media-cache', [SettingsController::class, 'clearMediaCache'])->name('api.settings.clear-media-cache');

// Database Backup & Disaster Recovery
Route::get('/api/database/backups', [DatabaseBackupController::class, 'listBackups'])->name('api.database.backups.list');
Route::post('/api/database/backups/create', [DatabaseBackupController::class, 'createBackup'])->name('api.database.backups.create');
Route::get('/api/database/backup/export', [DatabaseBackupController::class, 'exportJson'])->name('api.database.backup.export');
Route::get('/api/database/backups/download/{filename}', [DatabaseBackupController::class, 'downloadLocal'])->name('api.database.backups.download');
Route::delete('/api/database/backups/{filename}', [DatabaseBackupController::class, 'deleteLocal'])->name('api.database.backups.delete');
Route::post('/api/database/restore/upload', [DatabaseBackupController::class, 'restoreUpload'])->name('api.database.restore.upload');
Route::post('/api/database/restore/local', [DatabaseBackupController::class, 'restoreLocal'])->name('api.database.restore.local');
Route::get('/api/library/backup', [DatabaseBackupController::class, 'exportJson'])->name('api.library.backup');
Route::post('/api/library/restore', [DatabaseBackupController::class, 'restoreUpload'])->name('api.library.restore');
