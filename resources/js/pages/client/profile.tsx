import { Head, useForm } from '@inertiajs/react';
import Field from '@/components/sdpc/field';
import Meter from '@/components/sdpc/meter';
import { Panel, PanelKicker } from '@/components/sdpc/panel';
import { Tag } from '@/components/sdpc/tag';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { update as clientProfileUpdate } from '@/routes/client-profile';
import {
    destroy as testimonialDestroy,
    update as testimonialUpdate,
} from '@/routes/testimonial';

type Props = {
    profile: {
        businessName: string;
        businessDescription: string | null;
        ownerName: string | null;
        address: string | null;
        city: string | null;
        province: string | null;
        phoneNumber: string | null;
        contactEmail: string | null;
        websiteUrl: string | null;
        facebookUrl: string | null;
        logoUrl: string | null;
        hasPermit: boolean;
        verificationLabel: string;
        verificationTagVariant: string;
        completion: number;
    };
    testimonial: {
        body: string;
        authorTitle: string | null;
        updatedAt: string | null;
    } | null;
    canPublishTestimonial: boolean;
    canUpdate: boolean;
};

export default function ClientProfilePage({
    profile,
    testimonial,
    canPublishTestimonial,
    canUpdate,
}: Props) {
    const team = useCurrentTeam();

    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm({
            _method: 'patch',
            business_name: profile.businessName ?? '',
            business_description: profile.businessDescription ?? '',
            owner_name: profile.ownerName ?? '',
            address: profile.address ?? '',
            city: profile.city ?? '',
            province: profile.province ?? '',
            phone_number: profile.phoneNumber ?? '',
            contact_email: profile.contactEmail ?? '',
            website_url: profile.websiteUrl ?? '',
            facebook_url: profile.facebookUrl ?? '',
            logo: null as File | null,
            permit: null as File | null,
        });

    /*
     * Kept out of the profile form on purpose: this one is public copy on the
     * landing page, saved and withdrawn on its own, and a client should be
     * able to pull a quote down without re-submitting their whole business.
     */
    const quote = useForm({
        body: testimonial?.body ?? '',
        author_title: testimonial?.authorTitle ?? '',
    });

    return (
        <>
            <Head title="Business profile" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    /* POST with _method spoofing so file uploads reach a PATCH route. */
                    post(clientProfileUpdate.url(team.slug), {
                        preserveScroll: true,
                        forceFormData: true,
                    });
                }}
                className="mx-auto grid max-w-[1060px] items-start gap-6 px-8 pt-6 pb-[72px] lg:grid-cols-[minmax(0,1fr)_300px]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <div className="flex items-end gap-4">
                        <div className="mr-auto">
                            <h3 className="m-0">Business profile</h3>
                            <div className="text-[13px] text-muted-foreground">
                                Students see this when they consider your
                                postings.
                            </div>
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

                    <Panel padding="lg" gap="lg">
                        <h6 className="m-0">The business</h6>

                        <Field
                            label="Business name"
                            error={errors.business_name}
                            required
                        >
                            {(props) => (
                                <Input
                                    {...props}
                                    value={data.business_name}
                                    onChange={(e) =>
                                        setData('business_name', e.target.value)
                                    }
                                />
                            )}
                        </Field>

                        <Field
                            label="Business description"
                            error={errors.business_description}
                        >
                            {(props) => (
                                <textarea
                                    {...props}
                                    className="min-h-[100px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={data.business_description}
                                    onChange={(e) =>
                                        setData(
                                            'business_description',
                                            e.target.value,
                                        )
                                    }
                                />
                            )}
                        </Field>

                        <div className="grid gap-3.5 sm:grid-cols-2">
                            <Field label="Owner name" error={errors.owner_name}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.owner_name}
                                        onChange={(e) =>
                                            setData(
                                                'owner_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field label="Business logo" error={errors.logo}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) =>
                                            setData(
                                                'logo',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                    </Panel>

                    <Panel padding="lg" gap="lg">
                        <h6 className="m-0">Contact</h6>

                        <Field label="Address" error={errors.address}>
                            {(props) => (
                                <Input
                                    {...props}
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                            )}
                        </Field>

                        <div className="grid gap-3.5 sm:grid-cols-2">
                            <Field label="City" error={errors.city}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.city}
                                        onChange={(e) =>
                                            setData('city', e.target.value)
                                        }
                                    />
                                )}
                            </Field>

                            <Field label="Province" error={errors.province}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.province}
                                        onChange={(e) =>
                                            setData('province', e.target.value)
                                        }
                                    />
                                )}
                            </Field>

                            <Field
                                label="Phone number"
                                error={errors.phone_number}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        value={data.phone_number}
                                        onChange={(e) =>
                                            setData(
                                                'phone_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field
                                label="Contact email"
                                error={errors.contact_email}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="email"
                                        value={data.contact_email}
                                        onChange={(e) =>
                                            setData(
                                                'contact_email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field label="Website" error={errors.website_url}>
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="url"
                                        placeholder="https://"
                                        value={data.website_url}
                                        onChange={(e) =>
                                            setData(
                                                'website_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>

                            <Field
                                label="Facebook page"
                                error={errors.facebook_url}
                            >
                                {(props) => (
                                    <Input
                                        {...props}
                                        type="url"
                                        placeholder="https://facebook.com/"
                                        value={data.facebook_url}
                                        onChange={(e) =>
                                            setData(
                                                'facebook_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                )}
                            </Field>
                        </div>
                    </Panel>

                    <Panel padding="lg" gap="lg">
                        <h6 className="m-0">Verification</h6>
                        <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                            Upload your business permit for an administrator to
                            review. Replacing it returns the profile to pending.
                        </p>

                        <Field label="Business permit" error={errors.permit}>
                            {(props) => (
                                <Input
                                    {...props}
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    onChange={(e) =>
                                        setData(
                                            'permit',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            )}
                        </Field>

                        {profile.hasPermit && (
                            <span className="text-[11.5px] text-muted-foreground">
                                A permit is already on file.
                            </span>
                        )}
                    </Panel>

                    {canUpdate && (
                        <div className="flex items-center gap-2.5">
                            {recentlySuccessful && (
                                <span className="mr-auto text-[12px] text-primary">
                                    Saved.
                                </span>
                            )}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="ml-auto px-5"
                            >
                                {processing ? 'Saving…' : 'Save profile'}
                            </Button>
                        </div>
                    )}
                </div>

                <aside className="sticky top-[88px] flex flex-col gap-4">
                    <Panel padding="lg" gap="lg">
                        <PanelKicker>Profile completion</PanelKicker>
                        <Meter label="Filled in" value={profile.completion} />
                        <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                            A complete profile gives students the context they
                            need to take your postings seriously.
                        </p>
                    </Panel>

                    {profile.logoUrl && (
                        <Panel padding="lg" gap="sm">
                            <PanelKicker>Current logo</PanelKicker>
                            <img
                                src={profile.logoUrl}
                                alt=""
                                className="max-w-full rounded-md"
                            />
                        </Panel>
                    )}
                </aside>
            </form>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    quote.put(testimonialUpdate.url(team.slug), {
                        preserveScroll: true,
                    });
                }}
                className="mx-auto max-w-[1060px] px-8 pb-[72px]"
            >
                <Panel padding="lg" gap="lg">
                    <div className="flex items-end gap-4">
                        <div className="mr-auto">
                            <h6 className="m-0">Your words on the homepage</h6>
                            <p className="m-0 text-[12.5px] leading-relaxed text-muted-foreground">
                                Tell visitors what working with a student team
                                was like. It appears on the public landing page
                                under your business name, and stays there until
                                you remove it.
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
                            Your business needs to be verified before your
                            quote can go on the homepage.
                        </p>
                    )}

                    <Field label="What you would tell another business" error={quote.errors.body}>
                        {(props) => (
                            <textarea
                                {...props}
                                className="min-h-[110px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                                maxLength={400}
                                disabled={!canPublishTestimonial}
                                placeholder="We had a spec sitting in a folder for two years…"
                                value={quote.data.body}
                                onChange={(e) =>
                                    quote.setData('body', e.target.value)
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
                                        testimonialDestroy.url(team.slug),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Remove from homepage
                            </Button>
                        )}

                        <Button
                            type="submit"
                            disabled={quote.processing || !canPublishTestimonial}
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
        </>
    );
}
