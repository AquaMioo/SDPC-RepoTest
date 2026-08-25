<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveProfilePhotoRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The student's profile photo.
 *
 * Writes users.avatar_path, the same column the settings screen used to write
 * and the one User::avatarUrl() reads, so the picture changes everywhere at
 * once — the header, the recruit grid, every message. student_profiles carries
 * an unused photo_path from an earlier design; nothing reads it, and this does
 * not start.
 */
class StudentPhotoController extends Controller
{
    /**
     * Replace the photo.
     */
    public function update(SaveProfilePhotoRequest $request, Team $currentTeam): RedirectResponse
    {
        $user = $request->user();

        $replaced = $user->avatar_path;

        $user->avatar_path = $request->file('photo')->store('avatars/'.$user->id, 'public');
        $user->save();

        $this->forget($replaced);

        return back()->with('success', 'Profile photo updated.');
    }

    /**
     * Take the photo down.
     *
     * The Google-supplied `avatar` URL is cleared alongside the uploaded file.
     * Leaving it would mean "Remove photo" quietly restored the picture Google
     * gave us rather than removing anything.
     */
    public function destroy(Request $request, Team $currentTeam): RedirectResponse
    {
        $user = $request->user();

        $replaced = $user->avatar_path;

        $user->forceFill(['avatar_path' => null, 'avatar' => null])->save();

        $this->forget($replaced);

        return back()->with('success', 'Profile photo removed.');
    }

    /**
     * Drop a file that is no longer anybody's photo.
     */
    protected function forget(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
