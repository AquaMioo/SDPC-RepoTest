import { useForm } from '@inertiajs/react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Select, Textarea } from '@/components/sdpc/input';
import { store as reportStore } from '@/routes/reports';

type Props = {
    /** The account being reported. Omitted when a posting is. */
    userId?: number;
    /** Their name, so the dialog can say who this is about. */
    userName?: string;
    /**
     * Set to report a posting instead of an account. The server resolves who
     * is answerable for it — the browser is not trusted to name them.
     */
    projectId?: number;
    /** The posting's title, so the dialog can say what this is about. */
    projectTitle?: string;
    categories: { value: string; label: string }[];
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Report an account, or one of its postings, to the administrators.
 *
 * Filing a report changes nothing about the reported account and takes no
 * posting off the board — it puts a row in the admin queue and says so plainly,
 * so nobody expects a button here to remove someone.
 */
export default function ReportAccountDialog({
    userId,
    userName,
    projectId,
    projectTitle,
    categories,
}: Props) {
    const [open, setOpen] = useState(false);

    const aboutPosting = projectId !== undefined;
    const subject = aboutPosting ? projectTitle : userName;

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            reported_user_id: userId,
            reported_project_id: projectId,
            category: categories[0]?.value ?? '',
            description: '',
        });

    const close = () => {
        setOpen(false);
        clearErrors();
        reset('category', 'description');
    };

    const submit = () => {
        post(reportStore.url(), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    };

    return (
        <>
            <Btn
                variant="secondary"
                style={{ fontSize: 12.5, padding: '5px 12px' }}
                onClick={() => setOpen(true)}
            >
                {aboutPosting ? 'Report posting' : 'Report account'}
            </Btn>

            {open && (
                <div
                    className="dialog-backdrop"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="report-title"
                >
                    <div className="dialog">
                        <div className="dialog-title" id="report-title">
                            Report {subject}
                        </div>

                        <div
                            className="dialog-body"
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 10,
                            }}
                        >
                            <div className="field">
                                <label htmlFor="report-category">Reason</label>
                                <Select
                                    id="report-category"
                                    value={data.category}
                                    onChange={(event) =>
                                        setData('category', event.target.value)
                                    }
                                >
                                    {categories.map((category) => (
                                        <option
                                            key={category.value}
                                            value={category.value}
                                        >
                                            {category.label}
                                        </option>
                                    ))}
                                </Select>
                                <InputError
                                    message={errors.category}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            <div className="field">
                                <label htmlFor="report-description">
                                    What happened?
                                </label>
                                <Textarea
                                    id="report-description"
                                    rows={4}
                                    value={data.description}
                                    onChange={(event) =>
                                        setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.description)}
                                />
                                <InputError
                                    message={errors.description}
                                    className="mt-1 text-[11px]"
                                />
                            </div>

                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 11.5,
                                    lineHeight: 1.6,
                                    color: MUTED(60),
                                }}
                            >
                                This goes to the administrators for review. It
                                does not{' '}
                                {aboutPosting
                                    ? 'take the posting off the board'
                                    : 'remove or notify the account'}
                                .
                            </p>
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
                                onClick={submit}
                            >
                                Submit report
                            </Btn>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
