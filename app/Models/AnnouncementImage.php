<?php

namespace App\Models;

use Database\Factories\AnnouncementImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One picture of the Announcement the administrator posts on Content
 * Management. At most MAX of them; the dashboards slide through them in
 * position order.
 *
 * @property int $id
 * @property string $path
 * @property string|null $original_name
 * @property int $position
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $uploader
 */
#[Fillable(['path', 'original_name', 'position', 'uploaded_by'])]
class AnnouncementImage extends Model
{
    /** @use HasFactory<AnnouncementImageFactory> */
    use HasFactory;

    /** How many pictures one Announcement takes (owner, 2026-10-18). */
    public const MAX = 10;

    /** The disk the files live on, under a directory that is not published. */
    public const DISK = 'public';

    /**
     * Get the administrator who posted it.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the address the dashboards load it from, through the checked route.
     */
    public function url(): string
    {
        return route('announcements.images.show', $this);
    }

    /**
     * The Announcement as the dashboards draw it: its pictures in order and
     * when it last changed, or null while nothing is posted.
     *
     * @return array{images: list<array{id: int, url: string}>, updatedAt: string|null}|null
     */
    public static function forDashboard(): ?array
    {
        /** @var Collection<int, self> $images */
        $images = self::query()->orderBy('position')->orderBy('id')->get();

        if ($images->isEmpty()) {
            return null;
        }

        return [
            'images' => $images
                ->map(fn (self $image): array => ['id' => $image->id, 'url' => $image->url()])
                ->values()
                ->all(),
            'updatedAt' => $images->max('created_at')?->diffForHumans(),
        ];
    }
}
