import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseIcon,
    BuildingsIcon,
    ChartLineUpIcon,
    ChatsCircleIcon,
    FacebookLogoIcon,
    GithubLogoIcon,
    LinkedinLogoIcon,
    SealCheckIcon,
    SmileyIcon,
    SparkleIcon,
    StudentIcon,
    UserIcon,
    UsersThreeIcon,
} from '@phosphor-icons/react';
import type { ReactNode } from 'react';

import { Btn } from '@/components/sdpc/btn';
import PublicLayout from '@/layouts/public-layout';
import { login, register } from '@/routes';
import { login as adminLogin } from '@/routes/admin';

const SHELL: React.CSSProperties = {
    maxWidth: 1240,
    margin: '0 auto',
    padding: '0 clamp(16px, 4vw, 32px)',
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/*
 * The stat band and footer sit on the page ground with hairline rules, rather
 * than the green gradient the dark theme used. On the light palette a
 * saturated band fights the page instead of seating into it.
 */
const STAT_BAND: React.CSSProperties = {
    background: 'var(--color-bg)',
    boxShadow:
        'inset 0 1px 0 var(--color-divider), inset 0 -1px 0 var(--color-divider)',
};

const FOOTER_BAND: React.CSSProperties = {
    background: 'var(--color-bg)',
    boxShadow: 'inset 0 1px 0 var(--color-divider)',
};

const FADING_RULE: React.CSSProperties = {
    height: 1,
    background:
        'linear-gradient(to right,transparent,var(--color-divider) 48px,var(--color-divider) calc(100% - 48px),transparent)',
};

type Stats = {
    students: number;
    projectsCompleted: number;
    clientSatisfaction: number | null;
    clients: number;
    /** Null until somebody has actually been rated. */
    studentRating: { average: number; rated: number } | null;
};

type TestimonialItem = {
    quote: string;
    name: string;
    role: string | null;
};

/**
 * The public landing page. Reachable signed out — it is the platform's shop
 * window, so it renders for guests and never redirects to login.
 *
 * Every figure comes from HomeController. Nothing here is written by hand.
 */
export default function Welcome({
    stats,
    testimonials,
}: {
    stats: Stats;
    testimonials: TestimonialItem[];
}) {
    return (
        <PublicLayout>
            <Head title="SDPC — Student Developer Project Connection" />

            <div style={SHELL}>
                <nav
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 28,
                        padding: '22px 0',
                    }}
                >
                    <span
                        style={{
                            fontFamily: 'var(--font-heading)',
                            fontWeight: 600,
                            fontSize: 20,
                            letterSpacing: '-0.02em',
                            color: 'var(--color-accent)',
                            marginRight: 'auto',
                        }}
                    >
                        SDPC
                    </span>

                    {['About Us', "What's New", 'For Business'].map((label) => (
                        <a
                            key={label}
                            href="#"
                            style={{
                                fontSize: 14,
                                textDecoration: 'none',
                                color: 'var(--color-text)',
                                opacity: 0.75,
                                cursor: 'pointer',
                            }}
                        >
                            {label}
                        </a>
                    ))}

                    <Btn asChild variant="ghost">
                        <Link href={login.url()}>Log in</Link>
                    </Btn>
                    <Btn asChild variant="primary">
                        <Link href={register.url()}>Sign up</Link>
                    </Btn>
                </nav>

                <div style={FADING_RULE} />

                {/* ── hero ── */}
                <div
                    className="stack"
                    style={{
                        ['--cols' as string]: '1fr 1.05fr',
                        gap: 56,
                        alignItems: 'center',
                        padding: '48px 0 56px',
                    }}
                >
                    <div>
                        <div
                            className="tag tag-accent"
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 7,
                                marginBottom: 22,
                            }}
                        >
                            <SparkleIcon />
                            AI client matching · San Jose Del Monte
                        </div>

                        <h1
                            style={{
                                fontSize: 58,
                                lineHeight: 1.03,
                                letterSpacing: '-0.03em',
                                margin: '0 0 20px',
                                maxWidth: '11ch',
                            }}
                        >
                            Build smarter projects with the{' '}
                            <span style={{ color: 'var(--color-accent)' }}>
                                right people.
                            </span>
                        </h1>

                        <p
                            style={{
                                fontSize: 16,
                                lineHeight: 1.6,
                                maxWidth: '46ch',
                                color: MUTED(62),
                                margin: '0 0 28px',
                                textWrap: 'pretty',
                            }}
                        >
                            SDPC connects tertiary students in San Jose Del
                            Monte with local clients who need real systems built
                            — matched by skill, tracked to delivery, documented
                            end to end.
                        </p>

                        <div
                            style={{
                                display: 'flex',
                                gap: 12,
                                marginBottom: 30,
                            }}
                        >
                            <Btn
                                asChild
                                variant="primary"
                                style={{ padding: '11px 22px', fontSize: 15 }}
                            >
                                <Link href={register.url()}>Get started</Link>
                            </Btn>
                            <Btn
                                asChild
                                variant="secondary"
                                style={{ padding: '11px 22px', fontSize: 15 }}
                            >
                                <Link href={login.url()}>Browse students</Link>
                            </Btn>
                        </div>

                        {/*
                         * Averaged from student_profiles.rating_average rather
                         * than written down. The stars were once a hard-coded
                         * "4.7" that no data supported; this draws them only
                         * when somebody has actually been rated, and falls
                         * back to a plain fact when nobody has.
                         */}
                        {stats.studentRating ? (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 9,
                                    fontSize: 13,
                                    color: MUTED(60),
                                }}
                            >
                                <Stars value={stats.studentRating.average} />
                                {stats.studentRating.average.toFixed(1)} average
                                from{' '}
                                {stats.studentRating.rated.toLocaleString()}{' '}
                                rated student
                                {stats.studentRating.rated === 1 ? '' : 's'}
                            </div>
                        ) : (
                            <p
                                style={{
                                    fontSize: 13,
                                    color: MUTED(55),
                                    margin: 0,
                                }}
                            >
                                Free for students and for businesses in San Jose
                                Del Monte.
                            </p>
                        )}
                    </div>

                    <div
                        style={{
                            position: 'relative',
                            overflow: 'hidden',
                            padding: 14,
                            margin: -14,
                        }}
                    >
                        <div
                            style={{
                                position: 'absolute',
                                inset: 0,
                                background:
                                    'radial-gradient(60% 60% at 60% 40%, color-mix(in srgb, var(--color-accent) 26%, transparent), transparent 70%)',
                                filter: 'blur(18px)',
                            }}
                        />
                        <div
                            className="lighten"
                            style={{
                                position: 'relative',
                                borderRadius: 'var(--radius-lg)',
                                overflow: 'hidden',
                            }}
                        >
                            {/*
                             * The four students who built SDPC, photographed
                             * outside their own school. Described rather than
                             * left decorative because the picture is the claim
                             * the page is making — that this was built by
                             * students in San Jose Del Monte, not stock.
                             */}
                            <img
                                src="/images/hero.webp"
                                alt="The four students who built SDPC, outside their school"
                                style={{
                                    width: '100%',
                                    height: 380,
                                    objectFit: 'cover',
                                }}
                            />

                            {/*
                             * The match, shown rather than described: a
                             * student's skills on one side, what a client
                             * asked for on the other, and the engine's verdict
                             * between them. Illustrative — these are not live
                             * rows — so it is kept to the shape of the thing
                             * and never dressed with a real name.
                             *
                             * Hidden below `md`: laid over a 343px-wide photo
                             * the two cards cover the people in it.
                             */}
                            <div className="hero-proof">
                                <MatchCard
                                    icon={<UserIcon />}
                                    title="Student"
                                    kicker="Key skills"
                                    items={['Laravel', 'React', 'MySQL']}
                                    chips
                                />

                                <span className="hero-proof-badge">
                                    <SparkleIcon />
                                    AI matched
                                </span>

                                <MatchCard
                                    icon={<BuildingsIcon />}
                                    title="Client"
                                    kicker="Project requirements"
                                    items={[
                                        'Inventory system',
                                        'Reorder forecasting',
                                    ]}
                                />
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── value strip ── */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 1,
                        background: 'var(--color-divider)',
                        borderRadius: 'var(--radius-md)',
                        overflow: 'hidden',
                        boxShadow: 'var(--shadow-sm)',
                    }}
                >
                    <ValueCell
                        icon={<UsersThreeIcon />}
                        label="Find the right developer"
                    />
                    <ValueCell
                        icon={<ChatsCircleIcon />}
                        label="Collaborate in one place"
                    />
                    <ValueCell
                        icon={<SealCheckIcon />}
                        label="Verified with confidence"
                    />
                    <ValueCell
                        icon={<ChartLineUpIcon />}
                        label="Track every milestone"
                    />
                </div>
            </div>

            {/* ── stat band ── */}
            <div style={{ margin: '36px 0 0', ...STAT_BAND }}>
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '34px clamp(16px, 4vw, 32px)',
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: 24,
                    }}
                >
                    <Stat
                        icon={<StudentIcon />}
                        value={stats.students}
                        label={stats.students === 1 ? 'Student' : 'Students'}
                    />
                    <Stat
                        icon={<BriefcaseIcon />}
                        value={stats.projectsCompleted}
                        label="Projects completed"
                    />
                    <Stat
                        icon={<SmileyIcon />}
                        value={
                            stats.clientSatisfaction === null
                                ? null
                                : `${stats.clientSatisfaction}%`
                        }
                        label="Client satisfaction"
                    />
                    <Stat
                        icon={<BuildingsIcon />}
                        value={stats.clients}
                        label={
                            stats.clients === 1
                                ? 'Local client'
                                : 'Local clients'
                        }
                    />
                </div>
            </div>

            {/*
             * ── testimonials ──
             *
             * Absent entirely until a client writes one. An empty heading over
             * an empty row reads as something broken; no section reads as a
             * page that simply has not got there yet.
             */}
            {testimonials.length > 0 && (
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '64px clamp(16px, 4vw, 32px) 72px',
                    }}
                >
                    <h3 style={{ margin: '0 0 6px' }}>What our clients say</h3>
                    <p
                        style={{
                            fontSize: 13.5,
                            color: MUTED(55),
                            margin: '0 0 26px',
                        }}
                    >
                        From businesses in San Jose Del Monte who hired a
                        student team.
                    </p>

                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fit,minmax(300px,1fr))',
                            gap: 20,
                        }}
                    >
                        {testimonials.map((testimonial, index) => (
                            <Testimonial key={index} {...testimonial} />
                        ))}
                    </div>
                </div>
            )}

            {/* ── footer ── */}
            <div style={FOOTER_BAND}>
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '26px clamp(16px, 4vw, 32px)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 20,
                    }}
                >
                    <div style={{ marginRight: 'auto', fontSize: 13.5 }}>
                        Follow us · STI College San Jose Del Monte
                        <Btn
                            asChild
                            variant="ghost"
                            style={{
                                marginLeft: 14,
                                color: 'var(--color-accent-200)',
                                fontSize: 12,
                            }}
                        >
                            <Link href={adminLogin.url()}>Admin console</Link>
                        </Btn>
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            gap: 14,
                            fontSize: 18,
                            color: 'var(--color-accent-200)',
                        }}
                    >
                        <FacebookLogoIcon />
                        <LinkedinLogoIcon />
                        <GithubLogoIcon />
                    </div>

                    <div
                        style={{
                            fontSize: 12,
                            color: 'var(--color-accent-200)',
                            opacity: 0.75,
                        }}
                    >
                        Terms of Service · Privacy
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function ValueCell({ icon, label }: { icon: ReactNode; label: string }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                padding: '18px 20px',
                background: 'var(--color-surface)',
            }}
        >
            <span style={{ fontSize: 22, color: 'var(--color-accent)' }}>
                {icon}
            </span>
            <span style={{ fontSize: 13.5 }}>{label}</span>
        </div>
    );
}

/**
 * One figure in the stat band.
 *
 * A null value renders as a dash: it means nothing has been measured yet,
 * which is not the same claim as zero.
 */
function Stat({
    icon,
    value,
    label,
}: {
    icon: ReactNode;
    value: string | number | null;
    label: string;
}) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
            <span style={{ fontSize: 28, color: 'var(--color-accent-200)' }}>
                {icon}
            </span>
            <div>
                <div
                    style={{
                        fontFamily: 'var(--font-heading)',
                        fontSize: 24,
                        lineHeight: 1,
                        opacity: value === null ? 0.45 : 1,
                    }}
                >
                    {value ?? '—'}
                </div>
                <div
                    style={{
                        fontSize: 12,
                        color: 'var(--color-accent-200)',
                        opacity: 0.8,
                    }}
                >
                    {label}
                </div>
            </div>
        </div>
    );
}

function Testimonial({ quote, name, role }: TestimonialItem) {
    return (
        <div className="card elev-sm" style={{ padding: 20, gap: 16 }}>
            <p
                className="card-body"
                style={{ fontSize: 14, lineHeight: 1.6, opacity: 0.85 }}
            >
                “{quote}”
            </p>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <span
                    style={{
                        width: 34,
                        height: 34,
                        borderRadius: '50%',
                        background: 'var(--color-accent-800)',
                        display: 'grid',
                        placeItems: 'center',
                        color: 'var(--color-accent-200)',
                    }}
                >
                    <UserIcon />
                </span>
                <div>
                    <div style={{ fontSize: 13.5 }}>{name}</div>
                    {role !== null && (
                        <div style={{ fontSize: 11, color: MUTED(50) }}>
                            {role}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * One side of the match illustration over the hero photo.
 *
 * Skills read as chips because that is how they are shown everywhere else on
 * the platform; requirements read as lines, because a requirement is a
 * sentence and a chip would clip it.
 */
function MatchCard({
    icon,
    title,
    kicker,
    items,
    chips = false,
}: {
    icon: ReactNode;
    title: string;
    kicker: string;
    items: string[];
    chips?: boolean;
}) {
    return (
        <div className="hero-proof-card">
            <div className="hero-proof-head">
                <span className="hero-proof-icon">{icon}</span>
                {title}
            </div>

            <div className="hero-proof-kicker">{kicker}</div>

            <div className={chips ? 'hero-proof-chips' : 'hero-proof-lines'}>
                {items.map((item) => (
                    <span key={item}>{item}</span>
                ))}
            </div>
        </div>
    );
}

/**
 * Five stars filled to the average, halves included.
 *
 * Drawn from the figure rather than from a picked icon set so the row cannot
 * drift from the number printed beside it.
 */
function Stars({ value }: { value: number }) {
    return (
        <span
            className="hero-stars"
            role="img"
            aria-label={`${value.toFixed(1)} out of 5`}
        >
            <span
                style={{
                    width: `${Math.max(0, Math.min(value / 5, 1)) * 100}%`,
                }}
            >
                ★★★★★
            </span>
            ☆☆☆☆☆
        </span>
    );
}
