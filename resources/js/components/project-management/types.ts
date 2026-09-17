/** Where one checklist item stands. See App\Enums\TaskStatus. */
export type TaskStatus = 'open' | 'submitted' | 'verified';

export type Task = {
    id: number;
    position: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    statusLabel: string;
    proofNote: string | null;
    proofUrl: string | null;
    proofName: string | null;
    /** Through the access-checked proof route, never a disk URL. */
    proofHref: string | null;
    /** Why the client sent it back; cleared when the student resubmits. */
    reviewNote: string | null;
    submittedAt: string | null;
    verifiedAt: string | null;
    verifiedBy: string | null;
};

export type PhaseState = 'upcoming' | 'in_progress' | 'done';

export type Phase = {
    id: number;
    position: number;
    title: string;
    description: string | null;
    /** The dates both sides signed — the baseline. ISO (Y-m-d). */
    agreedStartsOn: string | null;
    agreedEndsOn: string | null;
    /** The student's working plan, which the timeline draws. ISO (Y-m-d). */
    startsOn: string | null;
    endsOn: string | null;
    isRescheduled: boolean;
    progress: number;
    verifiedCount: number;
    taskCount: number;
    state: PhaseState;
    tasks: Task[];
};

export type ProgressSummary = {
    progress: number;
    verifiedCount: number;
    submittedCount: number;
    taskCount: number;
    currentPhase: { id: number; title: string } | null;
    nextMilestone: { title: string; dueOn: string | null } | null;
    dueOn: string | null;
};

export type ManagedAgreement = {
    id: number;
    reference: string;
    projectTitle: string;
    /** The other side: the business for a student, the student for a client. */
    counterpart: string;
    studentName: string;
    startsOn: string | null;
    endsOn: string | null;
    summary: ProgressSummary;
    phases: Phase[];
};

export type Application = {
    id: number;
    projectId: number;
    projectTitle: string;
    projectSlug: string;
    client: string;
    status: string;
    statusLabel: string;
    source: string;
    appliedAt: string | null;
    respondedAt: string | null;
    awaitsMyDecision: boolean;
    /** False while the student is already on a project: one at a time. */
    canAccept: boolean;
    canWithdraw: boolean;
    canMessage: boolean;
};

export type ProjectManagementProps = {
    side: 'student' | 'client';
    agreements: {
        id: number;
        reference: string;
        projectTitle: string;
        counterpart: string;
    }[];
    agreement: ManagedAgreement | null;
    can: { manage: boolean; verify: boolean };
    pendingAgreementId: number | null;
    applications: Application[];
};
