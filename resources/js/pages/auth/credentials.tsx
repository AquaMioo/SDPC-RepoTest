import { Form, Head, Link } from '@inertiajs/react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Select } from '@/components/sdpc/input';
import { Tag } from '@/components/sdpc/tag';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { store } from '@/routes/credentials';

/** Mirrors App\Enums\CredentialStatus. */
type CredentialStatus = 'pending' | 'needs_review' | 'verified' | 'rejected';

type Submission = {
    school: string;
    fileName: string;
    status: CredentialStatus;
    statusLabel: string;
    reason: string | null;
    submittedAt: string | null;
    canResubmit: boolean;
};

type Props = {
    schools: string[];
    submission?: Submission | null;
};

const MUTED = 'color-mix(in srgb, var(--color-text) 55%, transparent)';

/** Matches StoreStudentCredentialRequest's `mimes` rule. */
const ACCEPTED = '.jpg,.jpeg,.png,.webp,.pdf';

/**
 * The tag colour each status wears. Rejected is the only one that reopens the
 * form, so it is the only one drawn as an outline rather than a filled chip.
 */
const STATUS_VARIANT: Record<
    CredentialStatus,
    'accent' | 'accent-2' | 'neutral' | 'outline'
> = {
    pending: 'neutral',
    needs_review: 'accent-2',
    verified: 'accent',
    rejected: 'outline',
};

/**
 * The student credential step.
 *
 * Students land here straight after signing up — with a password or through
 * Google — and stay until an administrator settles their document. The screen
 * has two faces: the upload form, and the state of the submission already made.
 * Only a rejection reopens the form, which is what `canResubmit` carries.
 */
export default function Credentials({ schools, submission }: Props) {
    const showForm = !submission || submission.canResubmit;

    return (
        <>
            <Head title="Verify your student status" />

            <div
                className="card elev-md"
                style={{
                    width: '100%',
                    maxWidth: 420,
                    padding: 28,
                    gap: 13,
                    position: 'relative',
                }}
            >
                <h4 style={{ margin: 0, textAlign: 'center' }}>
                    Verify your student status
                </h4>

                <p
                    style={{
                        margin: 0,
                        textAlign: 'center',
                        fontSize: 12.5,
                        lineHeight: 1.5,
                        color: MUTED,
                    }}
                >
                    Upload your student ID or enrolment record. We check it
                    before your account can take on projects.
                </p>

                {submission && (
                    <div
                        className="panel"
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                            padding: 14,
                            fontSize: 12.5,
                        }}
                        data-test="credential-submission"
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 10,
                            }}
                        >
                            <span style={{ fontWeight: 600 }}>
                                {submission.school}
                            </span>
                            <Tag variant={STATUS_VARIANT[submission.status]}>
                                {submission.statusLabel}
                            </Tag>
                        </div>

                        <span style={{ color: MUTED, wordBreak: 'break-all' }}>
                            {submission.fileName}
                            {submission.submittedAt &&
                                ` · ${submission.submittedAt}`}
                        </span>

                        {submission.reason && (
                            <p style={{ margin: 0, lineHeight: 1.5 }}>
                                {submission.reason}
                            </p>
                        )}
                    </div>
                )}

                {showForm ? (
                    <Form
                        {...store.form()}
                        disableWhileProcessing
                        style={{ display: 'contents' }}
                    >
                        {({ processing, errors, progress }) => (
                            <>
                                <div className="field">
                                    <label htmlFor="school">School</label>
                                    <Select
                                        id="school"
                                        name="school"
                                        required
                                        tabIndex={1}
                                        defaultValue={submission?.school ?? ''}
                                        aria-invalid={Boolean(errors.school)}
                                    >
                                        <option value="" disabled>
                                            Select your school
                                        </option>
                                        {schools.map((school) => (
                                            <option key={school} value={school}>
                                                {school}
                                            </option>
                                        ))}
                                    </Select>
                                    <InputError
                                        message={errors.school}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>

                                <div className="field">
                                    <label htmlFor="document">Document</label>
                                    <Input
                                        id="document"
                                        name="document"
                                        type="file"
                                        required
                                        tabIndex={2}
                                        accept={ACCEPTED}
                                        aria-invalid={Boolean(errors.document)}
                                        style={{ paddingBlock: 7 }}
                                    />
                                    <p
                                        style={{
                                            fontSize: 11,
                                            margin: '5px 0 0',
                                            color: MUTED,
                                        }}
                                    >
                                        JPG, PNG, WEBP or PDF, up to 8 MB.
                                    </p>
                                    <InputError
                                        message={errors.document}
                                        className="mt-1 text-[11px]"
                                    />
                                </div>

                                {/* The upload can be several megabytes, so the
                                    request exposes real progress rather than a
                                    spinner that sits still. */}
                                {progress && (
                                    <progress
                                        value={progress.percentage ?? 0}
                                        max="100"
                                        style={{ width: '100%', height: 4 }}
                                    >
                                        {progress.percentage ?? 0}%
                                    </progress>
                                )}

                                <Btn
                                    type="submit"
                                    variant="primary"
                                    block
                                    tabIndex={3}
                                    data-test="submit-credential-button"
                                    style={{ paddingBlock: 9 }}
                                >
                                    {processing && <Spinner />}
                                    {submission
                                        ? 'Submit again'
                                        : 'Submit for review'}
                                </Btn>
                            </>
                        )}
                    </Form>
                ) : (
                    <p
                        style={{
                            margin: 0,
                            textAlign: 'center',
                            fontSize: 12.5,
                            lineHeight: 1.5,
                            color: MUTED,
                        }}
                        data-test="credential-locked-notice"
                    >
                        We will email you as soon as this has been reviewed.
                        There is nothing else to do for now.
                    </p>
                )}

                <div
                    style={{
                        textAlign: 'center',
                        fontSize: 12.5,
                        color: MUTED,
                    }}
                >
                    <Btn asChild variant="ghost" style={{ fontSize: 12.5 }}>
                        <Link href={logout()} as="button" tabIndex={4}>
                            Log out
                        </Link>
                    </Btn>
                </div>
            </div>
        </>
    );
}

Credentials.layout = {
    title: 'Verify your student status',
    description:
        'Upload your student ID or enrolment record so we can confirm you are enrolled.',
};
