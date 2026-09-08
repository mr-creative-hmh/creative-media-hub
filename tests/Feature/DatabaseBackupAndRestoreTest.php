<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Episode;
use App\Models\MediaItem;
use App\Models\Season;
use App\Models\Series;
use App\Services\Database\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupAndRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean any test backups created in storage/app/backups
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                if (str_contains($file->getFilename(), 'test_')) {
                    @unlink($file->getRealPath());
                }
            }
        }
        parent::tearDown();
    }

    public function test_can_list_local_backups(): void
    {
        $response = $this->getJson('/api/database/backups');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'backups',
            ]);
    }

    public function test_can_create_local_backup_snapshot(): void
    {
        $response = $this->postJson('/api/database/backups/create', [
            'name' => 'test_feature_snapshot',
            'format' => 'json',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'name' => 'test_feature_snapshot',
            ])
            ->assertJsonStructure([
                'counts',
                'files',
            ]);

        $service = app(DatabaseBackupService::class);
        $backups = $service->listLocalBackups();
        $this->assertTrue(collect($backups)->contains(fn ($b) => $b['filename'] === 'test_feature_snapshot.json'));
    }

    public function test_can_export_backup_json_download(): void
    {
        $response = $this->get('/api/database/backup/export');
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json');

        // Test library backup alias as well
        $aliasResponse = $this->get('/api/library/backup');
        $aliasResponse->assertStatus(200);
    }

    public function test_can_restore_from_uploaded_json_file(): void
    {
        $service = app(DatabaseBackupService::class);
        $exportData = $service->exportData();

        $tempJson = tempnam(sys_get_temp_dir(), 'test_restore_') . '.json';
        file_put_contents($tempJson, json_encode($exportData));

        $uploadedFile = new UploadedFile(
            $tempJson,
            'test_upload_backup.json',
            'application/json',
            null,
            true
        );

        $response = $this->post('/api/database/restore/upload', [
            'backup_file' => $uploadedFile,
            'mode' => 'merge',
            'sections' => ['movies', 'series'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'mode' => 'merge',
            ]);

        @unlink($tempJson);
    }

    public function test_selective_restore_movies_only_leaves_series_untouched(): void
    {
        $service = app(DatabaseBackupService::class);

        // Seed initial movie and initial series
        $origMovie = MediaItem::create([
            'title' => 'Original Movie In Backup',
            'file_path' => 'H:/Movies/Orig/movie.mp4',
        ]);

        $origSeries = Series::create([
            'title' => 'Original Series In Backup',
            'folder_path' => 'H:/Series/Orig',
        ]);

        // Take a snapshot
        $backupResult = $service->createLocalBackup('test_selective_backup', 'json');
        $this->assertTrue($backupResult['success']);

        // Now add a newer series into DB that wasn't in the backup
        $newSeries = Series::create([
            'title' => 'Newly Added Series',
            'folder_path' => 'H:/Series/New',
        ]);

        // And modify the original movie in DB
        $origMovie->update(['title' => 'Locally Modified Movie']);

        // Restore ONLY movies with overwrite mode
        $restoreResult = $service->restoreFromFile(
            $service->getBackupPath() . '/test_selective_backup.json',
            'overwrite',
            ['movies']
        );

        $this->assertTrue($restoreResult['success']);
        $this->assertEquals(['movies'], $restoreResult['sections']);

        // Verification:
        // 1. Movies were restored: movie title is back to original
        $this->assertDatabaseHas('media_items', [
            'id' => $origMovie->id,
            'title' => 'Original Movie In Backup',
        ]);

        // 2. Series were NOT touched by the overwrite!
        // Both the original series and the newly added series must still be present!
        $this->assertDatabaseHas('series', [
            'id' => $origSeries->id,
            'title' => 'Original Series In Backup',
        ]);
        $this->assertDatabaseHas('series', [
            'id' => $newSeries->id,
            'title' => 'Newly Added Series',
        ]);
    }

    public function test_selective_restore_series_only_leaves_movies_untouched(): void
    {
        $service = app(DatabaseBackupService::class);

        // Seed initial movie and initial series
        $movie = MediaItem::create([
            'title' => 'Untouched Movie Test',
            'file_path' => 'H:/Movies/Test/movie.mp4',
        ]);

        $series = Series::create([
            'title' => 'Original TV Show',
            'folder_path' => 'H:/Series/Test',
        ]);

        $backupResult = $service->createLocalBackup('test_series_backup', 'json');

        // Modify series title in database
        $series->update(['title' => 'Locally Changed TV Show']);

        // Restore ONLY series
        $restoreResult = $service->restoreFromFile(
            $service->getBackupPath() . '/test_series_backup.json',
            'overwrite',
            ['series']
        );

        $this->assertTrue($restoreResult['success']);

        // Series is restored to original title
        $this->assertDatabaseHas('series', [
            'id' => $series->id,
            'title' => 'Original TV Show',
        ]);

        // Movie was untouched and still exists
        $this->assertDatabaseHas('media_items', [
            'id' => $movie->id,
            'title' => 'Untouched Movie Test',
        ]);
    }

    public function test_selective_restore_via_api_endpoint(): void
    {
        $service = app(DatabaseBackupService::class);
        $snapshot = $service->createLocalBackup('test_api_selective', 'json');
        $this->assertTrue($snapshot['success']);

        $response = $this->postJson('/api/database/restore/local', [
            'filename' => 'test_api_selective.json',
            'mode' => 'overwrite',
            'sections' => ['movies', 'settings'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'sections' => ['movies', 'settings'],
            ]);
    }

    public function test_can_delete_local_backup(): void
    {
        $service = app(DatabaseBackupService::class);
        $created = $service->createLocalBackup('test_for_delete', 'json');

        $response = $this->deleteJson('/api/database/backups/test_for_delete.json');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $backups = $service->listLocalBackups();
        $this->assertFalse(collect($backups)->contains(fn ($b) => $b['filename'] === 'test_for_delete.json'));
    }
}
