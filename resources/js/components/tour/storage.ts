/**
 * Which tours a person has finished or skipped. Kept per user in this
 * browser — a tour is a convenience, and replaying it on a new device is
 * harmless. Every read and write tolerates blocked storage.
 */

// The SEO Board shipped its own tour before the shared one existed; honour
// those flags so nobody sees a tour they already dismissed.
const LEGACY_KEYS: Record<string, string> = {
    'seo-employee': 'seo-board-tour-seen:employee',
    'seo-hod': 'seo-board-tour-seen:hod',
    'seo-settings': 'seo-board-tour-seen:settings',
};

const storageKey = (userId: number) => `ewms-tours:${userId}`;

export function readSeen(userId: number): Set<string> {
    const seen = new Set<string>();
    try {
        const raw = window.localStorage.getItem(storageKey(userId));
        const parsed: unknown = raw ? JSON.parse(raw) : [];
        if (Array.isArray(parsed)) parsed.forEach((id) => typeof id === 'string' && seen.add(id));
        for (const [id, legacy] of Object.entries(LEGACY_KEYS)) {
            if (window.localStorage.getItem(legacy) === '1') seen.add(id);
        }
    } catch {
        // Blocked or corrupt storage — behave as a first visit.
    }
    return seen;
}

export function writeSeen(userId: number, seen: Set<string>) {
    try {
        window.localStorage.setItem(storageKey(userId), JSON.stringify([...seen]));
        // Keep the legacy flags in step, so "replay next time" really replays.
        for (const [id, legacy] of Object.entries(LEGACY_KEYS)) {
            if (seen.has(id)) window.localStorage.setItem(legacy, '1');
            else window.localStorage.removeItem(legacy);
        }
    } catch {
        // Private browsing — the tour just offers itself again next visit.
    }
}
