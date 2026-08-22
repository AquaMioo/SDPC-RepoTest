import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Tag } from '@/components/sdpc/tag';
import { update as reviewAppeal } from '@/routes/admin/appeals';
import { index as adminUsers } from '@/routes/admin/users';

type Appeal = {
    id: number;
    body: string;
    status: string;
    statusLabel: string;
    /** What the account was in when it wrote this, not what it is now. */
    accountStatusLabel: string;
    filedOn: string | null;
    decided: boolean;
    decisionNote: string | null;
    reviewedBy: string | null;
    reviewedOn: string | null;
};

type Account = {
    id: number;
    name: string;
    avatarUrl: string | null;
    email: string;
    roleLabel: string;
    status: string;
    statusLabel: string;
    since: string | null;
    openReports: number;
    appeal: Appeal | null;
};

type Props = {
    accounts: Account[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Accounts an administrator has held back, and what they have said about it.
 *
 * Monitoring is not a punishment and not an approval — it is the middle state
 * for an account worth keeping an eye on. It now costs the account something
 * (see EnsureAccountIsNotMonitored), so this is also where the answer to that
 * arrives: an account that thinks the decision is wrong writes an appeal, and
 * it is granted or denied here.
 *
 * Deactivated accounts are listed alongside. They cannot reach their own
 * settings to appeal, so the guest page at /appeal is their only door.
 */
export default function AdminMonitoring({ accounts }: Props) {
    const [pending, setPending] = useState<{
        account: Account;
        decision: 'grant' | 'deny';
    } | null>(null);

    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const waiting = accounts.filter(
        (account) => account.appeal !== null && !account.appeal.decided,
    ).length;

    const close = () => {
        setPending(null);
        setNote('');
    };

    const decide = () => {
        if (!pending?.account.appeal) {
            return;
        }

        setProcessing(true);

        router.patch(
            reviewAppeal.url(pending.account.appeal.id),
            { decision: pending.decision, note },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    close();
                },
            },
        );
    };

    return (
        <div
            style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}
        >
            <Head title="Monitoring" />

            <div style={{ marginBottom: 20 }}>
                <h3 style={{ margin: 0 }}>Monitoring</h3>
                <div style={{ fontSize: 13, color: MUTED(55) }}>
                    Accounts held back, and the appeals they have written
                    {waiting > 0 ? ` — ${waiting} waiting` : ''}
                </div>
            </div>

            {accounts.length === 0 ? (
                <p
                    style={{
                        padding: '11px 13px',
                        borderRadius: 'var(--radius-md)',
                        background: MUTED(5),
                        fontSize: 12,
                        lineHeight: 1.6,
                        color: MUTED(65),
                    }}
                    data-test="no-monitored-accounts"
                >
                    <b style={{ color: 'var(--color-text)' }}>
                        Nobody is being held back.
                    </b>{' '}
                    Set an account to Monitored or Deactivated on the{' '}
                    <Link href={adminUsers.url()}>Users</Link> page and it will
                    appear here.
                </p>
            ) : (
                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 12,
                    }}
                >
                    {accounts.map((account) => (
                        <div
                            key={account.id}
                            className="card elev-sm"
                            style={{ padding: '16px 18px', gap: 12 }}
                            data-test="monitored-account"
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 12,
                                    flexWrap: 'wrap',
                                }}
                            >
                                <span
                                    style={{
                                        display: 'grid',
                                        placeItems: 'center',
                                        width: 34,
                                        height: 34,
                                        borderRadius: '50%',
                                        overflow: 'hidden',
                                        flex: 'none',
                                        background: 'var(--color-accent-800)',
                                        color: 'var(--color-accent-200)',
                                        fontSize: 13,
                                    }}
                                >
                                    {account.avatarUrl ? (
                                        <img
                                            src={account.avatarUrl}
                                            alt=""
                                            style={{
                                                width: '100%',
                                                height: '100%',
                                                objectFit: 'cover',
                                            }}
                                        />
                                    ) : (
                                        account.name.charAt(0).toUpperCase()
                                    )}
                                </span>

                                <div style={{ minWidth: 0, marginRight: 'auto' }}>
                                    <div style={{ fontSize: 14 }}>
                                        {account.name}
                                    </div>
                                    <div
                                        style={{
                                            fontSize: 11.5,
                                            color: MUTED(50),
                                        }}
                                    >
                                        {account.email} · {account.roleLabel}
                                        {account.since
                                            ? ` · since ${account.since}`
                                            : ''}
                                    </div>
                                </div>

                                {/* The usual reason an account is here. */}
                                {account.openReports > 0 && (
                                    <span className="tag tag-neutral">
                                        {account.openReports} open report
                                        {account.openReports === 1 ? '' : 's'}
                                    </span>
                                )}

                                <Tag variant="outline">
                                    {account.statusLabel}
                                </Tag>
                            </div>

                            {account.appeal === null ? (
                                <div
                                    style={{
                                        fontSize: 12.5,
                                        color: MUTED(55),
                                    }}
                                    data-test="no-appeal"
                                >
                                    No appeal written yet.
                                </div>
                            ) : (
                                <div
                                    style={{
                                        padding: '11px 13px',
                                        borderRadius: 'var(--radius-md)',
                                        background: MUTED(5),
                                        fontSize: 12.5,
                                        lineHeight: 1.6,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        gap: 6,
                                    }}
                                    data-test="appeal"
                                >
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        <span
                                            className="card-kicker"
                                            style={{ marginRight: 'auto' }}
                                        >
                                            Appealing{' '}
                                            {account.appeal.accountStatusLabel}
                                            {account.appeal.filedOn
                                                ? ` · ${account.appeal.filedOn}`
                                                : ''}
                                        </span>
                                        <Tag
                                            variant={
                                                account.appeal.decided
                                                    ? 'neutral'
                                                    : 'accent'
                                            }
                                        >
                                            {account.appeal.statusLabel}
                                        </Tag>
                                    </div>

                                    <div
                                        style={{
                                            whiteSpace: 'pre-wrap',
                                            wordBreak: 'break-word',
                                        }}
                                    >
                                        {account.appeal.body}
                                    </div>

                                    {account.appeal.decisionNote && (
                                        <div style={{ color: MUTED(60) }}>
                                            <b>{account.appeal.reviewedBy}:</b>{' '}
                                            {account.appeal.decisionNote}
                                            {account.appeal.reviewedOn
                                                ? ` (${account.appeal.reviewedOn})`
                                                : ''}
                                        </div>
                                    )}
                                </div>
                            )}

                            <div
                                style={{
                                    display: 'flex',
                                    gap: 8,
                                    flexWrap: 'wrap',
                                    alignItems: 'center',
                                }}
                            >
                                {account.appeal && !account.appeal.decided && (
                                    <>
                                        <Btn
                                            variant="primary"
                                            style={{
                                                fontSize: 12.5,
                                                padding: '5px 12px',
                                            }}
                                            onClick={() =>
                                                setPending({
                                                    account,
                                                    decision: 'grant',
                                                })
                                            }
                                        >
                                            Grant and restore
                                        </Btn>
                                        <Btn
                                            variant="secondary"
                                            style={{
                                                fontSize: 12.5,
                                                padding: '5px 12px',
                                            }}
                                            onClick={() =>
                                                setPending({
                                                    account,
                                                    decision: 'deny',
                                                })
                                            }
                                        >
                                            Deny
                                        </Btn>
                                    </>
                                )}

                                <Link
                                    href={adminUsers.url()}
                                    style={{
                                        fontSize: 12.5,
                                        marginLeft: 'auto',
                                    }}
                                >
                                    Change status
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {pending && (
                <div
                    className="dialog-backdrop nocturne"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="appeal-confirm-title"
                >
                    <div className="dialog">
                        <div className="dialog-title" id="appeal-confirm-title">
                            {pending.decision === 'grant'
                                ? `Restore ${pending.account.name}?`
                                : `Deny ${pending.account.name}?`}
                        </div>

                        <div
                            className="dialog-body"
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                            }}
                        >
                            <span>
                                {pending.decision === 'grant'
                                    ? 'This sets the account back to approved and closes the appeal. Everything it was held back from works again.'
                                    : 'This leaves the decision exactly where it stands and closes the appeal. Say why — the account is told this.'}
                            </span>

                            <div className="field">
                                <label htmlFor="appeal-note">
                                    {pending.decision === 'grant'
                                        ? 'Note (optional)'
                                        : 'Reason'}
                                </label>
                                <textarea
                                    id="appeal-note"
                                    rows={3}
                                    maxLength={1000}
                                    value={note}
                                    onChange={(event) =>
                                        setNote(event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="dialog-actions">
                            <Btn
                                variant="secondary"
                                disabled={processing}
                                onClick={close}
                            >
                                Cancel
                            </Btn>
                            <Btn
                                variant="primary"
                                disabled={processing}
                                onClick={decide}
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
