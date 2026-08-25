import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    BuildingsIcon,
    FacebookLogoIcon,
    LinkSimpleIcon,
    SealCheckIcon,
} from '@phosphor-icons/react';

import ReportAccountDialog from '@/components/report-account-dialog';
import { Btn } from '@/components/sdpc/btn';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { show as boardShow } from '@/routes/student/board';
import { index as clientsIndex } from '@/routes/student/clients';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Props = {
    business: {
        teamSlug: string;
        businessName: string;
        description: string | null;
        ownerName: string | null;
        city: string | null;
        province: string | null;
        websiteUrl: string | null;
        facebookUrl: string | null;
        verifiedAt: string | null;
        ownerUserId: number | null;
    };
    reportCategories: { value: string; label: string }[];
    postings: {
        slug: string;
        title: string;
        category: string;
        industry: string | null;
        isAcceptingApplications: boolean;
        skills: string[];
    }[];
};

/**
 * One business, as a student sees it.
 *
 * No phone number and no email address on purpose: a thread on this platform
 * opens off an application, and publishing contact details here would route
 * around that for every verified business at once.
 */
export default function StudentClient({
    business,
    postings,
    reportCategories,
}: Props) {
    const team = useCurrentTeam();

    return (
        <>
            <Head title={business.businessName} />

            <div
                style={{
                    maxWidth: 1060,
                    margin: '0 auto',
                    padding: '24px clamp(16px, 4vw, 32px) 72px',
                }}
            >
                <Btn asChild variant="ghost" style={{ marginBottom: 16 }}>
                    <Link href={clientsIndex.url(team.slug)}>
                        <ArrowLeftIcon />
                        Back to the client list
                    </Link>
                </Btn>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 20,
                        marginBottom: 26,
                    }}
                >
                    <span
                        style={{
                            width: 88,
                            height: 88,
                            borderRadius: '50%',
                            background: 'var(--color-accent-800)',
                            color: 'var(--color-accent-200)',
                            display: 'grid',
                            placeItems: 'center',
                            fontSize: 36,
                            flex: 'none',
                        }}
                    >
                        <BuildingsIcon />
                    </span>

                    <div style={{ paddingBottom: 6, marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>{business.businessName}</h3>
                        <div style={{ fontSize: 13, color: MUTED(68) }}>
                            {[
                                'Client',
                                business.ownerName,
                                [business.city, business.province]
                                    .filter(Boolean)
                                    .join(', ') || null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </div>
                    </div>

                    <Tag variant="accent" style={{ marginBottom: 10 }}>
                        <SealCheckIcon style={{ marginRight: 5 }} />
                        Verified business
                    </Tag>
                </div>

                <div
                    className="split"
                    style={{ ['--rail' as string]: '270px', gap: 28 }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 18,
                        }}
                    >
                        <div>
                            <h6 style={{ margin: '0 0 8px' }}>
                                About the business
                            </h6>
                            <p
                                style={{
                                    margin: 0,
                                    fontSize: 13,
                                    lineHeight: 1.6,
                                    color: MUTED(65),
                                }}
                            >
                                {business.description ??
                                    'This business has not written a description yet.'}
                            </p>

                            {business.ownerUserId !== null && (
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'flex-end',
                                        marginTop: 12,
                                    }}
                                >
                                    <ReportAccountDialog
                                        userId={business.ownerUserId}
                                        userName={business.businessName}
                                        categories={reportCategories}
                                    />
                                </div>
                            )}
                        </div>

                        {(business.websiteUrl || business.facebookUrl) && (
                            <Panel style={{ padding: 16, gap: 8 }}>
                                <PanelKicker>Links</PanelKicker>
                                {business.websiteUrl && (
                                    <a
                                        href={business.websiteUrl}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            fontSize: 12.5,
                                        }}
                                    >
                                        <LinkSimpleIcon />
                                        Website
                                    </a>
                                )}
                                {business.facebookUrl && (
                                    <a
                                        href={business.facebookUrl}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            fontSize: 12.5,
                                        }}
                                    >
                                        <FacebookLogoIcon />
                                        Facebook
                                    </a>
                                )}
                            </Panel>
                        )}

                        {business.verifiedAt && (
                            <Panel style={{ padding: 16, gap: 6 }}>
                                <PanelKicker>Verification</PanelKicker>
                                <span
                                    style={{
                                        fontSize: 12.5,
                                        color: MUTED(70),
                                    }}
                                >
                                    An administrator reviewed this business's
                                    documents on {business.verifiedAt}.
                                </span>
                            </Panel>
                        )}
                    </div>

                    <div>
                        <h6 style={{ margin: '0 0 4px' }}>Open postings</h6>
                        <p
                            style={{
                                fontSize: 12.5,
                                color: MUTED(68),
                                margin: '0 0 16px',
                            }}
                        >
                            What this business is hiring for right now.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 14,
                            }}
                        >
                            {postings.length === 0 ? (
                                <Panel style={{ padding: 16, gap: 6 }}>
                                    <span style={{ fontSize: 13 }}>
                                        Nothing open at the moment.
                                    </span>
                                    <span
                                        style={{
                                            fontSize: 12.5,
                                            color: MUTED(65),
                                        }}
                                    >
                                        A business can hold one posting at a
                                        time, so check back when this one
                                        finishes its build.
                                    </span>
                                </Panel>
                            ) : (
                                postings.map((posting) => (
                                    <Panel
                                        key={posting.slug}
                                        style={{ padding: 16, gap: 8 }}
                                    >
                                        <div
                                            style={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 8,
                                            }}
                                        >
                                            <Link
                                                href={boardShow.url({
                                                    current_team: team.slug,
                                                    project: posting.slug,
                                                })}
                                                style={{
                                                    fontFamily:
                                                        'var(--font-heading)',
                                                    fontSize: 16,
                                                    color: 'var(--color-text)',
                                                    textDecoration: 'none',
                                                    marginRight: 'auto',
                                                }}
                                            >
                                                {posting.title}
                                            </Link>
                                            <Tag
                                                variant={
                                                    posting.isAcceptingApplications
                                                        ? 'accent'
                                                        : 'neutral'
                                                }
                                            >
                                                {posting.isAcceptingApplications
                                                    ? 'Taking applications'
                                                    : 'Closed to applications'}
                                            </Tag>
                                        </div>

                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(68),
                                            }}
                                        >
                                            {[
                                                posting.category,
                                                posting.industry,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>

                                        {posting.skills.length > 0 && (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    gap: 6,
                                                    flexWrap: 'wrap',
                                                }}
                                            >
                                                {posting.skills.map((skill) => (
                                                    <Tag
                                                        key={skill}
                                                        variant="outline"
                                                    >
                                                        {skill}
                                                    </Tag>
                                                ))}
                                            </div>
                                        )}
                                    </Panel>
                                ))
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
