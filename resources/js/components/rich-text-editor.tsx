import Image from '@tiptap/extension-image';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useRef, useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import type { ReactNode } from 'react';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    value: string;
    onChange: (html: string) => void;
    uploadUrl: string;
};

// The visual editor keeps only the formatting it knows. Articles written on
// the first site can hold tables or embedded video, which it would drop, so
// those open as HTML and stay that way until the writer chooses otherwise.
const NEEDS_HTML_MODE = /<(table|iframe|video|figure|script|style)\b/i;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function ToolButton({
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'min-h-9 rounded-control px-2.5 text-sm font-medium hover:bg-accent disabled:opacity-60',
                active && 'bg-primary text-primary-foreground hover:bg-primary',
            )}
        >
            {children}
        </button>
    );
}

function Toolbar({
    editor,
    uploading,
    onLink,
    onPickImage,
}: {
    editor: Editor;
    uploading: boolean;
    onLink: () => void;
    onPickImage: () => void;
}) {
    return (
        <div
            role="toolbar"
            aria-label="Formatting"
            className="flex flex-wrap gap-1 border-b border-border p-2"
        >
            <ToolButton
                active={editor.isActive('heading', { level: 2 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                }
            >
                Heading
            </ToolButton>
            <ToolButton
                active={editor.isActive('heading', { level: 3 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
            >
                Subheading
            </ToolButton>
            <ToolButton
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
            >
                Bold
            </ToolButton>
            <ToolButton
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            >
                Italic
            </ToolButton>
            <ToolButton
                active={editor.isActive('underline')}
                onClick={() => editor.chain().focus().toggleUnderline().run()}
            >
                Underline
            </ToolButton>
            <ToolButton
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            >
                Bullets
            </ToolButton>
            <ToolButton
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            >
                Numbers
            </ToolButton>
            <ToolButton
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
            >
                Quote
            </ToolButton>
            <ToolButton active={editor.isActive('link')} onClick={onLink}>
                Link
            </ToolButton>
            <ToolButton onClick={onPickImage} disabled={uploading}>
                {uploading ? 'Uploading' : 'Image'}
            </ToolButton>
        </div>
    );
}

/** Adds "https://" when someone types a bare address like "example.com". */
function normaliseLink(raw: string): string {
    const url = raw.trim();

    if (url === '' || /^(https?:|mailto:|tel:|\/|#)/i.test(url)) {
        return url;
    }

    return url.includes('@') && !url.includes('/')
        ? `mailto:${url}`
        : `https://${url}`;
}

/**
 * The site's own box for adding, changing or removing a link, in place of
 * the browser's pop-up.
 */
function LinkDialog({
    open,
    current,
    onSave,
    onRemove,
    onClose,
}: {
    open: boolean;
    current: string;
    onSave: (url: string) => void;
    onRemove: () => void;
    onClose: () => void;
}) {
    const [url, setUrl] = useState(current);

    useEffect(() => {
        if (open) {
            setUrl(current);
        }
    }, [open, current]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                {/* Inside the post's form in React's tree, so its submit must not reach that form. */}
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        const link = normaliseLink(url);

                        if (link === '') {
                            onRemove();
                        } else {
                            onSave(link);
                        }
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {current ? 'Change the link' : 'Add a link'}
                        </DialogTitle>
                        <DialogDescription>
                            The selected words link to this address.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="link-url">Address</Label>
                        <Input
                            id="link-url"
                            value={url}
                            onChange={(event) => setUrl(event.target.value)}
                            placeholder="https://example.com"
                            autoFocus
                        />
                    </div>
                    <DialogFooter className="gap-2">
                        {current && (
                            <Button
                                type="button"
                                variant="outline"
                                className="sm:mr-auto"
                                onClick={onRemove}
                            >
                                Remove link
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit">
                            {current ? 'Save link' : 'Add link'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RichTextEditor({
    id,
    value,
    onChange,
    uploadUrl,
}: Props) {
    const [htmlMode, setHtmlMode] = useState(() => NEEDS_HTML_MODE.test(value));
    const [uploadError, setUploadError] = useState('');
    const [uploading, setUploading] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [currentLink, setCurrentLink] = useState('');
    const fileInput = useRef<HTMLInputElement>(null);
    const altInput = useRef<HTMLInputElement>(null);

    const editor = useEditor({
        extensions: [StarterKit, Image],
        content: value,
        editorProps: {
            attributes: {
                id,
                class: 'article-body min-h-72 p-4 focus:outline-none',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // The picture the cursor is on, so its description can be written.
    const selectedImage = useEditorState({
        editor,
        selector: ({ editor: current }) =>
            current?.isActive('image')
                ? { alt: String(current.getAttributes('image').alt ?? '') }
                : null,
    });

    const uploadImage = async (file: File) => {
        setUploadError('');
        setUploading(true);

        const body = new FormData();
        body.append('image', file);

        let response: Response;

        try {
            response = await fetch(uploadUrl, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });
        } catch {
            setUploading(false);
            setUploadError(
                'The image could not be uploaded. Check the connection and try again.',
            );

            return;
        }

        setUploading(false);

        if (!response.ok) {
            const problem = (await response.json().catch(() => null)) as {
                message?: string;
            } | null;
            setUploadError(
                problem?.message ??
                    'The image could not be uploaded. Try again.',
            );

            return;
        }

        const { url } = (await response.json()) as { url: string };

        if (!editor) {
            return;
        }

        // Insert the picture, select it and ask for its description straight away.
        editor.chain().focus().setImage({ src: url, alt: '' }).run();

        let imageAt: number | null = null;
        editor.state.doc.descendants((node, pos) => {
            if (node.type.name === 'image' && node.attrs.src === url) {
                imageAt = pos;
            }
        });

        if (imageAt !== null) {
            editor.commands.setNodeSelection(imageAt);
            window.setTimeout(() => altInput.current?.focus(), 0);
        }
    };

    const { confirm, dialog } = useConfirm();
    const switchMode = async () => {
        if (htmlMode) {
            if (
                NEEDS_HTML_MODE.test(value) &&
                !(await confirm({
                    title: 'Switch to the visual editor?',
                    description:
                        'This article has tables or embedded media. The visual editor removes them.',
                    confirmLabel: 'Switch anyway',
                    cancelLabel: 'Stay in HTML',
                    tone: 'danger',
                }))
            ) {
                return;
            }

            editor?.commands.setContent(value);
        }

        setHtmlMode(!htmlMode);
    };

    return (
        <div>
            {dialog}
            <div className="overflow-hidden rounded-control border border-input bg-background">
                {htmlMode ? (
                    <textarea
                        id={id}
                        value={value}
                        onChange={(event) => onChange(event.target.value)}
                        rows={18}
                        spellCheck={false}
                        className="block w-full p-4 font-mono text-sm focus:outline-none"
                    />
                ) : (
                    editor && (
                        <>
                            <Toolbar
                                editor={editor}
                                uploading={uploading}
                                onLink={() => {
                                    setCurrentLink(
                                        String(
                                            editor.getAttributes('link').href ??
                                                '',
                                        ),
                                    );
                                    setLinkOpen(true);
                                }}
                                onPickImage={() => fileInput.current?.click()}
                            />
                            {selectedImage && (
                                <div className="grid gap-1.5 border-b border-border bg-muted/40 p-3">
                                    <Label htmlFor={`${id}-alt`}>
                                        Describe this picture
                                    </Label>
                                    <Input
                                        ref={altInput}
                                        id={`${id}-alt`}
                                        value={selectedImage.alt}
                                        placeholder="What it shows, for people who cannot see it"
                                        onChange={(event) =>
                                            editor
                                                .chain()
                                                .updateAttributes('image', {
                                                    alt: event.target.value,
                                                })
                                                .run()
                                        }
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Read aloud by screen readers and shown
                                        if the picture cannot load. Leave it
                                        empty only for a purely decorative
                                        picture.
                                    </p>
                                </div>
                            )}
                            <EditorContent editor={editor} />
                        </>
                    )
                )}
            </div>

            <input
                ref={fileInput}
                type="file"
                accept="image/png,image/jpeg,image/webp,image/gif"
                className="hidden"
                tabIndex={-1}
                aria-hidden="true"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (file) {
                        void uploadImage(file);
                    }
                }}
            />

            {editor && (
                <LinkDialog
                    open={linkOpen}
                    current={currentLink}
                    onClose={() => setLinkOpen(false)}
                    onRemove={() => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .unsetLink()
                            .run();
                        setLinkOpen(false);
                    }}
                    onSave={(href) => {
                        editor
                            .chain()
                            .focus()
                            .extendMarkRange('link')
                            .setLink({ href })
                            .run();
                        setLinkOpen(false);
                    }}
                />
            )}

            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                {uploading ? (
                    <p
                        className="flex items-center gap-2 text-sm text-muted-foreground"
                        role="status"
                    >
                        <Spinner /> Uploading the picture
                    </p>
                ) : (
                    <p className="text-sm text-destructive" role="alert">
                        {uploadError}
                    </p>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={switchMode}
                >
                    {htmlMode ? 'Use the visual editor' : 'Edit as HTML'}
                </Button>
            </div>
        </div>
    );
}
