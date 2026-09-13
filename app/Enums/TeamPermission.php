<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    case ManageProjects = 'project:manage';
    case ManageApplications = 'application:manage';
    case UpdateClientProfile = 'client-profile:update';

    /**
     * Whether this permission is about running the team itself.
     *
     * The split matters because a client holds every permission in the second
     * group and none in the first. Their team is the business they registered
     * as, not a group they assembled: it is named once at sign up, holds only
     * them, and owns the postings. Renaming it, inviting into it or deleting
     * it are not things the client side does — but managing the projects and
     * applications that hang off it very much are.
     *
     * Keep ManageProjects, ManageApplications and UpdateClientProfile out of
     * this list. Putting them in would take the client module's own screens
     * away from the people it is built for.
     */
    public function isTeamAdministration(): bool
    {
        return match ($this) {
            self::UpdateTeam,
            self::DeleteTeam,
            self::AddMember,
            self::UpdateMember,
            self::RemoveMember,
            self::CreateInvitation,
            self::CancelInvitation => true,
            self::ManageProjects,
            self::ManageApplications,
            self::UpdateClientProfile => false,
        };
    }
}
