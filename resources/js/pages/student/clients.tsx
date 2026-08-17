import { Head, Link, router } from '@inertiajs/react';
import { BuildingsIcon, MagnifyingGlassIcon } from '@phosphor-icons/react';
import { useState } from 'react';

import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    index as clientsIndex,
    show as clientsShow,
} from '@/routes/student/clients';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Business = {
    teamSlug: string;
    businessName: string;
    description: string | null;
    city: string | null;
    province: string | null;
    openPostings: number;
};

type Props = {
    businesses: {
        data: Business[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { search: string };
};

/**
 * "Client List" — the businesses a student can look up.
 *
 * The counterpart to the client module's Recruit grid. Only verified
 * businesses are listed: an unverified one cannot publish a posting, so
 * showing it would advertise somebody nobody can work with.
 */
export default function StudentClients({ businesses, filters }: Props) {
    const team = useCurrentTeam();
    const [search, setSearch] = useState(filters.search);

    const submit = () => {
        router.get(
            clientsIndex.url(team.slug),
            { search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Client list" />

            <div
                style={{
                    maxWidth: 1320,
                    margin: '0 auto',
                    padding: '30px 32px 72px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 18,
                }}
            >
                <div>
                    <h3 style={{ margin: 0 }}>Client list</h3>
                    <div style={{ fontSize: 13, color: MUTED(60) }}>
                        {businesses.total} verified business
                        {businesses.total === 1 ? '' : 'es'} on the platform.
                    </div>
                </div>

                <Panel style={{ padding: 16, gap: 12 }}>
                    <form
                        style={{ display: 'flex', gap: 10 }}
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div style={{ position: 'relative', flex: 1 }}>
                            <MagnifyingGlassIcon
                                style={{
                                    position: 'absolute',
                                    left: 11,
                                    top: 10,
                                    fontSize: 15,
                                    color: MUTED(68),
                                }}
                            />
                            <Input
                                style={{ paddingLeft: 33 }}
                                value={search}
                                placeholder="Search by business, description or city"
                                aria-label="Search businesses"
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                        <Btn
                            variant="primary"
                            type="submit"
                            style={{ paddingInline: 20 }}
                        >
                            Search
                        </Btn>
                    </form>
                </Panel>

                {businesses.data.length === 0 ? (
                    <Panel padding="lg" gap="sm">
                        <span style={{ fontSize: 13 }}>
                            No business matches that.
                        </span>
                        <span style={{ fontSize: 12.5, color: MUTED(65) }}>
                            Only businesses an administrator has verified appear
                            here.
                        </span>
                    </Panel>
                ) : (
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns:
                                'repeat(auto-fill, minmax(300px, 1fr))',
                            gap: 16,
                        }}
                    >
                        {businesses.data.map((business) => (
                            <Panel
                                key={business.teamSlug}
                                style={{ padding: 18, gap: 10 }}
                            >
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 10,
                                    }}
                                >
                                    <span
                                        style={{
                                            width: 38,
                                            height: 38,
                                            borderRadius: '50%',
                                            background:
                                                'var(--color-accent-800)',
                                            color: 'var(--color-accent-200)',
                                            display: 'grid',
                                            placeItems: 'center',
                                            flex: 'none',
                                        }}
                                    >
                                        <BuildingsIcon />
                                    </span>
                                    <div
                                        style={{
                                            marginRight: 'auto',
                                            minWidth: 0,
                                        }}
                                    >
                                        <Link
                                            href={clientsShow.url({
                                                current_team: team.slug,
                                                business: business.teamSlug,
                                            })}
                                            style={{
                                                fontFamily:
                                                    'var(--font-heading)',
                                                fontSize: 15,
                                                color: 'var(--color-text)',
                                                textDecoration: 'none',
                                            }}
                                        >
                                            {business.businessName}
                                        </Link>
                                        <div
                                            style={{
                                                fontSize: 11.5,
                                                color: MUTED(68),
                                            }}
                                        >
                                            {[business.city, business.province]
                                                .filter(Boolean)
                                                .join(', ') ||
                                                'San Jose del Monte'}
                                        </div>
                                    </div>
                                </div>

                                {business.description && (
                                    <p
                                        style={{
                                            margin: 0,
                                            fontSize: 12.5,
                                            lineHeight: 1.55,
                                            color: MUTED(60),
                                        }}
                                    >
                                        {business.description}
                                    </p>
                                )}

                                <Tag
                                    variant={
                                        business.openPostings > 0
                                            ? 'accent'
                                            : 'neutral'
                                    }
                                    style={{ alignSelf: 'start' }}
                                >
                                    {business.openPostings === 0
                                        ? 'No open posting'
                                        : `${business.openPostings} open posting${business.openPostings === 1 ? '' : 's'}`}
                                </Tag>
                            </Panel>
                        ))}
                    </div>
                )}

                {businesses.links.length > 3 && (
                    <div
                        style={{
                            display: 'flex',
                            gap: 6,
                            justifyContent: 'center',
                        }}
                    >
                        {businesses.links.map((link) => (
                            <Btn
                                key={link.label}
                                variant={link.active ? 'primary' : 'secondary'}
                                disabled={link.url === null}
                                onClick={() =>
                                    link.url && router.get(link.url)
                                }
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
