import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Compass, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useState } from 'react';

export type TourStep = {
    /** Matches a `data-tour="<target>"` attribute somewhere on the page. */
    target: string;
    title: string;
    text: string;
    /** Runs before the step is measured — e.g. switching to the tab the target lives on. */
    onShow?: () => void;
};

type Rect = { top: number; left: number; width: number; height: number };

function seenKey(tourKey: string) {
    return `seo-board-tour-seen:${tourKey}`;
}

function hasSeenTour(tourKey: string): boolean {
    try {
        return window.localStorage.getItem(seenKey(tourKey)) === '1';
    } catch {
        return false;
    }
}

function markTourSeen(tourKey: string) {
    try {
        window.localStorage.setItem(seenKey(tourKey), '1');
    } catch {
        // Private browsing / blocked storage — the tour just reopens next visit.
    }
}

function measureTarget(target: string): Rect | null {
    const el = document.querySelector(`[data-tour="${target}"]`);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    return { top: r.top, left: r.left, width: r.width, height: r.height };
}

/** Below the target when there's room, otherwise above; clamped to stay on-screen. */
function tooltipStyle(rect: Rect | null): React.CSSProperties {
    const width = 320;
    if (!rect) {
        return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' };
    }
    const margin = 14;
    const estHeight = 180;
    let top = rect.top + rect.height + margin;
    if (top + estHeight > window.innerHeight) {
        top = Math.max(rect.top - margin - estHeight, 12);
    }
    const left = Math.min(Math.max(rect.left + rect.width / 2 - width / 2, 12), window.innerWidth - width - 12);
    return { top, left, width };
}

/**
 * A spotlight walkthrough for one SEO Board surface (employee board, HOD
 * panel, or settings). Auto-opens once per browser per `tourKey`, and can
 * always be replayed via the trigger button. Steps that reference a
 * conditionally-rendered target (e.g. a tab's content) should set `onShow`
 * to switch to it first.
 */
export function SeoBoardTour({ tourKey, steps }: { tourKey: string; steps: TourStep[] }) {
    const [index, setIndex] = useState<number | null>(null);
    const [rect, setRect] = useState<Rect | null>(null);

    useEffect(() => {
        if (steps.length === 0 || hasSeenTour(tourKey)) return;
        const t = setTimeout(() => setIndex(0), 700);
        return () => clearTimeout(t);
    }, [tourKey, steps.length]);

    useLayoutEffect(() => {
        if (index === null) return;
        steps[index]?.onShow?.();
        const t1 = requestAnimationFrame(() => setRect(measureTarget(steps[index].target)));
        const t2 = setTimeout(() => setRect(measureTarget(steps[index].target)), 260);
        return () => {
            cancelAnimationFrame(t1);
            clearTimeout(t2);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index]);

    useEffect(() => {
        if (index === null) return;
        const onUpdate = () => setRect(measureTarget(steps[index].target));
        window.addEventListener('resize', onUpdate);
        window.addEventListener('scroll', onUpdate, true);
        return () => {
            window.removeEventListener('resize', onUpdate);
            window.removeEventListener('scroll', onUpdate, true);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index]);

    const stop = () => {
        setIndex(null);
        markTourSeen(tourKey);
    };

    const next = () => {
        if (index === null) return;
        if (index >= steps.length - 1) {
            stop();
            return;
        }
        setIndex(index + 1);
    };
    const back = () => setIndex((i) => (i !== null && i > 0 ? i - 1 : i));

    useEffect(() => {
        if (index === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') stop();
            else if (e.key === 'ArrowRight') next();
            else if (e.key === 'ArrowLeft') back();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index]);

    if (steps.length === 0) return null;

    const step = index !== null ? steps[index] : null;

    return (
        <>
            <Button type="button" size="sm" variant="outline" className="gap-1.5" onClick={() => setIndex(0)}>
                <Compass className="h-3.5 w-3.5" />
                Take the tour
            </Button>

            {step && (
                <div className="fixed inset-0 z-[100]">
                    {rect ? (
                        <>
                            <div className="fixed inset-x-0 top-0 bg-black/55" style={{ height: Math.max(rect.top - 6, 0) }} />
                            <div className="fixed inset-x-0 bottom-0 bg-black/55" style={{ top: rect.top + rect.height + 6 }} />
                            <div
                                className="fixed top-0 left-0 bg-black/55"
                                style={{ top: rect.top - 6, height: rect.height + 12, width: Math.max(rect.left - 6, 0) }}
                            />
                            <div
                                className="fixed top-0 right-0 bg-black/55"
                                style={{ top: rect.top - 6, height: rect.height + 12, left: rect.left + rect.width + 6 }}
                            />
                            <div
                                className="border-primary pointer-events-none fixed rounded-md border-2"
                                style={{ top: rect.top - 6, left: rect.left - 6, width: rect.width + 12, height: rect.height + 12 }}
                            />
                        </>
                    ) : (
                        <div className="fixed inset-0 bg-black/55" />
                    )}

                    <div
                        className={cn('bg-popover text-popover-foreground fixed z-[101] rounded-lg border p-4 shadow-lg')}
                        style={tooltipStyle(rect)}
                    >
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <span className="text-muted-foreground text-xs font-medium">
                                Step {(index ?? 0) + 1} of {steps.length}
                            </span>
                            <button type="button" onClick={stop} aria-label="Close tour" className="text-muted-foreground hover:text-foreground">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <h4 className="mb-1 text-sm font-semibold">{step.title}</h4>
                        <p className="text-muted-foreground mb-3 text-sm">{step.text}</p>
                        <div className="flex items-center justify-between gap-2">
                            <Button type="button" size="sm" variant="ghost" onClick={stop}>
                                Skip
                            </Button>
                            <div className="flex gap-1.5">
                                <Button type="button" size="sm" variant="outline" disabled={index === 0} onClick={back}>
                                    Back
                                </Button>
                                <Button type="button" size="sm" onClick={next}>
                                    {index === steps.length - 1 ? 'Done' : 'Next'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
