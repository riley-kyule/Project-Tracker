import { type TourAudience, type TourDefinition, type TourStep } from './types';

/** Is this tour meant for this person at all? */
export function tourAppliesTo(tour: TourDefinition, audience: TourAudience): boolean {
    return tour.audience ? tour.audience(audience) : true;
}

/** Steps this person's role is allowed to see — what the Help Center lists. */
export function stepsFor(tour: TourDefinition, audience: TourAudience): TourStep[] {
    return tour.steps.filter((step) => (step.when ? step.when(audience) : true));
}

/**
 * Steps that can actually be shown on the page right now: role-scoped, and
 * only those whose target is rendered (or can be revealed). A button the
 * person's permissions hide never renders, so its step drops out on its own.
 */
export function playableSteps(tour: TourDefinition, audience: TourAudience, isPresent: (target: string) => boolean): TourStep[] {
    return stepsFor(tour, audience).filter((step) => !step.target || isPresent(step.target) || (step.reveal !== undefined && isPresent(step.reveal)));
}

export function findTarget(target: string): HTMLElement | null {
    const nodes = document.querySelectorAll<HTMLElement>(`[data-tour="${target}"]`);
    // The first *visible* match — the same anchor can exist twice (e.g. a
    // desktop and a mobile variant), and a display:none copy has no box.
    for (const node of nodes) {
        const rect = node.getBoundingClientRect();
        if (rect.width > 0 || rect.height > 0) return node;
    }
    return null;
}

export const isTargetPresent = (target: string) => findTarget(target) !== null;
