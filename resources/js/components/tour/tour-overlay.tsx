import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { findTarget } from './steps';
import { type TourStep } from './types';

type Rect = { top: number; left: number; width: number; height: number };

const PAD = 6; // spotlight padding around the target
const GAP = 12; // space between spotlight and card
const EDGE = 12; // minimum distance from the viewport edge

function measure(el: Element): Rect {
    const r = el.getBoundingClientRect();
    return { top: r.top, left: r.left, width: r.width, height: r.height };
}

const clamp = (value: number, min: number, max: number) => Math.max(min, Math.min(value, max));

/** Below the target, else above, else beside it, else pinned to the bottom of the screen. */
function placeCard(rect: Rect | null, size: { width: number; height: number }): React.CSSProperties {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const { width, height } = size;

    if (!rect) return { top: Math.max((vh - height) / 2, EDGE), left: Math.max((vw - width) / 2, EDGE) };

    const top = rect.top - PAD;
    const bottom = rect.top + rect.height + PAD;
    const left = rect.left - PAD;
    const right = rect.left + rect.width + PAD;
    const centredLeft = clamp(rect.left + rect.width / 2 - width / 2, EDGE, vw - width - EDGE);
    const centredTop = clamp(rect.top + rect.height / 2 - height / 2, EDGE, vh - height - EDGE);

    if (bottom + GAP + height <= vh - EDGE) return { top: bottom + GAP, left: centredLeft };
    if (top - GAP - height >= EDGE) return { top: top - GAP - height, left: centredLeft };
    if (right + GAP + width <= vw - EDGE) return { top: centredTop, left: right + GAP };
    if (left - GAP - width >= EDGE) return { top: centredTop, left: left - GAP - width };

    return { top: vh - height - EDGE, left: centredLeft };
}

/**
 * The spotlight walkthrough itself: dims the page, outlines the current
 * step's target and shows a small card beside it. Steps whose target can't
 * be found (even after clicking their `reveal` element) are skipped.
 */
export function TourOverlay({ title, steps, onClose }: { title: string; steps: TourStep[]; onClose: () => void }) {
    const [index, setIndex] = useState(0);
    const [rect, setRect] = useState<Rect | null>(null);
    const [ready, setReady] = useState(false);
    const [cardSize, setCardSize] = useState({ width: 340, height: 190 });
    const direction = useRef<1 | -1>(1);
    const cardRef = useRef<HTMLDivElement>(null);
    const nextRef = useRef<HTMLButtonElement>(null);

    const step = steps[index];
    const isLast = index === steps.length - 1;

    const go = (delta: 1 | -1) => {
        direction.current = delta;
        const next = index + delta;
        if (next >= steps.length) onClose();
        else if (next >= 0) setIndex(next);
    };

    // Find (or reveal) this step's target, scroll it into view, then measure.
    useEffect(() => {
        let cancelled = false;
        const timers: number[] = [];
        setReady(false);

        if (!step.target) {
            setRect(null);
            setReady(true);
            return;
        }

        const target = step.target;
        if (!findTarget(target) && step.reveal) findTarget(step.reveal)?.click();

        const started = performance.now();
        const settle = () => {
            if (cancelled) return;
            const el = findTarget(target);
            if (!el) {
                if (performance.now() - started < 900) {
                    timers.push(window.setTimeout(settle, 60));
                    return;
                }
                // Not on this page for this person — move on in the same direction.
                if (direction.current === -1 && index === 0) direction.current = 1;
                const next = index + direction.current;
                if (next >= steps.length) onClose();
                else setIndex(Math.max(next, 0));
                return;
            }
            el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' });
            timers.push(
                window.setTimeout(() => {
                    if (cancelled) return;
                    setRect(measure(el));
                    setReady(true);
                }, 320),
            );
        };
        settle();

        return () => {
            cancelled = true;
            timers.forEach((t) => window.clearTimeout(t));
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [index]);

    // Keep the spotlight glued to the target while the page scrolls or resizes.
    useEffect(() => {
        if (!step.target) return;
        let frame = 0;
        const update = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const el = findTarget(step.target!);
                if (el) setRect(measure(el));
            });
        };
        window.addEventListener('resize', update);
        window.addEventListener('scroll', update, true);
        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('resize', update);
            window.removeEventListener('scroll', update, true);
        };
    }, [step.target]);

    // Re-measured after every step change so placement uses the card's real height.
    useLayoutEffect(() => {
        if (!cardRef.current) return;
        const { width, height } = cardRef.current.getBoundingClientRect();
        if (Math.abs(width - cardSize.width) > 1 || Math.abs(height - cardSize.height) > 1) setCardSize({ width, height });
    }, [index, ready, rect, cardSize.width, cardSize.height]);

    useEffect(() => {
        if (ready) nextRef.current?.focus({ preventScroll: true });
    }, [ready, index]);

    // Captured on window so an open dialog underneath (which listens on
    // document) never sees Escape and closes itself mid-tour.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            else if (e.key === 'ArrowRight') go(1);
            else if (e.key === 'ArrowLeft') go(-1);
            else return;
            e.preventDefault();
            e.stopPropagation();
        };
        window.addEventListener('keydown', onKey, true);
        return () => window.removeEventListener('keydown', onKey, true);
    });

    const cardWidth = Math.min(340, window.innerWidth - EDGE * 2);

    return createPortal(
        <div
            className="fixed inset-0 z-[100]"
            // An open Radix dialog sets pointer-events:none on <body> and closes
            // itself on any pointerdown it thinks is outside — this layer must
            // stay clickable and keep its clicks to itself.
            style={{ pointerEvents: 'auto' }}
            onPointerDown={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-labelledby="tour-step-title"
            aria-describedby="tour-step-body"
            data-testid="tour-overlay"
        >
            {rect && ready ? (
                <>
                    <div className="fixed inset-x-0 top-0 bg-black/55" style={{ height: Math.max(rect.top - PAD, 0) }} />
                    <div className="fixed inset-x-0 bottom-0 bg-black/55" style={{ top: rect.top + rect.height + PAD }} />
                    <div
                        className="fixed left-0 bg-black/55"
                        style={{ top: rect.top - PAD, height: rect.height + PAD * 2, width: Math.max(rect.left - PAD, 0) }}
                    />
                    <div
                        className="fixed right-0 bg-black/55"
                        style={{ top: rect.top - PAD, height: rect.height + PAD * 2, left: rect.left + rect.width + PAD }}
                    />
                    <div
                        className="border-primary pointer-events-none fixed rounded-md border-2 transition-all duration-200"
                        style={{ top: rect.top - PAD, left: rect.left - PAD, width: rect.width + PAD * 2, height: rect.height + PAD * 2 }}
                    />
                </>
            ) : (
                <div className="fixed inset-0 bg-black/55" />
            )}

            <div
                ref={cardRef}
                className="bg-popover text-popover-foreground fixed rounded-lg border p-4 shadow-lg transition-opacity duration-150"
                style={{ ...placeCard(step.target ? rect : null, cardSize), width: cardWidth, opacity: ready ? 1 : 0 }}
            >
                <div className="mb-2 flex items-center justify-between gap-3">
                    <span className="text-muted-foreground truncate text-xs font-medium">
                        {title} · {index + 1} of {steps.length}
                    </span>
                    <button type="button" onClick={onClose} aria-label="Close tour" className="text-muted-foreground hover:text-foreground">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <h4 id="tour-step-title" className="mb-1 text-sm font-semibold">
                    {step.title}
                </h4>
                <p id="tour-step-body" className="text-muted-foreground mb-3 text-sm">
                    {step.body}
                </p>
                <div className="flex items-center justify-between gap-2">
                    {isLast ? (
                        <span />
                    ) : (
                        <Button type="button" size="sm" variant="ghost" onClick={onClose}>
                            Skip tour
                        </Button>
                    )}
                    <div className="flex gap-1.5">
                        {index > 0 && (
                            <Button type="button" size="sm" variant="outline" onClick={() => go(-1)}>
                                Back
                            </Button>
                        )}
                        <Button ref={nextRef} type="button" size="sm" onClick={() => go(1)}>
                            {isLast ? 'Done' : 'Next'}
                        </Button>
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
