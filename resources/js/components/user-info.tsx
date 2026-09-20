import { usePage } from '@inertiajs/react';

import UserAvatar from '@/components/sdpc/user-avatar';
import type { Team, User } from '@/types';

type SharedProps = {
    auth?: {
        user?: { id?: number } | null;
        /** Resolved by User::avatarUrl() on the server. */
        avatarUrl?: string | null;
    };
};

export function UserInfo({
    user,
    showEmail = false,
    team = null,
}: {
    user: User;
    showEmail?: boolean;
    team?: Team | null;
}) {
    const page = usePage<SharedProps>();

    /*
     * The picture comes off the shared auth prop, not off `user.avatar`.
     *
     * users.avatar holds only the URL the OAuth provider handed back at sign
     * in; an uploaded photo lives in users.avatar_path, and User::avatarUrl()
     * is what picks between them. Reading the column directly is why this menu
     * kept showing somebody's old Google picture after they had uploaded one.
     *
     * Both callers draw the signed-in account, but the check keeps this honest
     * if a third ever draws somebody else.
     */
    const avatarUrl =
        page.props.auth?.user?.id === user.id
            ? (page.props.auth?.avatarUrl ?? null)
            : ((user.avatar as string | undefined) ?? null);

    return (
        <>
            <UserAvatar name={user.name} avatarUrl={avatarUrl} size={32} />
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {team ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {team.name}
                    </span>
                ) : null}
                {!team && showEmail ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                ) : null}
            </div>
        </>
    );
}
