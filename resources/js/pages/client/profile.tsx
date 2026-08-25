import { Head, useForm } from '@inertiajs/react';
import {
    BuildingsIcon,
    PencilSimpleIcon,
    UserIcon,
} from '@phosphor-icons/react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import {
    AccountDialog,
    CompanyContactsDialog,
    CompanyDetailsDialog,
} from '@/components/client/profile-dialogs';
import type {
    Account,
    BusinessProfile,
} from '@/components/client/profile-dialogs';
import Field from '@/components/sdpc/field';
import { Input } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { useCurrentTeam } from '@/hooks/use-current-team';
import {
    destroy as testimonialDestroy,
    update as testimonialUpdate,
} from '@/routes/testimonial';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Every business on this platform is in one city, so the zone is a constant
 * rather than a column nobody would ever change. If clients outside the
 * Philippines ever sign up, this is the line that has to become a field.
 */
const TIME_ZONE = 'UTC+08:00 (Philippine Standard Time)';

type Props = {
    profile: BusinessProfile & {
        industryLabel: string | null;
        organizationSizeLabel: string | null;
        verificationLabel: string;
        verificationTagVariant: string;
        completion: number;
    };
    account: Account;
    industries: { value: string; label: string }[];
    organizationSizes: { value: string; label: string }[];
    testimonial: {
        body: string;
        authorTitle: string | null;
        updatedAt: string | null;
    } | null;
    canPublishTestimonial: boolean;
    canUpdate: boolean;
    locations: Record<string, string[]>;
};

/**
 * Business profile.
 *
 * Read as cards and edited in dialogs, rather than as one long form behind a
 * single "Save profile" button. The Account card at the top is the person
 * signed in, not the business — it is the only place on the platform a client
 * can change their own name, email address or picture, since the settings
 * screen no longer carries an editor for them.
 *
 * The business permit upload that used to sit here is gone. Nobody reviews
 * permits any more, and uploading one reset the profile to Pending with
 * nothing able to grant it back, which quietly cost the client their ability
 * to post work. See App\Actions\Client\UpdateClientProfile.
 */
export default function ClientProfilePage({
    profile,
    account,
    industries,
    organizationSizes,
    testimonial,
    canPublishTestimonial,
    canUpdate,
    locations,
}: Props) {
    const team = useCurrentTeam();

    const [accountOpen, setAccountOpen] = useState(false);
    const [companyOpen, setCompanyOpen] = useState(false);
    const [contactsOpen, setContactsOpen] = useState(false);

    const quote = useForm({
        body: testimonial?.body ?? '',
        author_title: testimonial?.authorTitle ?? '',
    });

    const address = [profile.address, profile.city, profile.province]
        .filter(Boolean)
        .join(', ');

    return (
        <>
            <Head title="Business profile" />

            <div className="mx-auto max-w-[1060px] px-4 pt-6 pb-[72px] sm:px-6 lg:px-8">
                <div className="mb-5 flex items-end gap-3">
                    <div className="mr-auto">
                        <h4 className="m-0">Business profile</h4>
                        <p className="m-0 text-[12.5px] text-muted-foreground">
                            Students see this when they consider your postings.
                        </p>
                    </div>

                    <Tag
                        variant={
                            profile.verificationTagVariant as
                                'accent' | 'neutral' | 'outline'
                        }
                    >
                        {profile.verificationLabel}
                    </Tag>
                </div>

                <div style={{ display: 'grid', gap: 16 }}>
                    <SectionCard
                        title="Account"
                        editable={canUpdate}
                        onEdit={() => setAccountOpen(true)}
                    >
                        <div
                            style={{
                                display: 'flex',
                                gap: 16,
                                alignItems: 'flex-start',
                            }}
                        >
                            <Avatar url={account.avatarUrl} />

                            <div style={{ display: 'grid', gap: 12 }}>
                                <div style={{ fontSize: 17 }}>
                                    {account.name}
                                </div>

                                <Detail label="Account type">
                                    {account.roleLabel}
                                </Detail>

                                <Detail label="Email">
                                    {maskEmail(account.email)}
                                </Detail>
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Company details"
                        editable={canUpdate}
                        onEdit={() => setCompanyOpen(true)}
                    >
                        <div
                            style={{
                                display: 'flex',
                                gap: 16,
                                alignItems: 'flex-start',
                            }}
                        >
                            <Logo url={profile.logoUrl} />

                            <div style={{ display: 'grid', gap: 12 }}>
                                <div style={{ fontSize: 17 }}>
                                    {profile.businessName}
                                </div>

                                {profile.tagline && (
                                    <div
                                        style={{
                                            fontSize: 12.5,
                                            color: MUTED(65),
                                            marginTop: -6,
                                        }}
                                    >
                                        {profile.tagline}
                                    </div>
                                )}

                                <Detail label="Industry">
                                    {profile.industryLabel}
                                </Detail>

                                <Detail label="Size">
                                    {profile.organizationSizeLabel}
                                </Detail>
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Company contacts"
                        editable={canUpdate}
                        onEdit={() => setContactsOpen(true)}
                    >
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    'repeat(auto-fit, minmax(160px, 1fr))',
                                gap: '16px 24px',
                            }}
                        >
                            <Detail label="Owner">{profile.ownerName}</Detail>
                            <Detail label="Phone">
                                {maskPhone(profile.phoneNumber)}
                            </Detail>
                            <Detail label="Time zone">{TIME_ZONE}</Detail>
                            <Detail label="Address">{address || null}</Detail>
                            <Detail label="Website">
                                {profile.websiteUrl}
                            </Detail>
                            <Detail label="Facebook page">
                                {profile.facebookUrl}
                            </Detail>
                        </div>
                    </SectionCard>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            quote.put(testimonialUpdate.url(team.slug), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <Panel padding="lg" gap="lg">
                            <div className="flex items-end gap-4">
                                <div className="mr-auto">
                                    <h6 className="m-0">
                                        Your words on the homepage
                                    </h6>
                                    <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                        Tell visitors what working with a
                                        student team was like. It appears on the
                                        public landing page under your business
                                        name, and stays there until you remove
                                        it.
                                    </p>
                                </div>
                                {testimonial?.updatedAt && (
                                    <Tag variant="accent">
                                        Live since {testimonial.updatedAt}
                                    </Tag>
                                )}
                            </div>

                            {!canPublishTestimonial && (
                                <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                    Your business needs to be verified before
                                    your quote can go on the homepage.
                                </p>
                            )}

                            <Field
                                label="What you would tell another business"
                                error={quote.errors.body}
                            >
                                {(props) => (
                                    <textarea
                                        {...props}
                                        className="min-h-[110px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        maxLength={400}
                                        disabled={!canPublishTestimonial}
                                        placeholder="We had a spec sitting in a folder for two years…"
                                        value={quote.data.body}
                                        onChange={(e) =>
                                            quote.setData(
                                                'body',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <div className="grid gap-3.5 sm:grid-cols-2">
                                <Field
                                    label="Your role at the business"
                                    error={quote.errors.author_title}
                                >
                                    {(props) => (
                                        <Input
                                            {...props}
                                            placeholder="Owner"
                                            disabled={!canPublishTestimonial}
                                            value={quote.data.author_title}
                                            onChange={(e) =>
                                                quote.setData(
                                                    'author_title',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </Field>
                            </div>

                            <div className="flex items-center gap-2.5">
                                <span className="mr-auto text-[12px] text-muted-foreground">
                                    {quote.recentlySuccessful
                                        ? 'Saved.'
                                        : `${quote.data.body.length}/400`}
                                </span>

                                {testimonial !== null && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() =>
                                            quote.delete(
                                                testimonialDestroy.url(
                                                    team.slug,
                                                ),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Remove from homepage
                                    </Button>
                                )}

                                <Button
                                    type="submit"
                                    disabled={
                                        quote.processing ||
                                        !canPublishTestimonial
                                    }
                                    className="px-5"
                                >
                                    {quote.processing
                                        ? 'Saving…'
                                        : testimonial === null
                                          ? 'Publish'
                                          : 'Update'}
                                </Button>
                            </div>
                        </Panel>
                    </form>
                </div>
            </div>

            <AccountDialog
                open={accountOpen}
                onOpenChange={setAccountOpen}
                account={account}
            />

            <CompanyDetailsDialog
                open={companyOpen}
                onOpenChange={setCompanyOpen}
                profile={profile}
                industries={industries}
                sizes={organizationSizes}
            />

            <CompanyContactsDialog
                open={contactsOpen}
                onOpenChange={setContactsOpen}
                profile={profile}
                locations={locations}
            />
        </>
    );
}

/**
 * Hide most of an address without hiding whose it is.
 *
 * The domain stays because that is the part which tells you the account is the
 * right one; the local part is what somebody reading over a shoulder would
 * take. The real value is in the dialog, one click away.
 */
function maskEmail(email: string): string {
    const [local, domain] = email.split('@');

    if (!domain) {
        return email;
    }

    return `${local.slice(0, 1)}${'•'.repeat(Math.max(local.length - 1, 1))}@${domain}`;
}

/** The same idea for a phone number: enough to recognise, not to dial. */
function maskPhone(phone: string | null): string | null {
    if (phone === null || phone === '') {
        return null;
    }

    const digits = phone.replace(/\D/g, '');

    if (digits.length < 6) {
        return '•'.repeat(digits.length);
    }

    const country = digits.startsWith('63') ? '+63 ' : '';
    const rest = country ? digits.slice(2) : digits;

    return `${country}${rest.slice(0, 3)} ${'•'.repeat(Math.max(rest.length - 5, 0))}${rest.slice(-2)}`;
}

/** One titled card with a pencil in its corner. */
function SectionCard({
    title,
    editable,
    onEdit,
    children,
}: {
    title: string;
    editable: boolean;
    onEdit: () => void;
    children: ReactNode;
}) {
    return (
        <Panel style={{ padding: 22, gap: 18 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span
                    style={{
                        marginRight: 'auto',
                        fontSize: 12,
                        letterSpacing: '0.06em',
                        textTransform: 'uppercase',
                        fontFamily: 'var(--font-heading)',
                    }}
                >
                    {title}
                </span>

                {editable && (
                    <button
                        type="button"
                        onClick={onEdit}
                        aria-label={`Edit ${title.toLowerCase()}`}
                        title={`Edit ${title.toLowerCase()}`}
                        style={{
                            width: 28,
                            height: 28,
                            flex: 'none',
                            display: 'grid',
                            placeItems: 'center',
                            borderRadius: '50%',
                            border: '1px solid var(--color-divider)',
                            background: 'transparent',
                            color: 'var(--color-accent)',
                            cursor: 'pointer',
                        }}
                    >
                        <PencilSimpleIcon size={14} />
                    </button>
                )}
            </div>

            {children}
        </Panel>
    );
}

/** A labelled value, absent rather than blank when there is nothing to show. */
function Detail({
    label,
    children,
}: {
    label: string;
    children: ReactNode | null;
}) {
    if (children === null || children === undefined || children === '') {
        return null;
    }

    return (
        <div>
            <div style={{ fontSize: 11, color: MUTED(50) }}>{label}</div>
            <div style={{ fontSize: 13, color: 'var(--color-accent)' }}>
                {children}
            </div>
        </div>
    );
}

/** The account holder's picture, or the placeholder standing in for it. */
function Avatar({ url }: { url: string | null }) {
    return (
        <span
            style={{
                width: 72,
                height: 72,
                flex: 'none',
                borderRadius: '50%',
                display: 'grid',
                placeItems: 'center',
                overflow: 'hidden',
                border: url ? 'none' : '1px dashed var(--color-divider)',
                background: url ? 'transparent' : 'transparent',
                color: MUTED(55),
                fontSize: 10.5,
                lineHeight: 1.35,
                textAlign: 'center',
            }}
        >
            {url ? (
                <img
                    src={url}
                    alt=""
                    style={{
                        width: '100%',
                        height: '100%',
                        objectFit: 'cover',
                    }}
                />
            ) : (
                <UserIcon size={26} />
            )}
        </span>
    );
}

/** The business logo, square rather than round so a wordmark still reads. */
function Logo({ url }: { url: string | null }) {
    return (
        <span
            style={{
                width: 72,
                height: 72,
                flex: 'none',
                borderRadius: 'var(--radius-md)',
                display: 'grid',
                placeItems: 'center',
                overflow: 'hidden',
                background:
                    'color-mix(in srgb, var(--color-accent) 14%, transparent)',
            }}
        >
            {url ? (
                <img
                    src={url}
                    alt=""
                    style={{
                        width: '100%',
                        height: '100%',
                        objectFit: 'cover',
                    }}
                />
            ) : (
                <BuildingsIcon
                    size={28}
                    style={{ color: 'var(--color-accent)' }}
                />
            )}
        </span>
    );
}
