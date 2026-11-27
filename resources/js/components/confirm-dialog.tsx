import { useCallback, useState } from 'react';
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

export type ConfirmOptions = {
    /** The question, such as "Delete this post?". */
    title: string;
    /** What will happen, in a sentence or two. */
    description: ReactNode;
    /** Names the action, such as "Delete post"; never just "OK". */
    confirmLabel: string;
    cancelLabel?: string;
    /** 'danger' for anything that deletes or cannot be undone: the button turns crimson. */
    tone?: 'default' | 'danger';
};

/**
 * The site's own "are you sure?" step, in place of the browser's pop-up:
 * a question, what will happen, a button that names the action, and a way out.
 */
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel,
    cancelLabel = 'Cancel',
    tone = 'default',
    processing = false,
    onConfirm,
    onCancel,
}: ConfirmOptions & {
    open: boolean;
    processing?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(next) => !next && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className="break-words">
                        {description}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={onCancel}>
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={tone === 'danger' ? 'destructive' : 'default'}
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Ask first, then act: `if (await confirm({...})) { ... }` reads like the
 * browser's confirm(), but shows ConfirmDialog. Render `dialog` once in the page.
 */
export function useConfirm() {
    const [asking, setAsking] = useState<
        (ConfirmOptions & { answer: (yes: boolean) => void }) | null
    >(null);

    const confirm = useCallback(
        (options: ConfirmOptions) =>
            new Promise<boolean>((resolve) =>
                setAsking({ ...options, answer: resolve }),
            ),
        [],
    );

    const reply = (yes: boolean) => {
        asking?.answer(yes);
        setAsking(null);
    };

    const dialog = asking ? (
        <ConfirmDialog
            open
            title={asking.title}
            description={asking.description}
            confirmLabel={asking.confirmLabel}
            cancelLabel={asking.cancelLabel}
            tone={asking.tone}
            onConfirm={() => reply(true)}
            onCancel={() => reply(false)}
        />
    ) : null;

    return { confirm, dialog };
}
