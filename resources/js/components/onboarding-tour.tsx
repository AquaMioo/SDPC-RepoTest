import { router } from '@inertiajs/react';
import { useCallback, useEffect, useLayoutEffect, useState } from 'react';
import { createPortal } from 'react-dom';

import { Btn } from '@/components/sdpc/btn';
import { complete as completeOnboarding } from '@/routes/onboarding';

type Step = {
    /** The data-tour anchor on the header (client-layout.tsx). */
    anchor: string;
    title: string;
    body: string;
};

const SHARED_ICONS: Step[] = [
    {
        anchor: 'icon-messages',
        title: 'Messages',
        body: 'Chat and hold video calls with the people you work with. Conversations open once an application is accepted or an invitation is sent.',
    },
    {
        anchor: 'icon-notifications',
        title: 'Notifications',
        body: 'Applications, signatures, payments, deadline requests and announcements arrive here. A dot means something new.',
    },
];

const STEPS: Record<'client' | 'student', Step[]> = {
    client: [
        {
            anchor: 'nav-dashboard',
            title: 'Dashboard',
            body: 'Your overview: the calendar, your project’s progress, the project team and the latest announcement.',
        },
        {
            anchor: 'nav-posting',
            title: 'Posting',
            body: 'Your project postings. Post a new project here (one at a time), edit it, and see who applied.',
        },
        {
            anchor: 'nav-recruit',
            title: 'Recruit',
            body: 'Browse student developers matched to your posting and invite the ones you want to work with.',
        },
        {
            anchor: 'nav-project-management',
            title: 'Project Management',
            body: 'Once both of you sign the agreement, follow the work here: the student submits tasks with proof, you verify them, and you complete or extend the project.',
        },
        {
            anchor: 'nav-agreement',
            title: 'Agreement',
            body: 'Your Memorandum of Agreement and any extension addendum. Read and add to them, sign, or deny them with a reason.',
        },
        {
            anchor: 'nav-team',
            title: 'Team',
            body: 'Invite colleagues to your business and choose what each of them can do.',
        },
        ...SHARED_ICONS,
        {
            anchor: 'icon-profile',
            title: 'Business profile',
            body: 'What students see when they look your business up: your description, logo and details.',
        },
        {
            anchor: 'icon-settings',
            title: 'Settings',
            body: 'Your account: how you sign in, your GCash account for extension payments, and your account status.',
        },
    ],
    student: [
        {
            anchor: 'nav-dashboard',
            title: 'Dashboard',
            body: 'Your overview: the project you are building, your calendar and the latest announcement.',
        },
        {
            anchor: 'nav-posting',
            title: 'Posting',
            body: 'Browse the open project postings and apply to the ones that fit your skills.',
        },
        {
            anchor: 'nav-get-client',
            title: 'Get a Client',
            body: 'Browse the businesses on SDPC and see what each of them is looking for.',
        },
        {
            anchor: 'nav-project-management',
            title: 'Project Management',
            body: 'Once your agreement is signed, list your tasks, submit work with proof, and follow the client’s approvals.',
        },
        {
            anchor: 'nav-agreement',
            title: 'Agreement',
            body: 'Your Memorandum of Agreement and any extension addendum. Read them, sign, ask for changes, or deny them with a reason.',
        },
        {
            anchor: 'nav-team',
            title: 'Team',
            body: 'Build your developer team: invite the teammates who work on your projects with you.',
        },
        ...SHARED_ICONS,
        {
            anchor: 'icon-profile',
            title: 'Your profile',
            body: 'What clients see: your skills, portfolio, education and languages. Keep it up to date to be matched well.',
        },
        {
            anchor: 'icon-settings',
            title: 'Settings',
            body: 'Your account: how you sign in, your GCash account for extension payments, and your account status.',
        },
    ],
};

const GAP = 10;
const CARD_WIDTH = 320;

/**
 * The first-login walkthrough (owner, 2026-10-18): the header's destinations
 * and icons one at a time, each lit up with what it is for. Finishing or
 * skipping it (Escape counts as skipping) records has_completed_onboarding,
 * so it never comes back for this account. A destination missing from the
 * header is passed over.
 */
export function OnboardingTour({ role }: { role: 'client' | 'student' }) {
    const steps = STEPS[role];
    const [index, setIndex] = useState(0);
    const [done, setDone] = useState(false);
    const [rect, setRect] = useState<DOMRect | null>(null);

    const target = useCallback(
        (at: number): HTMLElement | null =>
            document.querySelector<HTMLElement>(
                `[data-tour="${steps[at]?.anchor}"]`,
            ),
        [steps],
    );

    const finish = useCallback(() => {
        setDone(true);
        router.post(
            completeOnboarding.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    }, []);

    /* Moving forward or back passes over a destination the header does not have. */
    const go = useCallback(
        (direction: 1 | -1) => {
            let next = index + direction;

            while (next >= 0 && next < steps.length && target(next) === null) {
                next += direction;
            }

            if (next >= steps.length) {
                finish();
            } else if (next >= 0) {
                setIndex(next);
            }
        },
        [index, steps.length, target, finish],
    );

    /* Start on the first destination the header actually shows. */
    useEffect(() => {
        const timer = window.setTimeout(() => {
            if (target(0) === null) {
                go(1);
            }
        }, 0);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useLayoutEffect(() => {
        if (done) {
            return;
        }

        const element = target(index);

        if (element === null) {
            return;
        }

        element.scrollIntoView({ block: 'nearest', inline: 'center' });

        const measure = () => setRect(element.getBoundingClientRect());

        measure();
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);

        return () => {
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [index, done, target]);

    useEffect(() => {
        if (done) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                finish();
            } else if (event.key === 'ArrowRight') {
                go(1);
            } else if (event.key === 'ArrowLeft') {
                go(-1);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [done, go, finish]);

    if (done || rect === null) {
        return null;
    }

    const step = steps[index];
    const shown = steps.filter((_, at) => target(at) !== null);
    const position = shown.indexOf(step) + 1;
    const isLast = steps
        .slice(index + 1)
        .every((_, at) => target(index + 1 + at) === null);

    const cardLeft = Math.min(
        Math.max(8, rect.left + rect.width / 2 - CARD_WIDTH / 2),
        window.innerWidth - CARD_WIDTH - 8,
    );

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Welcome walkthrough"
            data-test="onboarding-tour"
            style={{ position: 'fixed', inset: 0, zIndex: 1000 }}
        >
            {/* The page dimmed around the lit-up destination; clicks wait for the card. */}
            <div style={{ position: 'absolute', inset: 0 }} />
            <div
                aria-hidden="true"
                style={{
                    position: 'fixed',
                    top: rect.top - 6,
                    left: rect.left - 8,
                    width: rect.width + 16,
                    height: rect.height + 12,
                    borderRadius: 12,
                    boxShadow: '0 0 0 9999px rgba(10, 14, 10, 0.55)',
                    outline: '2px solid var(--color-accent)',
                    pointerEvents: 'none',
                    transition: 'all 0.25s ease',
                }}
            />
            <div
                className="card elev-lg"
                style={{
                    position: 'fixed',
                    top: rect.bottom + GAP + 6,
                    left: cardLeft,
                    width: CARD_WIDTH,
                    maxWidth: 'calc(100vw - 16px)',
                    padding: 16,
                    gap: 8,
                    background: 'var(--color-surface)',
                    color: 'var(--color-text)',
                }}
            >
                <span
                    style={{
                        fontSize: 11,
                        letterSpacing: '0.05em',
                        textTransform: 'uppercase',
                        color: 'color-mix(in srgb, var(--color-text) 55%, transparent)',
                    }}
                >
                    Welcome to SDPC · {position} of {shown.length}
                </span>
                <div style={{ fontSize: 15, fontWeight: 600 }}>
                    {step.title}
                </div>
                <div style={{ fontSize: 13, lineHeight: 1.55 }}>
                    {step.body}
                </div>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                        marginTop: 6,
                    }}
                >
                    <Btn
                        variant="ghost"
                        onClick={finish}
                        style={{ marginRight: 'auto' }}
                        data-test="onboarding-skip"
                    >
                        Skip tour
                    </Btn>
                    {position > 1 && (
                        <Btn variant="ghost" onClick={() => go(-1)}>
                            Back
                        </Btn>
                    )}
                    <Btn
                        variant="primary"
                        onClick={() => (isLast ? finish() : go(1))}
                        data-test="onboarding-next"
                    >
                        {isLast ? 'Finish' : 'Next'}
                    </Btn>
                </div>
            </div>
        </div>,
        document.body,
    );
}
