<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppealStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewAppealRequest;
use App\Models\Appeal;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminMonitoringController extends Controller
{
    /**
     * Show the accounts an administrator has put under watch.
     *
     * "Monitored" was a status with no consequence anywhere — a label an
     * administrator could set and then never see again. This screen was the
     * first half of giving it one; EnsureAccountIsNotMonitored is the other,
     * and now that the status actually costs the account something, this is
     * also where they get to answer it.
     *
     * Deactivated accounts are listed alongside. They cannot sign in to appeal
     * from their settings, so the guest page at /appeal is their only door —
     * and this is where what they wrote arrives.
     */
    public function index(): Response
    {
        $held = User::query()
            ->with(['latestAppeal.reviewer:id,name'])
            ->whereIn('status', [UserStatus::Monitored, UserStatus::Deactivated])
            ->get();

        /*
         * Open reports per account, counted in one query rather than one per
         * row. An account with reports still waiting is the case an
         * administrator most needs to see.
         */
        $openReports = Issue::query()
            ->open()
            ->whereIn('reported_user_id', $held->pluck('id'))
            ->selectRaw('reported_user_id, COUNT(*) as total')
            ->groupBy('reported_user_id')
            ->pluck('total', 'reported_user_id');

        $accounts = $held
            /*
             * Anyone waiting on a decision first, oldest appeal at the top, so
             * the screen reads as work to do rather than a list of names.
             */
            ->sortBy(fn (User $user): array => [
                $user->latestAppeal?->status === AppealStatus::Pending ? 0 : 1,
                $user->latestAppeal?->created_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->values()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'avatarUrl' => $user->avatarUrl(),
                'email' => $user->email,
                'roleLabel' => $user->role->label(),
                'status' => $user->status->value,
                'statusLabel' => $user->status->label(),
                'since' => $user->updated_at?->diffForHumans(),
                'openReports' => (int) ($openReports[$user->id] ?? 0),
                'appeal' => $this->appealRow($user->latestAppeal),
            ])
            ->all();

        return Inertia::render('admin/monitoring', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Decide an appeal.
     *
     * Granting restores the account to approved rather than to whatever it was
     * before: the administrator has just read the case and decided it is fine,
     * which is what approved means. Denying leaves the decision exactly where
     * it stands and records why, so the account is told something.
     */
    public function decide(ReviewAppealRequest $request, Appeal $appeal): RedirectResponse
    {
        $grants = $request->grants();

        if ($grants) {
            $appeal->user->forceFill(['status' => UserStatus::Approved])->save();
        }

        /*
         * Assigned rather than mass-assigned: the decision fields are the
         * administrator's own record and are deliberately not fillable, so an
         * update() array would drop them without complaint.
         */
        $appeal->status = $grants ? AppealStatus::Granted : AppealStatus::Denied;
        $appeal->decision_note = $request->validated('note');
        $appeal->reviewed_by = $request->user()->id;
        $appeal->reviewed_at = now();
        $appeal->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $grants
                ? __(':name has been restored and the appeal closed.', ['name' => $appeal->user->name])
                : __('The appeal was denied and the decision left standing.'),
        ]);

        return back();
    }

    /**
     * Describe an appeal for the screen, or nothing if none was written.
     *
     * @return array<string, mixed>|null
     */
    private function appealRow(?Appeal $appeal): ?array
    {
        if ($appeal === null) {
            return null;
        }

        return [
            'id' => $appeal->id,
            'body' => $appeal->body,
            'status' => $appeal->status->value,
            'statusLabel' => $appeal->status->label(),
            /* What the account was in when it wrote this, not what it is now. */
            'accountStatusLabel' => $appeal->account_status->label(),
            'filedOn' => $appeal->created_at?->format('d M Y'),
            'decided' => $appeal->isDecided(),
            'decisionNote' => $appeal->decision_note,
            'reviewedBy' => $appeal->reviewer?->name,
            'reviewedOn' => $appeal->reviewed_at?->format('d M Y'),
        ];
    }
}
