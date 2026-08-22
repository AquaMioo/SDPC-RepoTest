import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    MagnifyingGlassIcon,
    SparkleIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { index as boardIndex, show as boardShow } from '@/routes/student/board';
import { index as clientsIndex } from '@/routes/student/clients';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type ProjectCard = {
    id: number;
    slug: string;
    title: string;
    summary: string;
    category: string;
    client: string;
    city: string | null;
    isBusinessVerified: boolean;
    postedAt: string | null;
    skills: string[];
    applicants: number;
    isAcceptingApplications: boolean;
    hasApplied: boolean;
    compatibility: number | null;
    insight: string | null;
};

type Props = {
    projects: {
        data: ProjectCard[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search: string; skills: string[]; sort: string };
    sorts: { value: string; label: string }[];
    skillGroups: {
        type: string;
        label: string;
        skills: { slug: string; name: string }[];
    }[];
    canApply: boolean;
    /** One student, one build — true while they already have work. */
    holdsProjectInHand: boolean;
    matchingEnabled: boolean;
    highlight: {
        title: string;
        client: string;
        compatibility: number;
        factors: { label: string; value: number }[];
        recommendation: string | null;
    } | null;
};

/**
 * "Get Client" — the board a student browses for work.
 *
 * The match ring, the per-factor bars and the per-card insight all come from
 * the recommendations table. Nothing writes to it until the AI module lands,
 * so the analysis panel is absent rather than showing a zeroed-out score, and
 * the cards simply carry no match tag.
 */
export default function FindClients({
    projects,
    filters,
    sorts,
    skillGroups,
    canApply,
    holdsProjectInHand,
    matchingEnabled,
    highlight,
}: Props) {
    const team = useCurrentTeam();
    const [search, setSearch] = useState(filters.search);

    const go = (next: Partial<Props['filters']>) =>
        router.get(
            boardIndex.url(team.slug),
            { ...filters, search, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const toggleSkill = (slug: string) =>
        go({
            skills: filters.skills.includes(slug)
                ? filters.skills.filter((s) => s !== slug)
                : [...filters.skills, slug],
        });

    return (
        <>
            <Head title="Get Client" />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-end',
                        gap: 16,
                        marginBottom: 18,
                    }}
                >
                    <div style={{ marginRight: 'auto' }}>
                        <h3 style={{ margin: 0 }}>Find a client</h3>
                        <div style={{ fontSize: 13, color: MUTED(68) }}>
                            Open project briefs from businesses around San Jose
                            Del Monte
                        </div>
                    </div>

                    {/* The board lists briefs; the directory lists the
                        businesses behind them. */}
                    <Btn asChild>
                        <Link href={clientsIndex.url(team.slug)}>
                            Browse the client list
                        </Link>
                    </Btn>
                </div>

                {!canApply && (
                    <Panel
                        padding="md"
                        gap="sm"
                        style={{ marginBottom: 18 }}
                    >
                        <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                            You can read every brief here. Applying opens up
                            once an administrator has verified your student
                            credential.
                        </span>
                    </Panel>
                )}

                {/* Browsing stays open while they build — applying does not. */}
                {canApply && holdsProjectInHand && (
                    <Panel
                        padding="md"
                        gap="sm"
                        style={{ marginBottom: 18 }}
                    >
                        <span style={{ fontSize: 12.5, color: MUTED(70) }}>
                            You already have a project in hand. Browse all you
                            like — applying opens up again once that build is
                            finished.
                        </span>
                    </Panel>
                )}

                {matchingEnabled && highlight !== null && (
                    <MatchPanel highlight={highlight} />
                )}

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        marginBottom: 16,
                        flexWrap: 'wrap',
                    }}
                >
                    {/* A segmented control rather than loose links: these
                        are one choice, and only one can be current. */}
                    <div
                        role="tablist"
                        aria-label="Order briefs"
                        style={{
                            display: 'flex',
                            gap: 2,
                            padding: 3,
                            borderRadius: 'var(--radius-md)',
                            border: '1px solid var(--color-divider)',
                        }}
                    >
                        {sorts.map((sort) => {
                            const isCurrent = filters.sort === sort.value;

                            return (
                                <button
                                    key={sort.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={isCurrent}
                                    onClick={() => go({ sort: sort.value })}
                                    style={{
                                        background: isCurrent
                                            ? 'color-mix(in srgb, var(--color-accent) 14%, transparent)'
                                            : 'none',
                                        border: 0,
                                        font: 'inherit',
                                        fontSize: 12.5,
                                        padding: '6px 14px',
                                        borderRadius: 'var(--radius-sm)',
                                        cursor: 'pointer',
                                        color: isCurrent
                                            ? 'var(--color-accent)'
                                            : MUTED(70),
                                    }}
                                >
                                    {sort.label}
                                </button>
                            );
                        })}
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            go({ search });
                        }}
                        style={{
                            position: 'relative',
                            marginLeft: 'auto',
                            width: 260,
                        }}
                    >
                        <MagnifyingGlassIcon
                            style={{
                                position: 'absolute',
                                left: 10,
                                top: 9,
                                fontSize: 15,
                                color: MUTED(68),
                            }}
                        />
                        <input
                            className="input"
                            style={{ paddingLeft: 31, width: '100%' }}
                            placeholder="Search briefs"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </form>
                </div>

                {skillGroups.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            flexWrap: 'wrap',
                            gap: 5,
                            marginBottom: 16,
                        }}
                    >
                        {skillGroups
                            .flatMap((group) => group.skills)
                            .slice(0, 18)
                            .map((skill) => (
                                <button
                                    key={skill.slug}
                                    type="button"
                                    onClick={() => toggleSkill(skill.slug)}
                                    className={
                                        filters.skills.includes(skill.slug)
                                            ? 'tag tag-accent'
                                            : 'tag tag-outline'
                                    }
                                    style={{
                                        border: 0,
                                        cursor: 'pointer',
                                        font: 'inherit',
                                        fontSize: 11,
                                    }}
                                >
                                    {skill.name}
                                </button>
                            ))}
                    </div>
                )}

                {projects.data.length === 0 ? (
                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>
                            No briefs match yet.
                        </span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            A brief appears here once a business publishes it
                            and an administrator approves it. Try clearing your
                            filters.
                        </span>
                    </Panel>
                ) : (
                    <Panel padding="lg" gap="none">
                        {projects.data.map((project, index) => (
                            <BriefRow
                                key={project.slug}
                                project={project}
                                isFirst={index === 0}
                                canApply={canApply && !holdsProjectInHand}
                                href={boardShow.url({
                                    current_team: team.slug,
                                    project: project.slug,
                                })}
                            />
                        ))}
                    </Panel>
                )}

                {projects.links.length > 3 && (
                    <div style={{ display: 'flex', gap: 6, marginTop: 20 }}>
                        {projects.links.map((link, index) =>
                            link.url === null ? (
                                <span
                                    key={index}
                                    className="btn btn-ghost"
                                    style={{ opacity: 0.4 }}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={
                                        link.active
                                            ? 'btn btn-primary'
                                            : 'btn btn-ghost'
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * The AI analysis strip: match ring, per-factor bars, and the written
 * recommendation. Only rendered when there is a real score behind it.
 */
function MatchPanel({ highlight }: { highlight: NonNullable<Props['highlight']> }) {
    const circumference = 2 * Math.PI * 46;

    return (
        <Panel
            elevation="md"
            padding="lg"
            style={{
                flexDirection: 'row',
                alignItems: 'center',
                gap: 20,
                marginBottom: 20,
                background:
                    'linear-gradient(120deg, color-mix(in srgb, var(--color-accent) 10%, var(--color-surface)), var(--color-surface) 60%)',
            }}
        >
            <div
                style={{
                    position: 'relative',
                    width: 104,
                    height: 104,
                    flex: '0 0 auto',
                }}
            >
                <svg
                    width={104}
                    height={104}
                    style={{ transform: 'rotate(-90deg)' }}
                >
                    <circle
                        cx={52}
                        cy={52}
                        r={46}
                        fill="none"
                        stroke="var(--color-divider)"
                        strokeWidth={8}
                    />
                    <circle
                        cx={52}
                        cy={52}
                        r={46}
                        fill="none"
                        stroke="var(--color-accent)"
                        strokeWidth={8}
                        strokeLinecap="round"
                        strokeDasharray={circumference}
                        strokeDashoffset={
                            circumference * (1 - highlight.compatibility / 100)
                        }
                    />
                </svg>
                <div
                    style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'grid',
                        placeItems: 'center',
                        fontFamily: 'var(--font-heading)',
                        fontSize: 22,
                    }}
                >
                    {highlight.compatibility}%
                </div>
            </div>

            <div style={{ flex: '1 1 0', minWidth: 0 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        marginBottom: 8,
                    }}
                >
                    <SparkleIcon style={{ color: 'var(--color-accent)' }} />
                    <span className="card-kicker">AI analysis</span>
                    <span style={{ fontSize: 13 }}>
                        Strongest match: {highlight.title} ·{' '}
                        {highlight.client}
                    </span>
                </div>

                {highlight.factors.length > 0 && (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '1fr 1fr',
                            gap: '10px 22px',
                        }}
                    >
                        {highlight.factors.map((factor) => (
                            <div key={factor.label}>
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        fontSize: 11.5,
                                        marginBottom: 4,
                                    }}
                                >
                                    <span>{factor.label}</span>
                                    <span style={{ color: MUTED(68) }}>
                                        {factor.value}%
                                    </span>
                                </div>
                                <div
                                    style={{
                                        height: 5,
                                        borderRadius: 3,
                                        background: 'var(--color-divider)',
                                    }}
                                >
                                    <div
                                        style={{
                                            height: '100%',
                                            width: `${factor.value}%`,
                                            borderRadius: 3,
                                            background: 'var(--color-accent)',
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {highlight.recommendation && (
                <div
                    style={{
                        width: 230,
                        flex: '0 0 auto',
                        paddingLeft: 20,
                        borderLeft: '1px solid var(--color-divider)',
                    }}
                >
                    <div
                        style={{
                            fontSize: 11,
                            letterSpacing: '0.08em',
                            textTransform: 'uppercase',
                            color: MUTED(68),
                            marginBottom: 6,
                        }}
                    >
                        Strategic recommendation
                    </div>
                    <p
                        style={{
                            margin: 0,
                            fontSize: 12.5,
                            lineHeight: 1.55,
                            color: MUTED(70),
                        }}
                    >
                        {highlight.recommendation}
                    </p>
                </div>
            )}
        </Panel>
    );
}

/**
 * One brief, as a full-width row.
 *
 * Everything drawn here is a column that exists. The design also sketched a
 * difficulty level and a "8-10 weeks" duration; both were dropped from
 * projects by an earlier migration, so they are absent rather than invented —
 * and there is no bookmark table behind the design's save icon, so that is
 * absent too.
 */
function BriefRow({
    project,
    href,
    isFirst,
    canApply,
}: {
    project: ProjectCard;
    href: string;
    isFirst: boolean;
    /** False while the student is unverified or already building something. */
    canApply: boolean;
}) {
    return (
        <article
            data-test="brief-row"
            style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 8,
                padding: isFirst ? '0 0 20px' : '20px 0',
                borderTop: isFirst ? undefined : '1px solid var(--color-divider)',
            }}
        >
            <div style={{ fontSize: 11.5, color: MUTED(60) }}>
                {project.postedAt ? `Posted ${project.postedAt}` : 'Not yet published'}
                {' · '}
                {project.client}
                {project.city ? ` · ${project.city}` : ''}
            </div>

            <Link
                href={href}
                style={{
                    fontFamily: 'var(--font-heading)',
                    fontSize: 19,
                    lineHeight: 1.25,
                    color: 'var(--color-text)',
                    textDecoration: 'none',
                }}
            >
                {project.title}
            </Link>

            <div style={{ fontSize: 11.5, color: MUTED(60) }}>
                {project.category}
                {' · '}
                {project.applicants} applicant
                {project.applicants === 1 ? '' : 's'}
            </div>

            <p
                style={{
                    margin: 0,
                    maxWidth: '78ch',
                    fontSize: 13,
                    lineHeight: 1.6,
                    color: MUTED(72),
                }}
            >
                {project.summary}
            </p>

            {project.skills.length > 0 && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
                    {project.skills.map((skill) => (
                        <Tag key={skill} variant="outline">
                            {skill}
                        </Tag>
                    ))}
                </div>
            )}

            {project.insight && (
                <div
                    style={{
                        padding: '8px 11px',
                        borderRadius: 'var(--radius-sm)',
                        background:
                            'color-mix(in srgb, var(--color-accent) 12%, transparent)',
                        fontSize: 11.5,
                        lineHeight: 1.5,
                        maxWidth: '78ch',
                    }}
                >
                    <span style={{ color: 'var(--color-accent)' }}>
                        AI insight
                    </span>{' '}
                    · {project.insight}
                </div>
            )}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    flexWrap: 'wrap',
                    marginTop: 2,
                }}
            >
                {project.isBusinessVerified && (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 5,
                            fontSize: 11.5,
                            color: MUTED(70),
                        }}
                    >
                        <CheckCircleIcon
                            weight="fill"
                            style={{ color: 'var(--color-accent)' }}
                        />
                        Business verified
                    </span>
                )}

                {project.compatibility !== null && (
                    <Tag variant="accent">{project.compatibility}% match</Tag>
                )}

                <span style={{ marginLeft: 'auto' }}>
                    {project.hasApplied ? (
                        <Tag variant="accent">Applied</Tag>
                    ) : (
                        /*
                         * A link, not a one-click apply: applying needs a
                         * cover letter, and that form lives on the brief.
                         */
                        <Btn
                            asChild={canApply}
                            variant="secondary"
                            disabled={!canApply}
                            title={
                                canApply
                                    ? undefined
                                    : 'Applying opens up once your credential is verified and you have no build in hand.'
                            }
                        >
                            {canApply ? <Link href={href}>Apply</Link> : 'Apply'}
                        </Btn>
                    )}
                </span>
            </div>
        </article>
    );
}
