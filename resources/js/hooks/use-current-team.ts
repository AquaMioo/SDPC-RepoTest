import { usePage } from '@inertiajs/react';

export type CurrentTeam = {
    slug: string;
    name: string;
};

type SharedProps = {
    currentTeam?: CurrentTeam | null;
};

/**
 * Get the acting team, which every Client Module route is scoped to.
 *
 * Wayfinder types `current_team` as a required argument, so client pages pass
 * this slug rather than relying on an implicit default.
 */
export function useCurrentTeam(): CurrentTeam {
    const currentTeam = usePage<SharedProps>().props.currentTeam;

    if (!currentTeam) {
        throw new Error(
            'A Client Module page rendered without a current team. Every client route sits behind EnsureTeamMembership, so this means the page was rendered outside that middleware.',
        );
    }

    return currentTeam;
}
