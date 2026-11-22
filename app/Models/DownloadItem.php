<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $title
 * @property string $media_type
 * @property ?string $source_url
 * @property ?string $destination_path
 * @property int $total_bytes
 * @property int $downloaded_bytes
 * @property string $status
 * @property ?string $workflow_stage
 * @property ?string $organize_status
 * @property ?string $organized_path
 * @property ?int $indexed_id
 * @property int $speed_bytes_sec
 * @property int $num_seeders
 * @property int $connections
 * @property int $upload_speed_bytes_sec
 * @property ?string $error_message
 * @property ?string $error_details
 * @property string $download_type
 * @property ?string $destination_folder
 * @property ?array<int, mixed> $torrent_files
 * @property ?array<int, mixed> $selected_files
 * @property ?string $info_hash
 * @property ?string $aria2_gid
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?MediaItem $mediaItem
 */
class DownloadItem extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_bytes' => 'integer',
            'downloaded_bytes' => 'integer',
            'speed_bytes_sec' => 'integer',
            'num_seeders' => 'integer',
            'connections' => 'integer',
            'upload_speed_bytes_sec' => 'integer',
            'indexed_id' => 'integer',
            'torrent_files' => 'array',
            'selected_files' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'indexed_id');
    }
}
