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
    StarHalfIcon,
    StarIcon,
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
    padding: '0 32px',
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

const SECTION_BAND: React.CSSProperties = {
    background:
        'linear-gradient(120deg,var(--color-section),var(--color-section-glow))',
};

const FADING_RULE: React.CSSProperties = {
    height: 1,
    background:
        'linear-gradient(to right,transparent,var(--color-divider) 48px,var(--color-divider) calc(100% - 48px),transparent)',
};

/**
 * The public landing page. Reachable signed out — it is the platform's shop
 * window, so it renders for guests and never redirects to login.
 */
export default function Welcome() {
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
                        <Link href={register.url()}>Register</Link>
                    </Btn>
                </nav>

                <div style={FADING_RULE} />

                {/* ── hero ── */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: '1fr 1.05fr',
                        gap: 56,
                        alignItems: 'center',
                        padding: '72px 0 64px',
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
                            SDPC connects tertiary students in San Jose Del Monte
                            with local clients who need real systems built —
                            matched by skill, tracked to delivery, documented end
                            to end.
                        </p>

                        <div style={{ display: 'flex', gap: 12, marginBottom: 30 }}>
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

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 12,
                            }}
                        >
                            <div
                                style={{
                                    display: 'flex',
                                    gap: 2,
                                    color: 'var(--color-accent)',
                                    fontSize: 15,
                                }}
                            >
                                <StarIcon weight="fill" />
                                <StarIcon weight="fill" />
                                <StarIcon weight="fill" />
                                <StarIcon weight="fill" />
                                <StarHalfIcon weight="fill" />
                            </div>
                            <span style={{ fontSize: 13, color: MUTED(55) }}>
                                4.7 average from 205 surveyed students
                            </span>
                        </div>
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
                            <img
                                src="/images/hero.webp"
                                alt="A student team working together at their desks"
                                style={{
                                    width: '100%',
                                    height: 380,
                                    objectFit: 'cover',
                                }}
                            />
                        </div>
                    </div>
                </div>

                {/* ── value strip ── */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 1,
                        background: 'var(--color-divider)',
                        borderRadius: 'var(--radius-md)',
                        overflow: 'hidden',
                        boxShadow: 'var(--shadow-sm)',
                    }}
                >
                    <ValueCell icon={<UsersThreeIcon />} label="Find the right developer" />
                    <ValueCell icon={<ChatsCircleIcon />} label="Collaborate in one place" />
                    <ValueCell icon={<SealCheckIcon />} label="Verified with confidence" />
                    <ValueCell icon={<ChartLineUpIcon />} label="Track every milestone" />
                </div>
            </div>

            {/* ── stat band ── */}
            <div style={{ margin: '36px 0 0', ...SECTION_BAND }}>
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '34px 32px',
                        display: 'grid',
                        gridTemplateColumns: 'repeat(4,1fr)',
                        gap: 24,
                    }}
                >
                    <Stat icon={<StudentIcon />} value="205" label="Students surveyed" />
                    <Stat icon={<BriefcaseIcon />} value="48" label="Projects completed" />
                    <Stat icon={<SmileyIcon />} value="94%" label="Client satisfaction" />
                    <Stat icon={<BuildingsIcon />} value="32" label="Local clients" />
                </div>
            </div>

            {/* ── testimonials ── */}
            <div
                style={{
                    maxWidth: 1240,
                    margin: '0 auto',
                    padding: '64px 32px 72px',
                }}
            >
                <h3 style={{ margin: '0 0 6px' }}>What our clients say</h3>
                <p style={{ fontSize: 13.5, color: MUTED(55), margin: '0 0 26px' }}>
                    From businesses in San Jose Del Monte who hired a student team.
                </p>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3,1fr)',
                        gap: 20,
                    }}
                >
                    <Testimonial
                        quote="We had a POS spec sitting in a folder for two years. A student team scoped it in a week and shipped in eight."
                        name="Lolene Javier"
                        role="Owner, Javier Hardware"
                    />
                    <Testimonial
                        quote="The progress board settled my biggest worry. I could see the build move every day without chasing anyone."
                        name="Shiah Carly"
                        role="Manager, Grocery Store"
                    />
                    <Testimonial
                        quote="Matching suggested three students. The first one we messaged already knew our inventory problem."
                        name="Nano Buyani"
                        role="Founder, HandyGo"
                    />
                </div>
            </div>

            {/* ── footer ── */}
            <div style={SECTION_BAND}>
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '26px 32px',
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
            <span style={{ fontSize: 22, color: 'var(--color-accent)' }}>{icon}</span>
            <span style={{ fontSize: 13.5 }}>{label}</span>
        </div>
    );
}

function Stat({
    icon,
    value,
    label,
}: {
    icon: ReactNode;
    value: string;
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
                    }}
                >
                    {value}
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

function Testimonial({
    quote,
    name,
    role,
}: {
    quote: string;
    name: string;
    role: string;
}) {
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
                    <div style={{ fontSize: 11, color: MUTED(50) }}>{role}</div>
                </div>
            </div>
        </div>
    );
}
