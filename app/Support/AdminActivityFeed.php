<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Appeal;
use App\Models\Issue;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What the administrator's bell shows: the latest things people did.
 *
 * Built from the records themselves rather than from notification rows, so
 * nothing has to remember to notify the admin: new student and client
 * accounts, reports filed, and feedback — appeals against a decision and
 * testimonials a client left. The count on the bell is whatever arrived after
 * the administrator last opened it.
 */
class AdminActivityFeed
{
    /** How many entries the bell's menu lists. */
    public const LIMIT = 15;

    /**
     * The newest entries, and how many the administrator has not seen.
     *
     * @return array{items: list<array{id: string, kind: string, title: string, body: string|null, at: string, ago: string, url: string, unread: bool}>, unread: int}
     */
    public function for(User $admin): array
    {
        $seenAt = $this->seenAt($admin);

        $items = collect()
            ->concat($this->signUps())
            ->concat($this->reports())
            ->concat($this->appeals())
            ->concat($this->testimonials())
            ->sortByDesc(fn (array $item): int => $item['moment']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'kind' => $item['kind'],
                'title' => $item['title'],
                'body' => $item['body'],
                'at' => $item['moment']->toIso8601String(),
                'ago' => $item['moment']->diffForHumans(),
                'url' => $item['url'],
                'unread' => $seenAt === null || $item['moment']->greaterThan($seenAt),
            ]);

        return [
            'items' => $items->all(),
            'unread' => $items->where('unread', true)->count(),
        ];
    }

    /**
     * Remember that the administrator has looked, so the count starts again.
     */
    public function markSeen(User $admin): void
    {
        Cache::forever($this->key($admin), now()->toIso8601String());
    }

    /**
     * When the administrator last opened the bell, if ever.
     */
    protected function seenAt(User $admin): ?Carbon
    {
        $value = Cache::get($this->key($admin));

        return is_string($value) ? Carbon::parse($value) : null;
    }

    protected function key(User $admin): string
    {
        return 'admin.activity.seen.'.$admin->id;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function signUps(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Student, UserRole::Client])
            ->latest()
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'role', 'created_at'])
            ->filter(fn (User $user): bool => $user->created_at !== null)
            ->map(fn (User $user): array => [
                'id' => 'user-'.$user->id,
                'kind' => 'signup',
                'title' => __('New :role account: :name', ['role' => mb_strtolower($user->role->label()), 'name' => $user->name]),
                'body' => null,
                'moment' => $user->created_at,
                'url' => route('admin.users.index'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function reports(): Collection
    {
        return Issue::query()
            ->with(['reporter:id,name', 'reportedUser:id,name'])
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (Issue $issue): bool => $issue->created_at !== null)
            ->map(fn (Issue $issue): array => [
                'id' => 'issue-'.$issue->id,
                'kind' => 'report',
                'title' => __(':reporter reported :reported', [
                    'reporter' => $issue->reporter?->name ?? __('Someone'),
                    'reported' => $issue->reportedUser?->name ?? __('an account'),
                ]),
                'body' => $issue->category->label(),
                'moment' => $issue->created_at,
                'url' => route('admin.issues'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function appeals(): Collection
    {
        return Appeal::query()
            ->with('user:id,name')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (Appeal $appeal): bool => $appeal->created_at !== null)
            ->map(fn (Appeal $appeal): array => [
                'id' => 'appeal-'.$appeal->id,
                'kind' => 'appeal',
                'title' => __(':name filed an appeal', ['name' => $appeal->user?->name ?? __('An account')]),
                'body' => str($appeal->body)->limit(90)->toString(),
                'moment' => $appeal->created_at,
                'url' => route('admin.monitoring'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function testimonials(): Collection
    {
        return Testimonial::query()
            ->with('author:id,name')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (Testimonial $testimonial): bool => $testimonial->created_at !== null)
            ->map(fn (Testimonial $testimonial): array => [
                'id' => 'testimonial-'.$testimonial->id,
                'kind' => 'feedback',
                'title' => __(':name left feedback', ['name' => $testimonial->author?->name ?? __('A client')]),
                'body' => str($testimonial->body)->limit(90)->toString(),
                'moment' => $testimonial->created_at,
                'url' => route('admin.dashboard'),
            ]);
    }
}
