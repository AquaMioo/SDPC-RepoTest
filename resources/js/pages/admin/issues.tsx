import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { update as resolveIssue } from '@/routes/admin/issues';

type IssueAction = 'warn' | 'monitor' | 'remove_access' | 'close_posting';

type Issue = {
    id: number;
    title: string;
    reporter: string;
    reportedUser: string;
    reportedUserStatus: string;
    /** Null unless the complaint is about a posting rather than a person. */
    reportedPosting: {
        title: string;
        statusLabel: string;
        closed: boolean;
    } | null;
    reportedOn: string | null;
    description: string;
    status: string;
    resolved: boolean;
    resolution: string | null;
    handledBy: string | null;
    /** What this report may be closed with — set by IssueResolution. */
    actions: { value: IssueAction; label: string }[];
};

type Props = {
    issues: Issue[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Reports and issues.
 *
 * Real rows: clients and students file these against an account or one of its
 * postings. Every action here sets state that already exists elsewhere —
 * "Place under monitoring" and "Remove access" set the same UserStatus the
 * Users screen sets, "Close posting" the same ProjectStatus the posting queue
 * sets — so a decision means the same thing wherever it is taken.
 */
export default function AdminIssues({ issues }: Props) {
    const [pending, setPending] = useState<{
        issue: Issue;
        action: IssueAction;
    } | null>(null);

    const [processing, setProcessing] = useState(false);

    const resolve = () => {
        if (!pending) {
            return;
        }

        setProcessing(true);

        router.patch(
            resolveIssue.url(pending.issue.id),
            { action: pending.action },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    };

    return (
        <div
            style={{
                maxWidth: 1180,
                margin: '0 auto',
                padding: '30px 32px 72px',
            }}
        >
            <Head title="Reports and issues" />

            <div
                style={{
                    display: 'flex',
                    alignItems: 'flex-end',
                    gap: 16,
                    marginBottom: 20,
                }}
            >
                <div style={{ marginRight: 'auto' }}>
                    <h3 style={{ margin: 0 }}>Reports and issues</h3>
                    <div style={{ fontSize: 13, color: MUTED(55) }}>
                        Review user complaints and take action
                    </div>
                </div>
            </div>

            {issues.length === 0 && (
                <p
                    style={{
                        padding: '11px 13px',
                        marginBottom: 16,
                        borderRadius: 'var(--radius-md)',
                        background: MUTED(5),
                        fontSize: 12,
                        lineHeight: 1.6,
                        color: MUTED(65),
                    }}
                >
                    <b style={{ color: 'var(--color-text)' }}>
                        Nothing reported.
                    </b>{' '}
                    Reports filed by clients and students against an account or
                    a posting arrive here.
                </p>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {issues.map((issue) => (
                    <div
                        key={issue.id}
                        className="card elev-sm"
                        style={{ padding: '18px 20px', gap: 8 }}
                    >
                        <div style={{ display: 'flex', alignItems: 'baseline' }}>
                            <span
                                style={{
                                    fontFamily: 'var(--font-heading)',
                                    fontSize: 16,
                                    marginRight: 'auto',
                                }}
                            >
                                {issue.title}
                            </span>
                            <span
                                className={
                                    issue.resolved
                                        ? 'tag tag-accent'
                                        : 'tag tag-neutral'
                                }
                            >
                                {issue.resolved && issue.resolution
                                    ? `Resolved · ${issue.resolution.toLowerCase()}`
                                    : issue.status}
                            </span>
                        </div>

                        <div style={{ fontSize: 11.5, color: MUTED(45) }}>
                            {issue.reportedUser} ({issue.reportedUserStatus}) ·
                            reported by {issue.reporter}
                            {issue.reportedOn ? ` · ${issue.reportedOn}` : ''}
                            {issue.handledBy
                                ? ` · closed by ${issue.handledBy}`
                                : ''}
                        </div>

                        {issue.reportedPosting && (
                            <div
                                style={{
                                    padding: '8px 11px',
                                    borderRadius: 'var(--radius-md)',
                                    background: MUTED(5),
                                    fontSize: 12,
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                                data-test="reported-posting"
                            >
                                <span className="card-kicker">Posting</span>
                                <span style={{ marginRight: 'auto' }}>
                                    {issue.reportedPosting.title}
                                </span>
                                <span
                                    className={
                                        issue.reportedPosting.closed
                                            ? 'tag tag-neutral'
                                            : 'tag tag-outline'
                                    }
                                >
                                    {issue.reportedPosting.statusLabel}
                                </span>
                            </div>
                        )}

                        <p
                            style={{
                                margin: 0,
                                fontSize: 13,
                                lineHeight: 1.6,
                                color: MUTED(65),
                            }}
                        >
                            {issue.description}
                        </p>

                        {!issue.resolved && (
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    marginTop: 4,
                                    flexWrap: 'wrap',
                                }}
                            >
                                {issue.actions.map((action, index) => (
                                    <Btn
                                        key={action.value}
                                        variant={
                                            index === 0 ? 'primary' : 'secondary'
                                        }
                                        style={{
                                            fontSize: 12.5,
                                            padding: '5px 12px',
                                        }}
                                        onClick={() =>
                                            setPending({
                                                issue,
                                                action: action.value,
                                            })
                                        }
                                    >
                                        {action.label}
                                    </Btn>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {pending && (
                <div
                    className="dialog-backdrop nocturne"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="issue-confirm-title"
                >
                    <div className="dialog">
                        <div className="dialog-title" id="issue-confirm-title">
                            Confirm this action?
                        </div>
                        <div className="dialog-body">
                            {consequenceOf(pending.action, pending.issue)}
                        </div>
                        <div className="dialog-actions">
                            <Btn
                                variant="secondary"
                                disabled={processing}
                                onClick={() => setPending(null)}
                            >
                                Cancel
                            </Btn>
                            <Btn
                                variant="primary"
                                disabled={processing}
                                onClick={resolve}
                            >
                                Confirm
                            </Btn>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Spell out what confirming would actually do.
 *
 * Every one of these is a state another screen also sets, so the wording names
 * where it can be undone rather than implying this queue owns it.
 */
function consequenceOf(action: IssueAction, issue: Issue): string {
    switch (action) {
        case 'warn':
            return `This closes the report against ${issue.reportedUser} with a warning. Their account keeps its access.`;
        case 'monitor':
            return `This puts ${issue.reportedUser} under monitoring and closes the report. They can still sign in and look around, but cannot post, apply, hire or sign until an administrator restores them on the Users page. They may appeal.`;
        case 'remove_access':
            return `This deactivates ${issue.reportedUser} and closes the report. They cannot sign in until an administrator restores them on the Users page.`;
        case 'close_posting':
            return `This takes "${issue.reportedPosting?.title}" off the student board and closes the report. ${issue.reportedUser} keeps their access — close the report against them separately if that is warranted.`;
    }
}
