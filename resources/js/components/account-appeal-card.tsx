import { Form } from '@inertiajs/react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Tag } from '@/components/sdpc/tag';
import { Spinner } from '@/components/ui/spinner';
import { store as fileAppeal } from '@/routes/profile/appeal';

export type AccountStatus = {
    label: string;
    /** True while the account is under monitoring. */
    restricted: boolean;
    /** False for accounts with no decision standing against them. */
    mayAppeal: boolean;
};

export type AppealState = {
    body: string;
    statusLabel: string;
    pending: boolean;
    granted: boolean;
    filedOn: string | null;
    decisionNote: string | null;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Account Information → Review Appeal.
 *
 * Absent unless there is a decision to answer. An approved account rendering
 * an empty "appeal" box would invite people to argue with nothing, and an
 * administrator to read it.
 */
export default function AccountAppealCard({
    accountStatus,
    appeal,
}: {
    accountStatus: AccountStatus;
    appeal: AppealState | null;
}) {
    if (!accountStatus.mayAppeal) {
        return null;
    }

    return (
        <div
            className="card elev-sm"
            style={{ marginTop: 24, padding: 20, gap: 12 }}
            data-test="appeal-card"
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <h6 style={{ margin: 0, marginRight: 'auto' }}>
                    Account status
                </h6>
                <Tag variant="outline">{accountStatus.label}</Tag>
            </div>

            <p
                style={{
                    margin: 0,
                    fontSize: 12.5,
                    lineHeight: 1.55,
                    color: MUTED(60),
                }}
            >
                {accountStatus.restricted
                    ? 'Your account is under review. You can still sign in, look around and talk to the people you are working with, but posting, applying, hiring and signing are on hold until an administrator decides.'
                    : 'Your account has been deactivated by an administrator.'}
            </p>

            {appeal && (
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
                    data-test="existing-appeal"
                >
                    <div style={{ display: 'flex', gap: 8 }}>
                        <span className="card-kicker" style={{ marginRight: 'auto' }}>
                            Your appeal
                            {appeal.filedOn ? ` · ${appeal.filedOn}` : ''}
                        </span>
                        <Tag variant={appeal.granted ? 'accent' : 'neutral'}>
                            {appeal.statusLabel}
                        </Tag>
                    </div>

                    <div style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                        {appeal.body}
                    </div>

                    {appeal.decisionNote && (
                        <div style={{ color: MUTED(65) }}>
                            <b>Administrator:</b> {appeal.decisionNote}
                        </div>
                    )}
                </div>
            )}

            {appeal?.pending ? (
                <span style={{ fontSize: 12, color: MUTED(60) }}>
                    An administrator will read this and reply by email. There is
                    nothing more to do for now.
                </span>
            ) : (
                <Form
                    {...fileAppeal.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['body']}
                    disableWhileProcessing
                    style={{ display: 'contents' }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="field">
                                <label htmlFor="appeal-body">
                                    {appeal ? 'Appeal again' : 'Review appeal'}
                                </label>
                                <textarea
                                    id="appeal-body"
                                    name="body"
                                    rows={4}
                                    maxLength={2000}
                                    placeholder="Explain your side. An administrator reads this alongside the reports on your account."
                                    aria-invalid={Boolean(errors.body)}
                                />
                                <InputError
                                    message={errors.body}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            <Btn
                                type="submit"
                                variant="primary"
                                style={{ alignSelf: 'start' }}
                                data-test="submit-appeal-button"
                            >
                                {processing && <Spinner />}
                                Submit appeal
                            </Btn>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
