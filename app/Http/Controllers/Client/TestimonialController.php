<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SaveTestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TestimonialController extends Controller
{
    /**
     * Save what the business wants to say, replacing anything it said before.
     */
    public function update(SaveTestimonialRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        Testimonial::updateOrCreate(
            ['team_id' => $team->id],
            [
                ...$request->validated(),
                'user_id' => $request->user()->id,
            ],
        );

        return back()->with('success', 'Your testimonial is now on the landing page.');
    }

    /**
     * Take the testimonial down.
     *
     * The only way one ever leaves the landing page — nothing expires it and
     * no administrator prunes it.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        Gate::authorize('update', $team->clientProfile);

        $team->testimonial?->delete();

        return back()->with('success', 'Your testimonial has been removed.');
    }
}
