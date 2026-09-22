<?php

namespace App\Actions\Profile;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Store a profile picture at the size it is actually drawn.
 *
 * Pictures went to disk exactly as uploaded — an 820 KB phone photo for a
 * circle drawn at 40 pixels — and every page that shows people carries
 * several. sdpc.tech sends everything through a home connection that manages
 * a few hundred KB a second on a good minute, so one such picture took 2-4
 * seconds to arrive (2026-09-22). Shrunk to 512 pixels as WebP it is a few
 * tens of KB and looks the same.
 */
class StoreProfilePicture
{
    /**
     * The longest side kept, in pixels.
     *
     * The largest avatar is drawn at about 160 CSS pixels; 512 stays sharp on
     * a 3x screen.
     */
    public const MAX_SIDE = 512;

    protected const QUALITY = 82;

    /**
     * Store an uploaded picture for the user and return its path on the
     * public disk.
     */
    public function handle(UploadedFile $file, User $user): string
    {
        $shrunk = $this->shrink((string) file_get_contents($file->getRealPath()));

        /* Not something GD can read: keep it as uploaded rather than lose it. */
        if ($shrunk === null) {
            return $file->store('avatars/'.$user->id, 'public')
                ?: throw new RuntimeException('The profile picture could not be stored.');
        }

        $path = 'avatars/'.$user->id.'/'.Str::random(40).'.webp';

        Storage::disk('public')->put($path, $shrunk);

        return $path;
    }

    /**
     * Re-encode image bytes as WebP no larger than MAX_SIDE, or null when they
     * are not an image GD understands.
     */
    public function shrink(string $contents): ?string
    {
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            return null;
        }

        $image = $this->upright($image, $contents);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC) ?: $image;
        }

        /* Keep a transparent PNG transparent. */
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * Turn a phone photo the way its camera meant it.
     *
     * Phones store a portrait photo sideways plus an EXIF note saying so, and
     * GD ignores the note. Only where the exif extension exists; without it
     * the picture is kept as stored.
     */
    protected function upright(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));

        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
