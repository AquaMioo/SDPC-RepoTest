<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AdminUserController extends Controller
{
    /**
     * Show the user account management screen.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()
                ->with('latestStudentCredential')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'email', 'role', 'status', 'avatar', 'email_verified_at'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    'status' => $user->status->value,
                    'verified' => $user->email_verified_at !== null,
                    'isSelf' => $user->is($request->user()),
                    'credentialStatus' => $user->latestStudentCredential?->status->value,
                    'credentialStatusLabel' => $user->latestStudentCredential?->status->label(),
                ])
                ->values()
                ->all(),
            'statuses' => array_map(fn (UserStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], UserStatus::cases()),
        ]);
    }

    /**
     * Approve, monitor or deactivate an account.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        // An administrator locking themselves out mid-session would leave the
        // portal unreachable, so self-changes are refused outright.
        abort_if($user->is($request->user()), HttpResponse::HTTP_FORBIDDEN, 'You cannot change your own account status.');

        $status = $request->status();

        $user->forceFill(['status' => $status])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name is now :status.', ['name' => $user->name, 'status' => mb_strtolower($status->label())]),
        ]);

        return back();
    }
}
