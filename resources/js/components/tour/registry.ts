import { adminTours, settingsTours } from './tours/admin';
import { gettingStartedTours } from './tours/getting-started';
import { hrTours, personalTours } from './tours/hr';
import { overviewTours } from './tours/overview';
import { scoringTours } from './tours/scoring';
import { serviceDeskTours } from './tours/service-desk';
import { workTours } from './tours/work';
import { type TourDefinition, type TourSection } from './types';

export const WELCOME_TOUR_ID = 'welcome';

/** Every guided tour in the app, in Help Center order. */
export const TOURS: TourDefinition[] = [
    ...gettingStartedTours,
    ...workTours,
    ...serviceDeskTours,
    ...overviewTours,
    ...hrTours,
    ...personalTours,
    ...scoringTours,
    ...adminTours,
    ...settingsTours,
];

export const TOURS_BY_ID: Record<string, TourDefinition> = Object.fromEntries(TOURS.map((tour) => [tour.id, tour]));

export const SECTION_ORDER: TourSection[] = [
    'Getting started',
    'Work',
    'Service Desk',
    'Overview & reports',
    'HR',
    'Personal',
    'Scoring boards',
    'Admin',
    'Settings',
];
