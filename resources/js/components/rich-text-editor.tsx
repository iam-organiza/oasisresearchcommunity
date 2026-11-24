import Image from '@tiptap/extension-image';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
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
    onClick,
    children,
}: {
    active?: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'min-h-9 rounded-control px-2.5 text-sm font-medium hover:bg-accent',
                active && 'bg-primary text-primary-foreground hover:bg-primary',
            )}
        >
            {children}
        </button>
    );
}

function Toolbar({
    editor,
    onPickImage,
}: {
    editor: Editor;
    onPickImage: () => void;
}) {
    const setLink = () => {
        const current = editor.getAttributes('link').href as string | undefined;
        const url = window.prompt('Link address', current ?? 'https://');

        if (url === null) {
            return;
        }

        if (url.trim() === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url.trim() })
            .run();
    };

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
            <ToolButton active={editor.isActive('link')} onClick={setLink}>
                Link
            </ToolButton>
            <ToolButton onClick={onPickImage}>Image</ToolButton>
        </div>
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
    const fileInput = useRef<HTMLInputElement>(null);

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

    const uploadImage = async (file: File) => {
        setUploadError('');

        const body = new FormData();
        body.append('image', file);

        const response = await fetch(uploadUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });

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
        editor?.chain().focus().setImage({ src: url }).run();
    };

    const switchMode = () => {
        if (htmlMode) {
            if (
                NEEDS_HTML_MODE.test(value) &&
                !window.confirm(
                    'This article has tables or embedded media that the visual editor removes. Switch anyway?',
                )
            ) {
                return;
            }

            editor?.commands.setContent(value);
        }

        setHtmlMode(!htmlMode);
    };

    return (
        <div>
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
                                onPickImage={() => fileInput.current?.click()}
                            />
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

            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-destructive" role="alert">
                    {uploadError}
                </p>
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
