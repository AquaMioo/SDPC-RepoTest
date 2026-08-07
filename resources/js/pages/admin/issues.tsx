import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { index as adminUsers } from '@/routes/admin/users';

type Issue = {
    id: number;
    title: string;
    reporter: string;
    reportedOn: string;
    description: string;
    status: string;
    resolved: boolean;
};

const SAMPLE: Issue[] = [
    {
        id: 1,
        title: 'Duplicate account report',
        reporter: 'adi',
        reportedOn: '11 Mar 2026',
        description:
            'User reported a second account created under the same email domain and student number.',
        status: 'Pending',
        resolved: false,
    },
    {
        id: 2,
        title: 'Delayed project delivery',
        reporter: 'adi',
        reportedOn: '09 Mar 2026',
        description:
            'Client mentioned an unfinished milestone past its deadline and requested platform intervention.',
        status: 'In review',
        resolved: false,
    },
];

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Reports and issues.
 *
 * The reports below are sample rows held in component state — there is no
 * issues table yet. Resolving one changes nothing on any account; the notice
 * points at the User page, where deactivation is genuinely wired.
 */
export default function AdminIssues() {
    const [issues, setIssues] = useState<Issue[]>(SAMPLE);
    const [pending, setPending] = useState<{
        issue: Issue;
        action: 'warn' | 'remove';
    } | null>(null);

    const resolve = () => {
        if (!pending) {
            return;
        }

        setIssues((current) =>
            current.map((issue) =>
                issue.id === pending.issue.id
                    ? {
                          ...issue,
                          resolved: true,
                          status:
                              pending.action === 'warn'
                                  ? 'Resolved · user warned'
                                  : 'Resolved · access removed',
                      }
                    : issue,
            ),
        );
        setPending(null);
    };

    return (
        <div style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}>
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
                <b style={{ color: 'var(--color-text)' }}>Sample data.</b> These
                reports live in the browser until an issues table exists.
                Resolving one here does not change any account — to actually
                revoke access use Deactivate on the{' '}
                <Link href={adminUsers.url()}>Users</Link> page, which is fully
                wired.
            </p>

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
                                {issue.status}
                            </span>
                        </div>

                        <div style={{ fontSize: 11.5, color: MUTED(45) }}>
                            Reported by {issue.reporter} · {issue.reportedOn}
                        </div>

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
                            <div style={{ display: 'flex', gap: 8, marginTop: 4 }}>
                                <Btn
                                    variant="primary"
                                    style={{ fontSize: 12.5, padding: '5px 12px' }}
                                    onClick={() => setPending({ issue, action: 'warn' })}
                                >
                                    Warn user
                                </Btn>
                                <Btn
                                    variant="secondary"
                                    style={{ fontSize: 12.5, padding: '5px 12px' }}
                                    onClick={() =>
                                        setPending({ issue, action: 'remove' })
                                    }
                                >
                                    Remove access
                                </Btn>
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
                            {pending.action === 'warn'
                                ? 'This marks the report as resolved with a warning. Sending the actual notice needs the issues table first.'
                                : 'This marks the report as resolved. It does not revoke access — use Deactivate on the Users page for that.'}
                        </div>
                        <div className="dialog-actions">
                            <Btn
                                variant="secondary"
                                onClick={() => setPending(null)}
                            >
                                Cancel
                            </Btn>
                            <Btn variant="primary" onClick={resolve}>
                                Confirm
                            </Btn>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
