export type UserStatus = 'pending' | 'approved' | 'monitored' | 'deactivated';

export type AdminUserRow = {
    id: number;
    name: string;
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
