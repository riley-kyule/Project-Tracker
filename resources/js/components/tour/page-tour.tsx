import { useEffect } from 'react';
import { useTours } from './tour-provider';

/**
 * Declares which guided tour(s) belong to whatever renders this — a page, a
 * tab or a dialog. The first unseen one plays once on its own; all of them
 * can be replayed from the header's help button. Renders nothing.
 */
export function PageTour({ id }: { id: string | string[] }) {
    const register = useTours()?.register;
    const key = Array.isArray(id) ? id.join('|') : id;

    useEffect(() => {
        if (!register) return;
        const cleanups = key.split('|').map((tourId) => register(tourId));
        return () => cleanups.forEach((cleanup) => cleanup());
    }, [register, key]);

    return null;
}
