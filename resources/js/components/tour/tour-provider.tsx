import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { TOURS_BY_ID, WELCOME_TOUR_ID } from './registry';
import { isTargetPresent, playableSteps, tourAppliesTo } from './steps';
import { autoplayDisabled, readSeen, writeSeen } from './storage';
import { TourOverlay } from './tour-overlay';
import { buildAudience, type TourAudience, type TourDefinition, type TourStep } from './types';

type TourApi = {
    audience: TourAudience;
    /** Tours registered by whatever is on screen right now, that apply to this person. */
    pageTours: TourDefinition[];
    register: (id: string) => () => void;
    start: (id: string) => void;
    isSeen: (id: string) => boolean;
    /** Forget that a tour was seen, so it plays again the next time its page opens. */
    replayNextTime: (id: string) => void;
    resetAll: () => void;
};

const TourContext = createContext<TourApi | null>(null);

export function useTours(): TourApi | null {
    return useContext(TourContext);
}

const AUTO_START_DELAY = 800;

/**
 * Owns tour state for the signed-in layout. Pages and dialogs register the
 * tours that belong to them (see PageTour); the first time a registered tour
 * hasn't been seen, it plays once on its own. Only one tour ever auto-plays
 * per registration burst and finishing one never chains into the next, so a
 * new person meets the app a page at a time instead of all at once.
 */
export function TourProvider({ children }: { children: React.ReactNode }) {
    const { auth } = usePage<SharedData>().props;
    const userId = auth.user.id;
    // Keyed on content, not identity: every partial reload hands back a new
    // `auth` object, and nothing downstream should churn because of that.
    const authKey = JSON.stringify([
        auth.permissions,
        auth.roles,
        auth.managesDepartment,
        auth.hasEmployeeRecord,
        auth.isSeoEmployee,
        auth.isCsEmployee,
    ]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    const audience = useMemo(() => buildAudience(auth), [authKey]);

    const [seen, setSeen] = useState<Set<string>>(() => readSeen(userId));
    const [registered, setRegistered] = useState<string[]>([]);
    const [active, setActive] = useState<{ tour: TourDefinition; steps: TourStep[] } | null>(null);

    const counts = useRef(new Map<string, number>());
    const offered = useRef(new Set<string>());
    const autoTimer = useRef<number | null>(null);
    const activeRef = useRef(active);
    activeRef.current = active;
    const seenRef = useRef(seen);
    seenRef.current = seen;

    const updateSeen = useCallback(
        (change: (next: Set<string>) => void) => {
            setSeen((current) => {
                const next = new Set(current);
                change(next);
                writeSeen(userId, next);
                return next;
            });
        },
        [userId],
    );

    const launch = useCallback(
        (id: string, { quiet }: { quiet: boolean }) => {
            const tour = TOURS_BY_ID[id];
            if (!tour || activeRef.current || !tourAppliesTo(tour, audience)) return false;
            const steps = playableSteps(tour, audience, isTargetPresent);
            if (steps.length === 0) {
                if (!quiet) toast.info('Nothing to point out here for your role yet.');
                return false;
            }
            offered.current.add(id);
            setActive({ tour, steps });
            return true;
        },
        [audience],
    );

    const autoStart = useCallback(() => {
        autoTimer.current = null;
        if (activeRef.current) return;

        const ids = [...counts.current.keys()];

        // ?tour=<id> — sent by the Help Center's "Start tour" buttons.
        const params = new URLSearchParams(window.location.search);
        const requested = params.get('tour');
        if (requested && ids.includes(requested)) {
            params.delete('tour');
            const query = params.toString();
            window.history.replaceState(window.history.state, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
            if (launch(requested, { quiet: false })) return;
        }

        if (autoplayDisabled()) return;

        // The welcome tour goes first; after that, whatever registered first.
        const ordered = ids.includes(WELCOME_TOUR_ID) ? [WELCOME_TOUR_ID, ...ids.filter((id) => id !== WELCOME_TOUR_ID)] : ids;
        for (const id of ordered) {
            if (seenRef.current.has(id) || offered.current.has(id)) continue;
            if (launch(id, { quiet: true })) return;
        }
    }, [launch]);

    const autoStartRef = useRef(autoStart);
    autoStartRef.current = autoStart;

    // Stable on purpose: a tour is only considered for auto-play when its
    // owner first mounts, never again because the page re-rendered.
    const register = useCallback((id: string) => {
        const isNew = !counts.current.has(id);
        counts.current.set(id, (counts.current.get(id) ?? 0) + 1);
        setRegistered([...counts.current.keys()]);
        if (isNew && autoTimer.current === null && !activeRef.current) {
            autoTimer.current = window.setTimeout(() => autoStartRef.current(), AUTO_START_DELAY);
        }

        return () => {
            const remaining = (counts.current.get(id) ?? 1) - 1;
            if (remaining <= 0) counts.current.delete(id);
            else counts.current.set(id, remaining);
            setRegistered([...counts.current.keys()]);
        };
    }, []);

    useEffect(
        () => () => {
            if (autoTimer.current !== null) window.clearTimeout(autoTimer.current);
        },
        [],
    );

    const finish = useCallback(() => {
        const current = activeRef.current;
        setActive(null);
        if (current) updateSeen((next) => next.add(current.tour.id));
    }, [updateSeen]);

    const api = useMemo<TourApi>(
        () => ({
            audience,
            pageTours: registered.map((id) => TOURS_BY_ID[id]).filter((tour): tour is TourDefinition => !!tour && tourAppliesTo(tour, audience)),
            register,
            start: (id) => launch(id, { quiet: false }),
            isSeen: (id) => seen.has(id),
            replayNextTime: (id) => updateSeen((next) => next.delete(id)),
            resetAll: () => updateSeen((next) => next.clear()),
        }),
        [audience, registered, register, launch, seen, updateSeen],
    );

    return (
        <TourContext.Provider value={api}>
            {children}
            {active && <TourOverlay key={active.tour.id} title={active.tour.title} steps={active.steps} onClose={finish} />}
        </TourContext.Provider>
    );
}
