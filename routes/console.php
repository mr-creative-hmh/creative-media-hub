<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Services\Organizer\OrganizerWatcherService;
use Illuminate\Support\Facades\Schedule;

Artisan::command('organizer:watch {--dry-run : Only simulate organizing files}', function (OrganizerWatcherService $watcher) {
    $dryRun = (bool) $this->option('dry-run');
    $this->info('Checking watched folders for completed media...');
    $result = $watcher->checkAndOrganize($dryRun);
    $this->info("Scanned: {$result['total_scanned']}, Ready: {$result['ready_count']}, Organized: {$result['organized_count']}");
})->purpose('Monitor watched folders and auto-organize completed downloads');

Schedule::command('organizer:watch')->everyFiveMinutes();