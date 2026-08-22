<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SiteContentKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteContentRequest;
use App\Models\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminContentController extends Controller
{
    /**
     * Save every block of copy.
     *
     * The screen submits all three together, so this writes them in one
     * transaction: a half saved page would leave the copy inconsistent with
     * what the administrator saw when they pressed save.
     */
    public function update(UpdateSiteContentRequest $request): RedirectResponse
    {
        $blocks = $request->blocks();
        $editorId = $request->user()->id;

        DB::transaction(function () use ($blocks, $editorId) {
            foreach ($blocks as $key => $body) {
                SiteContent::query()->updateOrCreate(
                    ['key' => SiteContentKey::from($key)],
                    ['body' => $body, 'updated_by' => $editorId],
                );
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Content saved.'),
        ]);

        return back();
    }
}
