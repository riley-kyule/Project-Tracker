import { SECTION_ORDER, TOURS } from '@/components/tour/registry';
import { stepsFor, tourAppliesTo } from '@/components/tour/steps';
import { useTours } from '@/components/tour/tour-provider';
import { type TourDefinition } from '@/components/tour/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Compass, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Help Center', href: '/help' }];

/** Plain-language summary of each role, shown for the roles the viewer actually holds. */
const ROLE_GUIDES: Record<string, string> = {
    CEO: 'Full access to everything: every dashboard, report, HR record and admin tool, plus final payroll approval.',
    Administrator: 'Same access as the CEO. Also runs system upkeep: users, permissions, integrations and queue health.',
    'Department Manager':
        "Runs your department: manage its boards and columns, assign and approve work, approve your team's leave, see department reports, and run the SEO or Customer Service score boards if you lead those teams.",
    'HR Manager': 'Everything in HR (people, assets, leave, payroll processing, performance) plus Department Manager tools for your own team.',
    'HR Staff': 'People records, leave and assets. Salaries and payroll stay hidden from this role.',
    'IT Technician': 'Work the IT ticket queue: assign, update, resolve, add internal notes and convert tickets into tasks.',
    'Research & Development': 'Work the R&D ticket queue the same way IT works theirs, and create tasks.',
    'R&D Manager': 'Department Manager tools for R&D, plus the R&D ticket queue.',
    Marketing: 'Create and work on tasks, view Marketing Statistics and fill in your SEO score cards.',
    'Customer Service': 'Create and work on tasks and fill in your Customer Service score cards (service, sales and continuity).',
    Employee: 'Create tasks, work on the tasks assigned to you, raise support tickets and use your personal HR pages.',
    Viewer: 'Read-only access to the work you are allowed to see. You can still raise support tickets.',
};

function TourCard({ tour }: { tour: TourDefinition }) {
    const tours = useTours();
    const [open, setOpen] = useState(false);
    if (!tours) return null;

    const steps = stepsFor(tour, tours.audience);
    const seen = tours.isSeen(tour.id);

    const startTour = () => {
        if (!tour.href) return;
        router.visit(`${tour.href}${tour.href.includes('?') ? '&' : '?'}tour=${tour.id}`);
    };

    const replayLater = () => {
        tours.replayNextTime(tour.id);
        toast.success(`"${tour.title}" will play the next time you open it.`);
    };

    return (
        <li className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border">
            <div className="flex flex-wrap items-start gap-3 p-4">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold">{tour.title}</h3>
                        {seen && (
                            <Badge variant="secondary" className="gap-1">
                                <CheckCircle2 className="size-3" /> Seen
                            </Badge>
                        )}
                    </div>
                    <p className="text-muted-foreground mt-0.5 text-sm">{tour.summary}</p>
                    {tour.where && <p className="text-muted-foreground mt-1 text-xs">Where: {tour.where}</p>}
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                    {tour.href ? (
                        <Button size="sm" onClick={startTour}>
                            <Compass className="mr-1 size-4" /> Start tour
                        </Button>
                    ) : (
                        <Button size="sm" variant="outline" onClick={replayLater} disabled={!seen}>
                            <RotateCcw className="mr-1 size-4" /> Replay next time
                        </Button>
                    )}
                </div>
            </div>
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                className="text-muted-foreground hover:text-foreground flex w-full items-center gap-1 border-t px-4 py-2 text-left text-xs font-medium"
            >
                <ChevronRight className={`size-3.5 transition-transform ${open ? 'rotate-90' : ''}`} />
                {open ? 'Hide' : 'Read'} the {steps.length} steps
            </button>
            {open && (
                <ol className="space-y-2 px-4 pb-4 text-sm">
                    {steps.map((step, i) => (
                        <li key={`${step.title}-${i}`} className="flex gap-2">
                            <span className="text-muted-foreground w-5 shrink-0 text-right text-xs leading-5">{i + 1}.</span>
                            <div>
                                <span className="font-medium">{step.title}.</span> <span className="text-muted-foreground">{step.body}</span>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </li>
    );
}

// Tour state lives in the layout's provider, so the page body has to sit inside AppLayout to reach it.
function HelpCenterContent() {
    const { auth } = usePage<SharedData>().props;
    const tours = useTours();
    const [query, setQuery] = useState('');

    if (!tours) return null;

    const term = query.trim().toLowerCase();
    const mine = TOURS.filter((tour) => tourAppliesTo(tour, tours.audience)).filter((tour) => {
        if (term === '') return true;
        const haystack = [tour.title, tour.summary, ...stepsFor(tour, tours.audience).flatMap((step) => [step.title, step.body])]
            .join(' ')
            .toLowerCase();
        return haystack.includes(term);
    });
    const seenCount = TOURS.filter((tour) => tourAppliesTo(tour, tours.audience) && tours.isSeen(tour.id)).length;
    const total = TOURS.filter((tour) => tourAppliesTo(tour, tours.audience)).length;
    const roleGuides = auth.roles.filter((role) => ROLE_GUIDES[role]);

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="flex-1">
                    <h1 className="text-xl font-semibold">Help Center</h1>
                    <p className="text-muted-foreground text-sm">
                        Short guided tours for every part of EWMS you can use. You've seen {seenCount} of {total}.
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                        if (!confirm('Show every tour again as you visit each page?')) return;
                        tours.resetAll();
                        toast.success('All tours will play again as you visit each page.');
                    }}
                >
                    <RotateCcw className="mr-1 size-4" /> Reset all tours
                </Button>
            </div>

            {roleGuides.length > 0 && (
                <div className="border-sidebar-border/70 dark:border-sidebar-border bg-muted/40 rounded-xl border p-4">
                    <h2 className="mb-2 text-sm font-semibold">What your role can do</h2>
                    <ul className="space-y-1.5 text-sm">
                        {roleGuides.map((role) => (
                            <li key={role}>
                                <span className="font-medium">{role}:</span> <span className="text-muted-foreground">{ROLE_GUIDES[role]}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search the guides, e.g. leave, approval, checklist…"
                aria-label="Search the guides"
                className="max-w-md"
            />

            {SECTION_ORDER.map((section) => {
                const inSection = mine.filter((tour) => tour.section === section);
                if (inSection.length === 0) return null;
                return (
                    <section key={section}>
                        <h2 className="mb-2 text-sm font-semibold">{section}</h2>
                        <ul className="flex flex-col gap-2">
                            {inSection.map((tour) => (
                                <TourCard key={tour.id} tour={tour} />
                            ))}
                        </ul>
                    </section>
                );
            })}

            {mine.length === 0 && <p className="text-muted-foreground text-sm">No guides match “{query}”.</p>}
        </div>
    );
}

export default function HelpCenter() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Help Center" />
            <HelpCenterContent />
        </AppLayout>
    );
}
