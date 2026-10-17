<?php

namespace App\Http\Controllers;

use App\Models\AnnouncementImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an Announcement image to any signed-in account.
 *
 * The announcements/ directory is deliberately not published
 * (config/filesystems.php links per directory), so the dashboards load each
 * picture through here.
 */
class AnnouncementImageController extends Controller
{
    /**
     * Stream one image.
     */
    public function __invoke(AnnouncementImage $image): StreamedResponse
    {
        $disk = Storage::disk(AnnouncementImage::DISK);

        abort_unless($disk->exists($image->path), Response::HTTP_NOT_FOUND);

        return $disk->response($image->path, $image->original_name, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
