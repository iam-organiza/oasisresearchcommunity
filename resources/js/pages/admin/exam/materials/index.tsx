import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    ChoiceTiles,
    Field,
    fieldClass,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, index, store, update } from '@/routes/admin/exam/materials';

type Material = {
    id: number;
    title: string;
    area: string | null;
    area_label: string;
    description: string | null;
    url: string | null;
    is_file: boolean;
    file_name: string | null;
    size: number | null;
    published: boolean;
};

type Option = { value: string; label: string };

const size = (bytes: number | null) =>
    bytes === null
        ? ''
        : bytes >= 1048576
          ? `${(bytes / 1048576).toFixed(1)} MB`
          : `${Math.max(1, Math.round(bytes / 1024))} KB`;

function AddDialog({
    areas,
    maxMb,
    onClose,
}: {
    areas: Option[];
    maxMb: number;
    onClose: () => void;
}) {
    const form = useForm<{
        title: string;
        area: string;
        description: string;
        kind: string;
        file: File | null;
        url: string;
    }>({
        title: '',
        area: '',
        description: '',
        kind: 'file',
        file: null,
        url: '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="@container max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} noValidate className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Add a prep material</DialogTitle>
                        <DialogDescription>
                            Applicants see it once they have paid the exam fee.
                        </DialogDescription>
                    </DialogHeader>
                    <Field id="title" label="Title" error={errors.title}>
                        <Input
                            id="title"
                            value={data.title}
                            aria-invalid={errors.title ? true : undefined}
                            onChange={(e) => setData('title', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="area"
                        label="Research area"
                        hint="Leave on All areas for general guides."
                        error={errors.area}
                    >
                        <select
                            id="area"
                            value={data.area}
                            onChange={(e) => setData('area', e.target.value)}
                            className={selectClass}
                        >
                            <option value="">All areas</option>
                            {areas.map((area) => (
                                <option key={area.value} value={area.value}>
                                    {area.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="description"
                        label="What it covers"
                        error={errors.description}
                        optional
                    >
                        <textarea
                            id="description"
                            rows={2}
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            className={fieldClass}
                        />
                    </Field>
                    <ChoiceTiles
                        name="kind"
                        legend="It is"
                        columns="two"
                        options={[
                            { value: 'file', label: 'A file' },
                            { value: 'link', label: 'A link' },
                        ]}
                        value={data.kind}
                        onChange={(kind) => setData('kind', kind)}
                    />
                    {data.kind === 'file' ? (
                        <div className="grid gap-2">
                            <label
                                htmlFor="file"
                                className="text-sm font-semibold"
                            >
                                File
                            </label>
                            <input
                                id="file"
                                type="file"
                                onChange={(e) =>
                                    setData('file', e.target.files?.[0] ?? null)
                                }
                                className="text-sm file:mr-3 file:rounded-control file:border file:border-border file:bg-background file:px-3 file:py-1.5 file:font-semibold"
                            />
                            <p className="text-sm text-muted-foreground">
                                PDF, Word, PowerPoint, Excel, text, image, audio
                                or video, up to {maxMb} MB. Share longer videos
                                as a link.
                            </p>
                            <InputError
                                className="font-semibold text-destructive"
                                message={errors.file}
                            />
                        </div>
                    ) : (
                        <Field id="url" label="Link" error={errors.url}>
                            <Input
                                id="url"
                                type="url"
                                placeholder="https://"
                                value={data.url}
                                aria-invalid={errors.url ? true : undefined}
                                onChange={(e) => setData('url', e.target.value)}
                            />
                        </Field>
                    )}
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Add the material
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function MaterialsIndex({
    materials,
    areas,
    maxMb,
}: {
    materials: Material[];
    areas: Option[];
    maxMb: number;
}) {
    const [adding, setAdding] = useState(false);
    const groups = [{ value: '', label: 'All areas' }, ...areas]
        .map((area) => ({
            ...area,
            items: materials.filter(
                (material) => (material.area ?? '') === area.value,
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <>
            <Head title="Prep materials" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Prep materials"
                        description="Guides, readings and videos that help applicants prepare. They open once an applicant has paid the exam fee."
                    />
                    <Button
                        size="lg"
                        className="mb-8"
                        onClick={() => setAdding(true)}
                    >
                        <Plus />
                        Add a material
                    </Button>
                </div>

                {groups.length === 0 ? (
                    <p className="rounded-card border border-border bg-background p-10 text-center text-muted-foreground">
                        No prep materials yet. Add a general guide, or one for
                        each research area.
                    </p>
                ) : (
                    <div className="grid gap-6">
                        {groups.map((group) => (
                            <section
                                key={group.value || 'general'}
                                aria-labelledby={`group-${group.value || 'general'}`}
                                className="rounded-card border border-border bg-background"
                            >
                                <h2
                                    id={`group-${group.value || 'general'}`}
                                    className="border-b border-border p-4 font-semibold md:px-6"
                                >
                                    {group.label}
                                </h2>
                                <ul className="divide-y divide-border">
                                    {group.items.map((material) => (
                                        <li
                                            key={material.id}
                                            className={cn(
                                                'flex flex-col gap-3 p-4 md:flex-row md:items-center md:px-6',
                                                !material.published &&
                                                    'bg-muted/40',
                                            )}
                                        >
                                            <div className="flex min-w-0 flex-1 items-start gap-3">
                                                {material.is_file ? (
                                                    <FileText
                                                        aria-hidden
                                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                    />
                                                ) : (
                                                    <ExternalLink
                                                        aria-hidden
                                                        className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                                    />
                                                )}
                                                <div className="min-w-0">
                                                    <a
                                                        href={
                                                            material.url ?? '#'
                                                        }
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                                    >
                                                        {material.title}
                                                    </a>
                                                    <p className="text-sm break-words text-muted-foreground">
                                                        {material.is_file
                                                            ? `${material.file_name} · ${size(material.size)}`
                                                            : material.url}
                                                        {!material.published &&
                                                            ' · Hidden from applicants'}
                                                    </p>
                                                    {material.description && (
                                                        <p className="mt-1 max-w-prose text-sm">
                                                            {
                                                                material.description
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.patch(
                                                            update.url(
                                                                material.id,
                                                            ),
                                                            {
                                                                published:
                                                                    !material.published,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {material.published
                                                        ? 'Hide'
                                                        : 'Publish'}
                                                    <span className="sr-only">
                                                        {' '}
                                                        {material.title}
                                                    </span>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Delete "${material.title}"? This cannot be undone.`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                destroy.url(
                                                                    material.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Delete
                                                    <span className="sr-only">
                                                        {' '}
                                                        {material.title}
                                                    </span>
                                                </Button>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            {adding && (
                <AddDialog
                    areas={areas}
                    maxMb={maxMb}
                    onClose={() => setAdding(false)}
                />
            )}
        </>
    );
}

MaterialsIndex.layout = {
    breadcrumbs: [{ title: 'Prep materials', href: index() }],
};
