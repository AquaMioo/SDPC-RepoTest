<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementImagesRequest;
use App\Models\AnnouncementImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Content Management → Announcement: the administrator posts the
 * Announcement as images (owner, 2026-10-18), previewed on the screen before
 * they are posted, and removes them one at a time.
 */
class AdminAnnouncementImageController extends Controller
{
    /**
     * Post the chosen images, after the ones already there.
     */
    public function store(StoreAnnouncementImagesRequest $request): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images');
        $adminId = $request->user()->id;

        DB::transaction(function () use ($files, $adminId): void {
            $position = (int) AnnouncementImage::query()->max('position');

            foreach ($files as $file) {
                AnnouncementImage::query()->create([
                    'path' => $file->store('announcements', AnnouncementImage::DISK),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'position' => ++$position,
                    'uploaded_by' => $adminId,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('{1} Announcement image posted.|[2,*] :count announcement images posted.', count($files), ['count' => count($files)]),
        ]);

        return back();
    }

    /**
     * Take one image down, file and all.
     */
    public function destroy(AnnouncementImage $image): RedirectResponse
    {
        Storage::disk(AnnouncementImage::DISK)->delete($image->path);

        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image removed from the announcement.')]);

        return back();
    }
}
