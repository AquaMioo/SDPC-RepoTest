<?php

namespace App\Models;

use Database\Factories\ProjectAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_in_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User|null $uploader
 */
#[Fillable([
    'project_id', 'uploaded_by', 'disk', 'path', 'original_name',
    'mime_type', 'size_in_bytes',
])]
class ProjectAttachment extends Model
{
    /** @use HasFactory<ProjectAttachmentFactory> */
    use HasFactory;

    /**
     * Get the project the file is attached to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get a temporary, signed URL for downloading the file.
     */
    public function temporaryUrl(): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(15));
    }

    /**
     * Get the human-readable file size shown beside the attachment.
     */
    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size_in_bytes, precision: 0);
    }

    /**
     * Remove the underlying file when the record is deleted.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleted(function (ProjectAttachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}
