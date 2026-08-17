<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SavePortfolioItemRequest;
use App\Models\Skill;
use App\Models\StudentPortfolioItem;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Student Background History — the work a student has already shipped.
 *
 * Every method resolves the item through the signed-in student's own profile,
 * so there is no policy to forget: an id belonging to somebody else simply is
 * not found.
 */
class PortfolioItemController extends Controller
{
    /**
     * Add a piece of work.
     */
    public function store(SavePortfolioItemRequest $request, Team $currentTeam): RedirectResponse
    {
        $profile = $this->profileFor($request->user());

        DB::transaction(function () use ($request, $profile): void {
            $item = $profile->portfolioItems()->create([
                ...$this->attributes($request),
                /* New work goes to the end of the student's chosen order. */
                'position' => (int) $profile->portfolioItems()->max('position') + 1,
            ]);

            $item->skills()->sync(Skill::idsForNames($request->array('skills')));
        });

        return back()->with('success', 'Added to your portfolio.');
    }

    /**
     * Edit a piece of work.
     */
    public function update(
        SavePortfolioItemRequest $request,
        Team $currentTeam,
        StudentPortfolioItem $portfolioItem,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $portfolioItem);

        DB::transaction(function () use ($request, $portfolioItem): void {
            $portfolioItem->update($this->attributes($request));

            $portfolioItem->skills()->sync(Skill::idsForNames($request->array('skills')));
        });

        return back()->with('success', 'Portfolio updated.');
    }

    /**
     * Remove a piece of work.
     */
    public function destroy(
        Request $request,
        Team $currentTeam,
        StudentPortfolioItem $portfolioItem,
    ): RedirectResponse {
        $this->authorizeOwnership($request->user(), $portfolioItem);

        $portfolioItem->delete();

        return back()->with('success', 'Removed from your portfolio.');
    }

    /**
     * Get the columns a submission writes.
     *
     * @return array<string, mixed>
     */
    protected function attributes(SavePortfolioItemRequest $request): array
    {
        return [
            'title' => $request->string('title')->toString(),
            'role' => $request->input('role'),
            'description' => $request->input('description'),
            'year' => $request->input('year'),
            'url' => $request->input('url'),
            'repository_url' => $request->input('repository_url'),
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    /**
     * Refuse an item that is not this student's.
     */
    protected function authorizeOwnership(User $student, StudentPortfolioItem $item): void
    {
        abort_unless(
            $item->student_profile_id === $student->studentProfile?->id,
            404,
        );
    }

    /**
     * Get the signed-in student's profile, creating it on first visit.
     */
    protected function profileFor(User $student): StudentProfile
    {
        return StudentProfile::firstOrCreate(['user_id' => $student->id]);
    }
}
