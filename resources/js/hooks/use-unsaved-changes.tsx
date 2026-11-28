import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { useConfirm } from '@/components/confirm-dialog';

/**
 * Asks before unsaved work is thrown away: on any link inside the app (with
 * the site's own dialog) and on closing or reloading the tab (with the
 * browser's, the only one allowed there). Saving is a POST, so it goes
 * ahead. Render the returned dialog once in the page.
 */
/** `what` names what is being edited, as in "Your changes to the home page…". */
export function useUnsavedChanges(dirty: boolean, what = 'this post') {
    const { confirm, dialog } = useConfirm();
    const dirtyRef = useRef(dirty);
    // Set once leaving is agreed.
    const allowRef = useRef(false);

    useEffect(() => {
        dirtyRef.current = dirty;
    }, [dirty]);

    useEffect(() => {
        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            if (dirtyRef.current && !allowRef.current) {
                event.preventDefault();
            }
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        const stop = router.on('before', (event) => {
            const visit = event.detail.visit;

            // Saving, partial reloads (adding a category) and agreed exits go ahead.
            if (
                !dirtyRef.current ||
                allowRef.current ||
                visit.method !== 'get' ||
                visit.only.length > 0
            ) {
                return;
            }

            void confirm({
                title: 'Leave without saving?',
                description: `Your changes to ${what} have not been saved. If you leave now, they are lost.`,
                confirmLabel: 'Leave and lose changes',
                cancelLabel: 'Keep editing',
                tone: 'danger',
            }).then((leave) => {
                if (leave) {
                    allowRef.current = true;
                    router.visit(visit.url, {
                        method: visit.method,
                        preserveScroll: visit.preserveScroll,
                    });
                }
            });

            return false;
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            stop();
        };
    }, [confirm, what]);

    return dialog;
}
