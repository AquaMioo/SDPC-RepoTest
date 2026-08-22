<?php

namespace App\Enums;

/**
 * What someone does on a capstone team.
 *
 * The four job titles are what a team can hand out; Owner is held by whoever
 * created the team and is never assignable. Admin and Member predate the job
 * titles and are kept because rows carrying them still exist — dropping the
 * cases would break casting on every one of them — but they are no longer
 * offered anywhere, so the set drains as teams re-assign.
 */
enum TeamRole: string
{
    case Owner = 'owner';

    case ProjectManager = 'project_manager';
    case QualityAssurance = 'quality_assurance';
    case SystemAnalyst = 'system_analyst';
    case LeadProgrammer = 'lead_programmer';

    /** @deprecated Legacy value; kept so existing memberships still cast. */
    case Admin = 'admin';

    /** @deprecated Legacy value; kept so existing memberships still cast. */
    case Member = 'member';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::ProjectManager => 'Project Manager',
            self::QualityAssurance => 'Quality Assurance',
            self::SystemAnalyst => 'System Analyst',
            self::LeadProgrammer => 'Lead Programmer',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
                TeamPermission::ManageProjects,
                TeamPermission::ManageApplications,
                TeamPermission::UpdateClientProfile,
            ],
            /*
             * The job titles describe what someone does on the team, not what
             * they may do to the team account. Team management stays with the
             * owner until someone asks for it to be shared.
             */
            self::ProjectManager,
            self::QualityAssurance,
            self::SystemAnalyst,
            self::LeadProgrammer,
            self::Member => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::ProjectManager,
            self::QualityAssurance,
            self::SystemAnalyst,
            self::LeadProgrammer,
            self::Member => 1,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * The roles a team can hand out.
     *
     * Owner is excluded because it belongs to whoever created the team, and
     * the two legacy values because nothing should be assigned them again.
     *
     * @return array<self>
     */
    public static function assignableCases(): array
    {
        return [
            self::ProjectManager,
            self::QualityAssurance,
            self::SystemAnalyst,
            self::LeadProgrammer,
        ];
    }

    /**
     * The assignable roles as option rows for a select.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::assignableCases())
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
