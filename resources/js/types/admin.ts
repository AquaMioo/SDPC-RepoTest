export type UserStatus = 'pending' | 'approved' | 'monitored' | 'deactivated';

export type AdminUserRow = {
    id: number;
    name: string;
    avatarUrl: string | null;
    email: string;
    role: string;
    roleLabel: string;
    status: UserStatus;
    verified: boolean;
    isSelf: boolean;
    credentialStatus: string | null;
    credentialStatusLabel: string | null;
};

export type StatusOption = {
    value: UserStatus;
    label: string;
};

export type AdminStats = {
    totalUsers: number;
    byStatus: Record<UserStatus, number>;
    byRole: Record<string, number>;
    approvedPercentage: number;
    pendingReview: number;
    deactivated: number;
};

/** A row in the posting review queue, built by App\Support\AdminPostingQueue. */
export type AdminPosting = {
    slug: string;
    title: string;
    description: string;
    category: string;
    business: string;
    city: string | null;
    skills: string[];
    status: string;
    statusLabel: string;
    publishedAt: string | null;
    awaitingDecision: boolean;
};

/** The three blocks of copy an administrator maintains. */
export type SiteContentDraft = {
    announcements: string;
    rules: string;
    policies: string;
};
