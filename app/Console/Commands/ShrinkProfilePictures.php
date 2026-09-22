<?php

namespace App\Console\Commands;

use App\Actions\Profile\StoreProfilePicture;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrink the profile pictures uploaded before uploads were shrunk.
 *
 * StoreProfilePicture sizes every new upload; this brings the ones already on
 * disk into line, so the pages that show them stop waiting on megabytes.
 */
class ShrinkProfilePictures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'avatars:shrink {--dry-run : List what would be shrunk without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-encode uploaded profile pictures at the size they are drawn';

    /**
     * Execute the console command.
     */
    public function handle(StoreProfilePicture $storeProfilePicture): int
    {
        $disk = Storage::disk('public');
        $shrunk = 0;
        $savedBytes = 0;

        User::query()->whereNotNull('avatar_path')->each(function (User $user) use ($disk, $storeProfilePicture, &$shrunk, &$savedBytes) {
            $old = $user->avatar_path;

            if (! $disk->exists($old)) {
                return;
            }

            $contents = $disk->get($old);
            [$width, $height] = @getimagesizefromstring($contents) ?: [0, 0];

            if (str_ends_with($old, '.webp') && max($width, $height) <= StoreProfilePicture::MAX_SIDE) {
                return;
            }

            $webp = $storeProfilePicture->shrink($contents);

            if ($webp === null || strlen($webp) >= strlen($contents)) {
                return;
            }

            $shrunk++;
            $savedBytes += strlen($contents) - strlen($webp);

            $this->line(sprintf('#%d  %s  %d KB -> %d KB', $user->id, $old, strlen($contents) / 1024, strlen($webp) / 1024));

            if ($this->option('dry-run')) {
                return;
            }

            $new = 'avatars/'.$user->id.'/'.Str::random(40).'.webp';
            $disk->put($new, $webp);
            $user->forceFill(['avatar_path' => $new])->save();
            $disk->delete($old);
        });

        $this->info(sprintf(
            '%s %d picture(s), saving %d KB.',
            $this->option('dry-run') ? 'Would shrink' : 'Shrank',
            $shrunk,
            $savedBytes / 1024,
        ));

        return self::SUCCESS;
    }
}
