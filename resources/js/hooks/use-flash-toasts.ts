import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Bridges Laravel's session flash messages (`back()->with('success', ...)` /
 * `->with('error', ...)`) to toast notifications, so success/error feedback
 * (task added, assignment saved, sync failed, etc.) is a transient toast
 * rather than a permanent on-page banner. Call once per page — AppLayout
 * does this so every authenticated page gets it for free.
 */
export function useFlashToasts() {
    const { flash } = usePage<SharedData>().props;
    const success = flash?.success;
    const error = flash?.error;

    useEffect(() => {
        if (success) {
            toast.success(success);
        }
    }, [success]);

    useEffect(() => {
        if (error) {
            toast.error(error);
        }
    }, [error]);
}
