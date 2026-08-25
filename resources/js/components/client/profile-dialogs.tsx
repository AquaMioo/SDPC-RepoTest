import { router } from '@inertiajs/react';
import { BuildingsIcon, UserIcon } from '@phosphor-icons/react';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';

import InputError from '@/components/input-error';
import { Btn } from '@/components/sdpc/btn';
import { Input, Select, Textarea } from '@/components/sdpc/input';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as businessUpdate } from '@/routes/client-profile';
import { update as accountUpdate } from '@/routes/profile';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

type Option = { value: string; label: string };

export type Account = {
    name: string;
    email: string;
    avatarUrl: string | null;
    roleLabel: string;
};

export type BusinessProfile = {
    businessName: string;
    businessDescription: string | null;
    industry: string | null;
    organizationSize: string | null;
    tagline: string | null;
    ownerName: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    phoneNumber: string | null;
    contactEmail: string | null;
    websiteUrl: string | null;
    facebookUrl: string | null;
    logoUrl: string | null;
};

/** A dialog whose open state the caller owns. */
function Shell({
    open,
    onOpenChange,
    title,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    children: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                {children}
            </DialogContent>
        </Dialog>
    );
}

/**
 * The round picture-or-placeholder both dialogs open with.
 */
function PictureWell({
    src,
    onPick,
    round,
    children,
}: {
    src: string | null;
    onPick: (file: File | null) => void;
    round: boolean;
    children: ReactNode;
}) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <>
            <button
                type="button"
                onClick={() => input.current?.click()}
                style={{
                    width: 76,
                    height: 76,
                    flex: 'none',
                    borderRadius: round ? '50%' : 'var(--radius-md)',
                    border: src ? 'none' : '1px dashed var(--color-divider)',
                    background: src
                        ? 'transparent'
                        : 'color-mix(in srgb, var(--color-accent) 14%, transparent)',
                    color: MUTED(60),
                    fontSize: 10.5,
                    lineHeight: 1.35,
                    cursor: 'pointer',
                    display: 'grid',
                    placeItems: 'center',
                    overflow: 'hidden',
                    padding: 0,
                }}
            >
                {src ? (
                    <img
                        src={src}
                        alt=""
                        style={{
                            width: '100%',
                            height: '100%',
                            objectFit: 'cover',
                        }}
                    />
                ) : (
                    children
                )}
            </button>

            <input
                ref={input}
                type="file"
                accept="image/*"
                hidden
                onChange={(event) => onPick(event.target.files?.[0] ?? null)}
            />
        </>
    );
}

/**
 * Hold a chosen file and its preview, handing the old blob URL back when it is
 * replaced. Built inline during render it would mint a fresh URL on every
 * keystroke and leak every one of them.
 */
function usePicture(existing: string | null) {
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const pick = (chosen: File | null) => {
        setPreview((previous) => {
            if (previous !== null) {
                URL.revokeObjectURL(previous);
            }

            return chosen === null ? null : URL.createObjectURL(chosen);
        });

        setFile(chosen);
    };

    return { file, src: preview ?? existing, pick };
}

/**
 * "Account" — the person, not the business.
 *
 * This posts at the settings profile endpoint, which is the only thing that
 * writes a user's own name, address and picture. The settings screen no longer
 * carries an editor for them, so without this card a client would have no way
 * to change their own name or email anywhere on the platform.
 */
export function AccountDialog({
    open,
    onOpenChange,
    account,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    account: Account;
}) {
    const picture = usePicture(account.avatarUrl);
    const [name, setName] = useState(account.name);
    const [email, setEmail] = useState(account.email);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const save = () => {
        setBusy(true);

        /*
         * Posted rather than patched because of the file: Inertia spoofs the
         * method so multipart still reaches a PATCH route.
         */
        router.post(
            accountUpdate.url(),
            {
                _method: 'patch',
                name,
                email,
                ...(picture.file ? { avatar: picture.file } : {}),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell open={open} onOpenChange={onOpenChange} title="Account">
            <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                <PictureWell src={picture.src} onPick={picture.pick} round>
                    <span>
                        Photo
                        <br />
                        <u>browse files</u>
                    </span>
                </PictureWell>

                <div style={{ flex: 1, display: 'grid', gap: 12 }}>
                    <div className="field">
                        <label htmlFor="account-name">Full name</label>
                        <Input
                            id="account-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                        />
                        <InputError
                            message={errors.name}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="account-email">Email</label>
                        <Input
                            id="account-email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            aria-invalid={Boolean(errors.email)}
                        />
                        <InputError
                            message={errors.email}
                            className="mt-1 text-[11px]"
                        />
                        <p
                            style={{
                                margin: '6px 0 0',
                                fontSize: 11,
                                color: MUTED(55),
                            }}
                        >
                            Changing this asks you to confirm the new address
                            before it is trusted again.
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter className="gap-2">
                <Btn
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={save}
                    data-test="save-account-button"
                >
                    {busy && <Spinner />}
                    Save
                </Btn>

                <Btn
                    type="button"
                    variant="ghost"
                    onClick={() => onOpenChange(false)}
                >
                    Cancel
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/**
 * "Company details" — the business as a student reads it.
 */
export function CompanyDetailsDialog({
    open,
    onOpenChange,
    profile,
    industries,
    sizes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    profile: BusinessProfile;
    industries: Option[];
    sizes: Option[];
}) {
    const team = useCurrentTeam();
    const logo = usePicture(profile.logoUrl);

    const [businessName, setBusinessName] = useState(profile.businessName);
    const [industry, setIndustry] = useState(profile.industry ?? '');
    const [size, setSize] = useState(profile.organizationSize ?? '');
    const [tagline, setTagline] = useState(profile.tagline ?? '');
    const [description, setDescription] = useState(
        profile.businessDescription ?? '',
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const save = () => {
        setBusy(true);

        router.post(
            businessUpdate.url(team.slug),
            {
                _method: 'patch',
                business_name: businessName,
                industry: industry || null,
                organization_size: size || null,
                tagline: tagline || null,
                business_description: description || null,
                ...(logo.file ? { logo: logo.file } : {}),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell open={open} onOpenChange={onOpenChange} title="Company details">
            <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start' }}>
                <PictureWell src={logo.src} onPick={logo.pick} round={false}>
                    <BuildingsIcon
                        size={26}
                        style={{ color: 'var(--color-accent)' }}
                    />
                </PictureWell>

                <div style={{ flex: 1, display: 'grid', gap: 12 }}>
                    <div className="field">
                        <label htmlFor="business_name">Company name</label>
                        <Input
                            id="business_name"
                            value={businessName}
                            onChange={(e) => setBusinessName(e.target.value)}
                            aria-invalid={Boolean(errors.business_name)}
                        />
                        <InputError
                            message={errors.business_name}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="industry">Add your industry</label>
                        <Select
                            id="industry"
                            value={industry}
                            onChange={(e) => setIndustry(e.target.value)}
                        >
                            <option value="">Select your industry</option>
                            {industries.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={errors.industry}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <span
                            style={{
                                fontSize: 11.5,
                                color: MUTED(60),
                                marginBottom: 6,
                                display: 'block',
                            }}
                        >
                            How many people are in your organization?
                        </span>

                        <div style={{ display: 'flex', flexWrap: 'wrap' }}>
                            {sizes.map((option) => {
                                const chosen = size === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        aria-pressed={chosen}
                                        onClick={() =>
                                            /* Tapping the chosen band clears it. */
                                            setSize(chosen ? '' : option.value)
                                        }
                                        style={{
                                            padding: '7px 13px',
                                            fontSize: 12.5,
                                            cursor: 'pointer',
                                            fontFamily: 'var(--font-body)',
                                            border: `1px solid ${chosen ? 'var(--color-accent)' : 'var(--color-divider)'}`,
                                            background: chosen
                                                ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)'
                                                : 'transparent',
                                            color: chosen
                                                ? 'var(--color-accent)'
                                                : 'inherit',
                                        }}
                                    >
                                        {option.label}
                                    </button>
                                );
                            })}
                        </div>
                        <InputError
                            message={errors.organization_size}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="tagline">Tagline</label>
                        <Input
                            id="tagline"
                            value={tagline}
                            maxLength={160}
                            placeholder="Operations systems for growing Bulacan businesses"
                            onChange={(e) => setTagline(e.target.value)}
                        />
                        <InputError
                            message={errors.tagline}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="business_description">
                            Description
                        </label>
                        <Textarea
                            id="business_description"
                            rows={4}
                            value={description}
                            placeholder="What does your company do, and who do you serve?"
                            onChange={(e) => setDescription(e.target.value)}
                        />
                        <InputError
                            message={errors.business_description}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter className="gap-2">
                <Btn
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={save}
                    data-test="save-company-button"
                >
                    {busy && <Spinner />}
                    Save
                </Btn>

                <Btn
                    type="button"
                    variant="ghost"
                    onClick={() => onOpenChange(false)}
                >
                    Cancel
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/**
 * "Company contacts" — how somebody reaches the business.
 */
export function CompanyContactsDialog({
    open,
    onOpenChange,
    profile,
    locations,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    profile: BusinessProfile;
    locations: Record<string, string[]>;
}) {
    const team = useCurrentTeam();

    const [form, setForm] = useState({
        owner_name: profile.ownerName ?? '',
        phone_number: profile.phoneNumber ?? '',
        contact_email: profile.contactEmail ?? '',
        address: profile.address ?? '',
        province: profile.province ?? '',
        city: profile.city ?? '',
        website_url: profile.websiteUrl ?? '',
        facebook_url: profile.facebookUrl ?? '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const set = (key: keyof typeof form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));

    const cities = form.province ? (locations[form.province] ?? []) : [];

    const save = () => {
        setBusy(true);

        router.patch(
            businessUpdate.url(team.slug),
            {
                business_name: profile.businessName,
                ...form,
                /* Empty selects post "", and every rule here is nullable. */
                province: form.province || null,
                city: form.city || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: setErrors,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Shell open={open} onOpenChange={onOpenChange} title="Company contacts">
            <div
                style={{
                    display: 'grid',
                    gap: 12,
                    maxHeight: '60vh',
                    overflowY: 'auto',
                }}
            >
                <div className="field">
                    <label htmlFor="owner_name">Owner</label>
                    <Input
                        id="owner_name"
                        value={form.owner_name}
                        onChange={(e) => set('owner_name', e.target.value)}
                    />
                    <InputError
                        message={errors.owner_name}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="phone_number">Phone</label>
                    <Input
                        id="phone_number"
                        inputMode="numeric"
                        value={form.phone_number}
                        placeholder="639175550142"
                        /* Digits only, which is what the rule accepts. */
                        onChange={(e) =>
                            set(
                                'phone_number',
                                e.target.value.replace(/\D/g, ''),
                            )
                        }
                    />
                    <InputError
                        message={errors.phone_number}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="contact_email">Contact email</label>
                    <Input
                        id="contact_email"
                        type="email"
                        value={form.contact_email}
                        onChange={(e) => set('contact_email', e.target.value)}
                    />
                    <InputError
                        message={errors.contact_email}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="address">Address</label>
                    <Input
                        id="address"
                        value={form.address}
                        onChange={(e) => set('address', e.target.value)}
                    />
                    <InputError
                        message={errors.address}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns:
                            'repeat(auto-fit, minmax(200px, 1fr))',
                        gap: 10,
                    }}
                >
                    <div className="field">
                        <label htmlFor="province">Province</label>
                        <Select
                            id="province"
                            value={form.province}
                            onChange={(e) => {
                                /* A new province orphans the chosen city. */
                                set('province', e.target.value);
                                set('city', '');
                            }}
                        >
                            <option value="">Not stated</option>
                            {Object.keys(locations).map((province) => (
                                <option key={province} value={province}>
                                    {province}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={errors.province}
                            className="mt-1 text-[11px]"
                        />
                    </div>

                    <div className="field">
                        <label htmlFor="city">City</label>
                        <Select
                            id="city"
                            value={form.city}
                            disabled={form.province === ''}
                            onChange={(e) => set('city', e.target.value)}
                        >
                            <option value="">Not stated</option>
                            {cities.map((city) => (
                                <option key={city} value={city}>
                                    {city}
                                </option>
                            ))}
                        </Select>
                        <InputError
                            message={errors.city}
                            className="mt-1 text-[11px]"
                        />
                    </div>
                </div>

                <div className="field">
                    <label htmlFor="website_url">Website</label>
                    <Input
                        id="website_url"
                        value={form.website_url}
                        placeholder="https://example.test"
                        onChange={(e) => set('website_url', e.target.value)}
                    />
                    <InputError
                        message={errors.website_url}
                        className="mt-1 text-[11px]"
                    />
                </div>

                <div className="field">
                    <label htmlFor="facebook_url">Facebook page</label>
                    <Input
                        id="facebook_url"
                        value={form.facebook_url}
                        placeholder="https://facebook.com/yourpage"
                        onChange={(e) => set('facebook_url', e.target.value)}
                    />
                    <InputError
                        message={errors.facebook_url}
                        className="mt-1 text-[11px]"
                    />
                </div>
            </div>

            <DialogFooter className="gap-2">
                <Btn
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={save}
                    data-test="save-contacts-button"
                >
                    {busy && <Spinner />}
                    Save
                </Btn>

                <Btn
                    type="button"
                    variant="ghost"
                    onClick={() => onOpenChange(false)}
                >
                    Cancel
                </Btn>
            </DialogFooter>
        </Shell>
    );
}

/** Kept beside the dialogs it belongs with. */
export { UserIcon };
