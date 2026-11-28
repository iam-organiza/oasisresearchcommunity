import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { showToast } from '@/components/ui/sonner';
import type { FlashToast } from '@/types/ui';

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const undo = data.undo;

            showToast(
                data.type,
                data.message,
                undo
                    ? {
                          label: 'Undo',
                          onClick: () =>
                              router.patch(
                                  undo.url,
                                  undo.data as Record<string, string>,
                                  { preserveScroll: true },
                              ),
                      }
                    : undefined,
            );
        });
    }, []);
}
