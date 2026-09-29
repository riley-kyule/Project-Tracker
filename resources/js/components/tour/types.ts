import { type Auth } from '@/types';

/**
 * Who is looking at the page — everything a tour needs to decide which of
 * its steps apply. Built once from the shared Inertia `auth` prop.
 */
export type TourAudience = {
    has: (permission: string) => boolean;
    hasRole: (...roles: string[]) => boolean;
    /** CEO or Administrator. */
    isExec: boolean;
    managesDepartment: boolean;
    hasEmployeeRecord: boolean;
    hasWebsiteAssignments: boolean;
    isSeoEmployee: boolean;
    isCsEmployee: boolean;
    canViewMarketingStatistics: boolean;
};

export type TourStep = {
    /** Matches a `data-tour="<target>"` attribute. Omit for a centred, untargeted step. */
    target?: string;
    title: string;
    body: string;
    /** Role/permission scope. A step whose check fails is never shown (or listed in the Help Center). */
    when?: (audience: TourAudience) => boolean;
    /**
     * A `data-tour` element to click when the target isn't on screen yet —
     * e.g. the tab or collapsible section it lives in.
     */
    reveal?: string;
};

export type TourSection =
    | 'Getting started'
    | 'Work'
    | 'Service Desk'
    | 'Overview & reports'
    | 'HR'
    | 'Personal'
    | 'Scoring boards'
    | 'Admin'
    | 'Settings';

export type TourDefinition = {
    id: string;
    section: TourSection;
    title: string;
    /** One line for the Help Center. */
    summary: string;
    /** Page the tour runs on. Omit when it needs a specific record (see `where`). */
    href?: string;
    /** How to get there when there's no single `href`, e.g. "Open any board, then click a task". */
    where?: string;
    /** Who the tour is for at all. Defaults to everyone. */
    audience?: (audience: TourAudience) => boolean;
    steps: TourStep[];
};

export function buildAudience(auth: Auth): TourAudience {
    const permissions = auth.permissions ?? [];
    const roles = auth.roles ?? [];

    return {
        has: (permission) => permissions.includes(permission),
        hasRole: (...wanted) => wanted.some((role) => roles.includes(role)),
        isExec: roles.includes('CEO') || roles.includes('Administrator'),
        managesDepartment: auth.managesDepartment,
        hasEmployeeRecord: auth.hasEmployeeRecord,
        hasWebsiteAssignments: auth.hasWebsiteAssignments,
        isSeoEmployee: auth.isSeoEmployee,
        isCsEmployee: auth.isCsEmployee,
        canViewMarketingStatistics: auth.canViewMarketingStatistics,
    };
}
