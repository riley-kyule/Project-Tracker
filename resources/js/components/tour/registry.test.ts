import { describe, expect, it } from 'vitest';
import { SECTION_ORDER, TOURS, TOURS_BY_ID } from './registry';
import { playableSteps, stepsFor, tourAppliesTo } from './steps';
import { type TourAudience } from './types';

// Every component and page, as raw text, to check anchors against.
const files = import.meta.glob(['../../**/*.tsx', '!../../**/*.test.tsx'], { query: '?raw', import: 'default', eager: true }) as Record<
    string,
    string
>;
const source = Object.values(files).join('\n');

/** Every data-tour name the UI can render, including template-literal ones like `cs-emp-tab-${t}`. */
function anchorExists(target: string): boolean {
    // Plain attributes, or names passed through a prop (tourAnchor="…" / a quoted literal).
    if (source.includes(`data-tour="${target}"`) || source.includes(`="${target}"`) || source.includes(`'${target}'`)) return true;
    // Template literals such as data-tour={`cs-emp-tab-${t}`} or {id ? `ceo-${id}` : undefined}.
    const templated = [...source.matchAll(/`([a-z]+(?:-[a-z]+)*-)\$\{/g)].map((match) => match[1]);
    return templated.some((prefix) => target.startsWith(prefix));
}

const audience = (overrides: Partial<TourAudience> = {}, permissions: string[] = [], roles: string[] = []): TourAudience => ({
    has: (p) => permissions.includes(p),
    hasRole: (...wanted) => wanted.some((r) => roles.includes(r)),
    isExec: false,
    managesDepartment: false,
    hasEmployeeRecord: false,
    hasWebsiteAssignments: false,
    isSeoEmployee: false,
    isCsEmployee: false,
    canViewMarketingStatistics: false,
    ...overrides,
});

describe('tour registry', () => {
    it('has unique ids and known sections', () => {
        expect(new Set(TOURS.map((t) => t.id)).size).toBe(TOURS.length);
        TOURS.forEach((tour) => expect(SECTION_ORDER).toContain(tour.section));
    });

    it('keeps every tour short enough not to overwhelm, even for the CEO', () => {
        const everything = audience({ isExec: true, managesDepartment: true, hasEmployeeRecord: true, hasWebsiteAssignments: true }, [], ['CEO']);
        const all: TourAudience = { ...everything, has: () => true };
        TOURS.forEach((tour) => expect(stepsFor(tour, all).length, tour.id).toBeLessThanOrEqual(11));
    });

    it('points every step at an anchor that exists in the UI', () => {
        const missing = TOURS.flatMap((tour) =>
            tour.steps.flatMap((step) =>
                [step.target, step.reveal].filter((t): t is string => !!t && !anchorExists(t)).map((t) => `${tour.id} → ${t}`),
            ),
        );
        expect(missing).toEqual([]);
    });

    it('registers every tour somewhere, and only tours that exist', () => {
        const registered = [...source.matchAll(/<(?:PageTour|TourButton) id=\{?([^/>]+?)\}? \/>/g)].flatMap((m) =>
            [...m[1].matchAll(/['"]([a-z-]+)['"]/g)].map((x) => x[1]),
        );
        registered.forEach((id) => expect(TOURS_BY_ID, id).toHaveProperty(id));
        const unregistered = TOURS.map((t) => t.id).filter((id) => !registered.includes(id));
        expect(unregistered).toEqual([]);
    });

    it('never shows an employee admin or HR steps', () => {
        const employee = audience({ hasEmployeeRecord: true }, ['tasks.create', 'departments.view']);
        const visible = TOURS.filter((t) => tourAppliesTo(t, employee)).map((t) => t.id);
        expect(visible).toContain('welcome');
        expect(visible).toContain('my-leave');
        expect(visible).not.toContain('admin-users');
        expect(visible).not.toContain('payroll');
        expect(visible).not.toContain('cs-hod');
        const welcome = stepsFor(TOURS_BY_ID.welcome, employee).map((s) => s.title);
        expect(welcome).not.toContain('Admin tools');
        expect(welcome).not.toContain('HR tools');
    });

    it('scopes steps inside a shared tour by permission', () => {
        const tech = audience({}, ['tickets.manage'], ['IT Technician']);
        const plain = audience();
        const titles = (a: TourAudience) => stepsFor(TOURS_BY_ID.ticket, a).map((s) => s.title);
        expect(titles(tech)).toContain('Internal notes');
        expect(titles(plain)).not.toContain('Internal notes');
    });

    it('drops steps whose target is not on the page', () => {
        const steps = playableSteps(TOURS_BY_ID.board, audience(), (target) => target !== 'board-view-tabs');
        expect(steps.map((s) => s.target)).not.toContain('board-view-tabs');
        expect(steps.length).toBeGreaterThan(0);
    });
});
