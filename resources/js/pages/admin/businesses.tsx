import { Head, router } from '@inertiajs/react';
import { FilePdfIcon } from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import { Tag } from '@/components/sdpc/tag';
import { permit, update } from '@/routes/admin/businesses';

type Business = {
    id: number;
    businessName: string | null;
    ownerName: string | null;
    contactEmail: string | null;
    teamName: string | null;
    city: string | null;
    province: string | null;
    completion: number;
    status: string;
    statusLabel: string;
    statusTagVariant: string;
    verifiedAt: string | null;
    awaitingDecision: boolean;
};

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Business permit review — the client-side twin of the credential queue.
 *
 * A client cannot post work or hire anyone until a permit lands here and an
 * administrator accepts it, so anything still waiting sorts to the top.
 */
export default function AdminBusinesses({
    businesses,
}: {
    businesses: Business[];
}) {
    const decide = (id: number, decision: 'verified' | 'rejected') =>
        router.patch(update.url(id), { decision }, { preserveScroll: true });

    return (
        <div
            style={{
                maxWidth: 1180,
                margin: '0 auto',
                padding: '30px 32px 72px',
            }}
        >
            <Head title="Business permits" />

            <div style={{ marginBottom: 20 }}>
                <h3 style={{ margin: 0 }}>Business permits</h3>
                <div style={{ fontSize: 13, color: MUTED(55) }}>
                    Verify a business before it can post work or hire students
                </div>
            </div>

            {businesses.length === 0 && (
                <div
                    className="card elev-sm"
                    style={{ padding: 20, fontSize: 13, color: MUTED(55) }}
                    data-test="no-businesses"
                >
                    No business has submitted a permit yet.
                </div>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                {businesses.map((business) => (
                    <div
                        key={business.id}
                        className="card elev-sm"
                        style={{ padding: 18, gap: 10 }}
                        data-test="business-row"
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: 12,
                                flexWrap: 'wrap',
                            }}
                        >
                            <div>
                                <div style={{ fontWeight: 600 }}>
                                    {business.businessName ??
                                        'Unnamed business'}
                                </div>
                                <div
                                    style={{ fontSize: 12.5, color: MUTED(55) }}
                                >
                                    {business.ownerName}
                                    {business.contactEmail &&
                                        ` · ${business.contactEmail}`}
                                    {business.city && ` · ${business.city}`}
                                    {business.province &&
                                        `, ${business.province}`}
                                </div>
                            </div>

                            <Tag
                                variant={
                                    business.statusTagVariant as
                                        | 'accent'
                                        | 'accent-2'
                                        | 'neutral'
                                        | 'outline'
                                }
                            >
                                {business.statusLabel}
                            </Tag>
                        </div>

                        <div style={{ fontSize: 12, color: MUTED(50) }}>
                            Profile {business.completion}% complete
                            {business.verifiedAt &&
                                ` · verified ${business.verifiedAt}`}
                        </div>

                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <Btn
                                asChild
                                variant="secondary"
                                style={{ fontSize: 12.5, padding: '5px 12px' }}
                            >
                                <a
                                    href={permit.url(business.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FilePdfIcon />
                                    View permit
                                </a>
                            </Btn>

                            {business.awaitingDecision && (
                                <>
                                    <Btn
                                        variant="primary"
                                        style={{
                                            fontSize: 12.5,
                                            padding: '5px 12px',
                                        }}
                                        data-test="verify-business"
                                        onClick={() =>
                                            decide(business.id, 'verified')
                                        }
                                    >
                                        Verify
                                    </Btn>
                                    <Btn
                                        variant="secondary"
                                        style={{
                                            fontSize: 12.5,
                                            padding: '5px 12px',
                                        }}
                                        data-test="reject-business"
                                        onClick={() =>
                                            decide(business.id, 'rejected')
                                        }
                                    >
                                        Reject
                                    </Btn>
                                </>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
