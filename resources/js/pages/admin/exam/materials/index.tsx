import { Head, router, useForm } from '@inertiajs/react';
import {
    ExternalLink,
    FileText,
    Image as ImageIcon,
    Music,
    Play,
    Plus,
    Upload,
} from 'lucide-react';
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
import { ConfirmDialog } from '@/components/confirm-dialog';
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
    mime_type: string | null;
};

type Option = { value: string; label: string };

const size = (bytes: number | null) =>
    bytes === null
        ? ''
        : bytes >= 1048576
          ? `${(bytes / 1048576).toFixed(1)} MB`
          : `${Math.max(1, Math.round(bytes / 1024))} KB`;

type Area = Option & { advanced: boolean };

/** What kind of material it is, in a word, from its file type or link. */
function kindOf(material: Material): { label: string; Icon: typeof FileText } {
    if (!material.is_file) {
        const host = hostOf(material.url);

        return /youtube\.com|youtu\.be|vimeo\.com/.test(host)
            ? { label: 'Video', Icon: Play }
            : { label: 'Link', Icon: ExternalLink };
    }
    const mime = material.mime_type ?? '';
    const name = (material.file_name ?? '').toLowerCase();

    if (mime === 'application/pdf' || name.endsWith('.pdf')) {
        return { label: 'PDF', Icon: FileText };
    }
    if (mime.startsWith('video/')) {
        return { label: 'Video', Icon: Play };
    }
    if (mime.startsWith('audio/')) {
        return { label: 'Audio', Icon: Music };
    }
    if (mime.startsWith('image/')) {
        return { label: 'Image', Icon: ImageIcon };
    }

    return { label: 'File', Icon: FileText };
}

function hostOf(url: string | null) {
    try {
        return url ? new URL(url).hostname.replace(/^www\./, '') : '';
    } catch {
        return '';
    }
}

function AddDialog({
    areas,
    maxMb,
    defaultArea,
    onClose,
}: {
    areas: Area[];
    maxMb: number;
    defaultArea: string;
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
        area: defaultArea,
        description: '',
        kind: 'file',
        file: null,
        url: '',
    });
    const { data, setData, errors } = form;
    const [dragging, setDragging] = useState(false);

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
                        hint="Leave on General for guides that cover every area."
                        error={errors.area}
                    >
                        <select
                            id="area"
                            value={data.area}
                            onChange={(e) => setData('area', e.target.value)}
                            className={selectClass}
                        >
                            <option value="">General</option>
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
                            <span className="text-sm font-semibold">File</span>
                            <label
                                htmlFor="file"
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    setDragging(false);
                                    setData(
                                        'file',
                                        e.dataTransfer.files[0] ?? null,
                                    );
                                }}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-control border-2 border-dashed px-4 py-6 text-center text-sm transition-colors',
                                    'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                    dragging
                                        ? 'border-brand-crimson-strong bg-brand-crimson-strong/5 dark:border-brand-crimson'
                                        : errors.file
                                          ? 'border-destructive'
                                          : 'border-border hover:border-foreground/40',
                                )}
                            >
                                {data.file ? (
                                    <>
                                        <FileText
                                            aria-hidden
                                            className="size-6 text-brand-navy dark:text-brand-on-dark"
                                        />
                                        <span className="max-w-full truncate font-semibold">
                                            {data.file.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {size(data.file.size)}. Choose
                                            another to replace it.
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <Upload
                                            aria-hidden
                                            className="size-6 text-muted-foreground"
                                        />
                                        <span className="font-semibold">
                                            Drop a file here, or choose one
                                        </span>
                                        <span className="text-muted-foreground">
                                            Up to {maxMb} MB
                                        </span>
                                    </>
                                )}
                                <input
                                    id="file"
                                    type="file"
                                    onChange={(e) =>
                                        setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="sr-only"
                                />
                            </label>
                            <p className="text-sm text-muted-foreground">
                                PDF, Word, PowerPoint, Excel, text, image, audio
                                or video. Share longer videos as a link.
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

function DeleteDialog({
    material,
    onClose,
}: {
    material: Material;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <ConfirmDialog
            open
            title="Delete this material?"
            description={`${material.title}. ${material.is_file ? 'The file is deleted too. ' : ''}This cannot be undone. To keep it but stop applicants seeing it, hide it instead.`}
            confirmLabel="Delete"
            cancelLabel="Keep it"
            tone="danger"
            processing={processing}
            onCancel={onClose}
            onConfirm={() => {
                setProcessing(true);
                router.delete(destroy.url(material.id), {
                    preserveScroll: true,
                    onFinish: onClose,
                });
            }}
        />
    );
}

function MaterialRow({
    material,
    onDelete,
}: {
    material: Material;
    onDelete: () => void;
}) {
    const { label, Icon } = kindOf(material);

    return (
        <li
            className={cn(
                'grid gap-3 px-4 py-3 md:px-6 @2xl:grid-cols-[minmax(0,1fr)_auto] @2xl:items-center',
                !material.published && 'bg-muted/40',
            )}
        >
            <div className="flex min-w-0 items-start gap-3">
                <span
                    aria-hidden
                    className="flex size-9 shrink-0 items-center justify-center rounded-control bg-muted text-muted-foreground"
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <a
                            href={material.url ?? '#'}
                            target="_blank"
                            rel="noopener"
                            className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                        >
                            {material.title}
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                        {!material.published && (
                            <span className="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-border ring-inset">
                                Hidden from applicants
                            </span>
                        )}
                    </p>
                    <p className="text-sm [overflow-wrap:anywhere] text-muted-foreground">
                        {label}
                        {material.is_file
                            ? `, ${material.file_name}, ${size(material.size)}`
                            : hostOf(material.url) &&
                              `, ${hostOf(material.url)}`}
                    </p>
                    {material.description && (
                        <p className="mt-1 max-w-prose text-sm">
                            {material.description}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex gap-2 pl-12 @2xl:pl-0">
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                        router.patch(
                            update.url(material.id),
                            { published: !material.published },
                            { preserveScroll: true },
                        )
                    }
                >
                    {material.published ? 'Hide' : 'Publish'}
                    <span className="sr-only"> {material.title}</span>
                </Button>
                <Button size="sm" variant="ghost" onClick={onDelete}>
                    Delete
                    <span className="sr-only"> {material.title}</span>
                </Button>
            </div>
        </li>
    );
}

export default function MaterialsIndex({
    materials,
    areas,
    maxMb,
}: {
    materials: Material[];
    areas: Area[];
    maxMb: number;
}) {
    const [adding, setAdding] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<Material | null>(null);
    const forArea = (value: string) =>
        materials.filter((material) => (material.area ?? '') === value);
    const covered = areas.filter((area) => forArea(area.value).length > 0);
    const hidden = materials.filter((material) => !material.published).length;

    const group = (value: string, label: string) => {
        const items = forArea(value);
        const id = `area-${value || 'general'}`;

        return (
            <section
                key={id}
                aria-labelledby={id}
                className="@container border-t border-border first:border-t-0"
            >
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 md:px-6">
                    <h3 id={id} className="font-semibold">
                        {label}
                        {items.length > 0 && (
                            <span className="ml-2 font-normal text-muted-foreground">
                                {items.length}
                            </span>
                        )}
                    </h3>
                    {items.length === 0 ? (
                        <span className="flex items-center gap-3 text-sm">
                            <span className="text-muted-foreground">
                                Nothing yet
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setAdding(value)}
                            >
                                <Plus />
                                Add for this area
                                <span className="sr-only">: {label}</span>
                            </Button>
                        </span>
                    ) : (
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setAdding(value)}
                        >
                            <Plus />
                            Add
                            <span className="sr-only"> to {label}</span>
                        </Button>
                    )}
                </div>
                {items.length > 0 && (
                    <ul className="divide-y divide-border border-t border-border">
                        {items.map((material) => (
                            <MaterialRow
                                key={material.id}
                                material={material}
                                onDelete={() => setDeleting(material)}
                            />
                        ))}
                    </ul>
                )}
            </section>
        );
    };

    return (
        <>
            <Head title="Prep materials" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="mb-8 flex flex-col gap-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
                    <div className="max-w-prose [&>header]:mb-0">
                        <Heading
                            title="Prep materials"
                            description="Guides, readings and videos that help applicants prepare. They open once an applicant has paid the exam fee."
                        />
                    </div>
                    <Button
                        size="lg"
                        className="shrink-0"
                        onClick={() => setAdding('')}
                    >
                        <Plus />
                        Add a material
                    </Button>
                </div>

                <div className="grid gap-6 *:min-w-0">
                    <p className="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-card bg-brand-navy px-5 py-4 text-white md:px-6 dark:bg-brand-night-raised">
                        <span>
                            <span className="text-heading03 font-bold">
                                {covered.length} of {areas.length}
                            </span>{' '}
                            <span className="text-brand-on-dark">
                                areas have materials
                            </span>
                        </span>
                        <span className="text-brand-on-dark">
                            {materials.length}{' '}
                            {materials.length === 1 ? 'material' : 'materials'}
                            {forArea('').length > 0 &&
                                `, ${forArea('').length} general`}
                            {hidden > 0 && `, ${hidden} hidden`}
                        </span>
                    </p>

                    <div className="rounded-card border border-border bg-background">
                        {group('', 'General: every area')}
                    </div>

                    {[false, true].map((advanced) => (
                        <div key={String(advanced)} className="grid gap-3">
                            <h2 className="text-heading03 font-semibold">
                                {advanced
                                    ? 'Advanced areas: Fellows and Members only'
                                    : 'Basic areas: every grade'}
                            </h2>
                            <div className="rounded-card border border-border bg-background">
                                {areas
                                    .filter(
                                        (area) => area.advanced === advanced,
                                    )
                                    .map((area) =>
                                        group(area.value, area.label),
                                    )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {adding !== null && (
                <AddDialog
                    areas={areas}
                    maxMb={maxMb}
                    defaultArea={adding}
                    onClose={() => setAdding(null)}
                />
            )}
            {deleting && (
                <DeleteDialog
                    material={deleting}
                    onClose={() => setDeleting(null)}
                />
            )}
        </>
    );
}

MaterialsIndex.layout = {
    breadcrumbs: [{ title: 'Prep materials', href: index() }],
};
