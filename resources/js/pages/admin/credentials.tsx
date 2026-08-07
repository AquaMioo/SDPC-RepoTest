import { Head, router } from '@inertiajs/react';
import { FilePdfIcon } from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import { document as credentialDocument, update } from '@/routes/admin/credentials';

type Credential = {
    id: number;
    student: {
        id: number | null;
        name: string | null;
        email: string | null;
        accountStatus: string | null;
    };
    school: string | null;
    fileName: string | null;
    fileSize: string | null;
    status: string;
    statusLabel: string;
    reason: string | null;
    checks: Record<string, unknown>;
    submittedAt: string | null;
    reviewedAt: string | null;
    awaitingDecision: boolean;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const STATUS_TAG: Record<string, string> = {
    verified: 'tag tag-accent',
    needs_review: 'tag tag-accent-2',
    pending: 'tag tag-outline',
    rejected: 'tag tag-neutral',
};

/**
 * Student credential review.
 *
 * Submissions land here from the automated verifier; anything it could not
 * decide is sorted to the top with `awaitingDecision` set, so the queue reads
 * as a worklist rather than a log.
 */
export default function AdminCredentials({
    credentials,
}: {
    credentials: Credential[];
}) {
    const decide = (id: number, status: 'verified' | 'rejected') =>
        router.patch(update.url(id), { status }, { preserveScroll: true });

    return (
        <div style={{ maxWidth: 1180, margin: '0 auto', padding: '30px 32px 72px' }}>
            <Head title="Student credentials" />

            <div style={{ marginBottom: 20 }}>
                <h3 style={{ margin: 0 }}>Student credentials</h3>
                <div style={{ fontSize: 13, color: MUTED(55) }}>
                    Verify school documents before students take on projects
                </div>
            </div>

            {credentials.length === 0 && (
                <div className="card elev-sm" style={{ padding: 20 }}>
                    <span style={{ fontSize: 13, color: MUTED(55) }}>
                        No credential submissions yet.
                    </span>
                </div>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {credentials.map((credential) => (
                    <div
                        key={credential.id}
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
                                {credential.student.name ?? 'Deleted account'}
                            </span>
                            <span
                                className={
                                    STATUS_TAG[credential.status] ?? 'tag tag-neutral'
                                }
                            >
                                {credential.statusLabel}
                            </span>
                        </div>

                        <div style={{ fontSize: 11.5, color: MUTED(45) }}>
                            {credential.student.email}
                            {credential.school && ` · ${credential.school}`}
                            {credential.submittedAt &&
                                ` · submitted ${credential.submittedAt}`}
                        </div>

                        {credential.reason && (
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: MUTED(65),
                                }}
                            >
                                {credential.reason}
                            </p>
                        )}

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                marginTop: 4,
                                flexWrap: 'wrap',
                            }}
                        >
                            {credential.fileName && (
                                <Btn
                                    asChild
                                    variant="secondary"
                                    style={{ fontSize: 12.5, padding: '5px 12px' }}
                                >
                                    <a
                                        href={credentialDocument.url(credential.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <FilePdfIcon />
                                        {credential.fileName}
                                        {credential.fileSize &&
                                            ` · ${credential.fileSize}`}
                                    </a>
                                </Btn>
                            )}

                            {credential.awaitingDecision && (
                                <>
                                    <Btn
                                        variant="primary"
                                        style={{ fontSize: 12.5, padding: '5px 12px' }}
                                        onClick={() =>
                                            decide(credential.id, 'verified')
                                        }
                                    >
                                        Verify
                                    </Btn>
                                    <Btn
                                        variant="secondary"
                                        style={{ fontSize: 12.5, padding: '5px 12px' }}
                                        onClick={() =>
                                            decide(credential.id, 'rejected')
                                        }
                                    >
                                        Reject
                                    </Btn>
                                </>
                            )}

                            {credential.reviewedAt && (
                                <span style={{ fontSize: 11.5, color: MUTED(45) }}>
                                    Reviewed {credential.reviewedAt}
                                </span>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
