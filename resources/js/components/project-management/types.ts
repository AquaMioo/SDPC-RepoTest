/** Where one checklist item stands. See App\Enums\TaskStatus. */
export type TaskStatus = 'open' | 'submitted' | 'verified';

/** Where an ask to move a deadline stands. See App\Enums\DeadlineRequestStatus. */
export type DeadlineRequestStatus = 'pending' | 'approved' | 'declined';

/**
 * The newest ask to move a deadline: waiting on the client, or the client's
 * last answer. Withdrawn asks are never sent.
 */
export type DeadlineRequest = {
    id: number;
    status: DeadlineRequestStatus;
    statusLabel: string;
    /** ISO (Y-m-d). */
    previousOn: string | null;
    proposedOn: string;
    reason: string | null;
    requestedBy: string | null;
    requestedAt: string | null;
    decisionNote: string | null;
};

export type Task = {
    id: number;
    position: number;
    title: string;
    description: string | null;
    /** The deadline, ISO (Y-m-d). Set once; moves only with the client's approval. */
    dueOn: string | null;
    isOverdue: boolean;
    deadlineRequest: DeadlineRequest | null;
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
    /** The last phase, whatever the client named it: its end is the final deadline. */
    isTurnover: boolean;
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
    /** The end of Turnover, ISO (Y-m-d). */
    finalDeadline: string | null;
    /** Negative once the final deadline has passed with the project still running. */
    daysToFinalDeadline: number | null;
    overdueTaskCount: number;
};

export type ManagedAgreement = {
    id: number;
    reference: string;
    projectTitle: string;
    /** The other side: the business for a student, the student for a client. */
    counterpart: string;
    studentName: string;
    isCompleted: boolean;
    completedOn: string | null;
    startsOn: string | null;
    endsOn: string | null;
    summary: ProgressSummary;
    /** The end of Turnover, ISO (Y-m-d), and any ask to move it. */
    finalDeadline: string | null;
    finalDeadlineRequest: DeadlineRequest | null;
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
    /** Finished builds, kept readable. */
    completedAgreements: {
        id: number;
        reference: string;
        projectTitle: string;
        counterpart: string;
        completedOn: string | null;
    }[];
    can: { manage: boolean; verify: boolean; complete: boolean };
    /**
     * The student is tied to a build, their own or their team's, so the
     * screen offers no way out to more work.
     */
    isLocked: boolean;
    pendingAgreementId: number | null;
    applications: Application[];
};
