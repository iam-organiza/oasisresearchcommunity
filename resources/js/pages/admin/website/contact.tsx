import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { history, update } from '@/routes/admin/website';

const PLATFORMS = ['Facebook', 'Instagram', 'LinkedIn', 'WhatsApp'] as const;

type Contact = {
    tagline: string;
    support_email: string;
    phones: string[];
    address: string[];
    map_embed_url: string;
    social: Record<string, string>;
};

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="@container rounded-card border border-border bg-background p-6 md:p-8">
            <h2 className="text-heading03 font-semibold">{title}</h2>
            {description && (
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            <div className="mt-6 grid gap-6">{children}</div>
        </section>
    );
}

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id} className="font-semibold">
                {label}
            </Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
    );
}

/** The map preview only loads a Google Maps embed, never anything else typed in. */
const mapPreviewUrl = (value: string): string | null => {
    const src = value.match(/src=["']([^"']+)["']/i)?.[1] ?? value.trim();

    try {
        const url = new URL(src.replaceAll('&amp;', '&'));

        return url.protocol === 'https:' &&
            url.hostname === 'www.google.com' &&
            url.pathname.startsWith('/maps/embed')
            ? url.toString()
            : null;
    } catch {
        return null;
    }
};

/**
 * The organisation's contact details and social links. Admins only: they
 * appear in the site's header and footer, the Contact Us section, every
 * email and receipt, and the privacy policy.
 */
export default function ContactSettings({
    sections,
}: {
    sections: { key: string; values: Contact }[];
}) {
    const values = sections[0].values;
    const form = useForm<Contact>({
        ...values,
        phones: [0, 1, 2].map((i) => values.phones[i] ?? ''),
        address: [0, 1, 2].map((i) => values.address[i] ?? ''),
    });
    const { data, setData, processing } = form;
    const errors = form.errors as Record<string, string | undefined>;
    const leaveDialog = useUnsavedChanges(form.isDirty, 'the contact details');
    const preview = mapPreviewUrl(data.map_embed_url);

    const setListItem = (
        field: 'phones' | 'address',
        index: number,
        value: string,
    ) =>
        setData(
            field,
            data[field].map((item, i) => (i === index ? value : item)),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url('contact'), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <>
            <Head title="Contact & social" />
            {leaveDialog}
            <form
                onSubmit={submit}
                noValidate
                className="px-4 py-8 md:px-8 md:py-10 xl:px-12"
            >
                <Heading
                    title="Contact & social"
                    description="Shown in the site's header and footer, the Contact Us section, emails, receipts and the privacy policy. Saving updates them all at once."
                />

                <div className="grid items-start gap-6 2xl:grid-cols-2">
                    <Card title="How to reach us">
                        <Field
                            id="tagline"
                            label="Tagline"
                            hint="Under the name on the home page and in the footer."
                            error={errors.tagline}
                        >
                            <Input
                                id="tagline"
                                value={data.tagline}
                                maxLength={60}
                                onChange={(event) =>
                                    setData('tagline', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="support_email"
                            label="Email address"
                            hint="Where readers, members and applicants write to. Shown on every email and receipt."
                            error={errors.support_email}
                        >
                            <Input
                                id="support_email"
                                type="email"
                                value={data.support_email}
                                onChange={(event) =>
                                    setData('support_email', event.target.value)
                                }
                            />
                        </Field>
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-semibold">
                                Phone numbers
                            </legend>
                            <div className="grid gap-3 @xl:grid-cols-3">
                                {data.phones.map((phone, i) => (
                                    <div key={i} className="grid gap-1">
                                        <Input
                                            aria-label={`Phone number ${i + 1}`}
                                            value={phone}
                                            placeholder={
                                                i === 0
                                                    ? '+234 703 635 6635'
                                                    : 'Optional'
                                            }
                                            onChange={(event) =>
                                                setListItem(
                                                    'phones',
                                                    i,
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            className="font-semibold text-destructive"
                                            message={errors[`phones.${i}`]}
                                        />
                                    </div>
                                ))}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Up to three, with the country code. Leave a box
                                empty to show fewer.
                            </p>
                            <InputError
                                className="font-semibold text-destructive"
                                message={errors.phones}
                            />
                        </fieldset>
                    </Card>

                    <Card title="Address and map">
                        <div className="grid gap-6 @xl:grid-cols-3">
                            {(
                                [
                                    ['Office', 'National Secretariat'],
                                    ['Street', 'No. 11 Banjul Street, Wuse 2,'],
                                    ['Town and country', 'Abuja, Nigeria'],
                                ] as const
                            ).map(([label, placeholder], i) => (
                                <Field
                                    key={label}
                                    id={`address-${i}`}
                                    label={label}
                                    error={errors[`address.${i}`]}
                                >
                                    <Input
                                        id={`address-${i}`}
                                        value={data.address[i]}
                                        placeholder={placeholder}
                                        onChange={(event) =>
                                            setListItem(
                                                'address',
                                                i,
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            ))}
                        </div>
                        <Field
                            id="map_embed_url"
                            label="Map"
                            hint="In Google Maps, find the office, choose Share, then Embed a map, then Copy HTML, and paste it here."
                            error={errors.map_embed_url}
                        >
                            <textarea
                                id="map_embed_url"
                                rows={3}
                                value={data.map_embed_url}
                                onChange={(event) =>
                                    setData('map_embed_url', event.target.value)
                                }
                                className="w-full rounded-control border border-input bg-background px-3 py-2 font-mono text-xs break-all outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20"
                            />
                        </Field>
                        {preview ? (
                            <iframe
                                src={preview}
                                title="Map preview"
                                loading="lazy"
                                referrerPolicy="no-referrer-when-downgrade"
                                className="h-56 w-full rounded-control border border-border"
                            />
                        ) : (
                            <p className="rounded-control bg-muted px-4 py-3 text-sm text-muted-foreground">
                                The map preview appears here once a Google Maps
                                embed is pasted.
                            </p>
                        )}
                    </Card>

                    <Card
                        title="Social links"
                        description="Shown as icons in the header and footer. Leave a box empty to hide that icon."
                    >
                        <div className="grid gap-6 @xl:grid-cols-2">
                            {PLATFORMS.map((name) => (
                                <Field
                                    key={name}
                                    id={`social-${name}`}
                                    label={name}
                                    error={errors[`social.${name}`]}
                                >
                                    <Input
                                        id={`social-${name}`}
                                        type="url"
                                        value={data.social[name] ?? ''}
                                        placeholder="https://"
                                        onChange={(event) =>
                                            setData('social', {
                                                ...data.social,
                                                [name]: event.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            ))}
                        </div>
                    </Card>
                </div>

                <div className="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <Button type="submit" size="lg" disabled={processing}>
                        {processing ? 'Saving' : 'Save contact details'}
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        Every save is kept in the{' '}
                        <Link
                            href={history()}
                            className="font-semibold text-foreground underline underline-offset-4"
                        >
                            change history
                        </Link>
                        , where any earlier version can be put back.
                    </p>
                </div>
            </form>
        </>
    );
}

ContactSettings.layout = {
    breadcrumbs: [
        { title: 'Contact & social', href: '/admin/website/contact' },
    ],
};
