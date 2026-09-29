import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/react';
import { BookOpen, CircleHelp, Compass } from 'lucide-react';
import { useTours } from './tour-provider';

/** Header "?" menu: replay this page's tours, or open the Help Center. */
export function HelpButton() {
    const tours = useTours();
    if (!tours) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label="Help and guided tours" data-tour="help-button">
                    <CircleHelp className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                {tours.pageTours.length > 0 && (
                    <>
                        <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Guided tours on this page</DropdownMenuLabel>
                        {tours.pageTours.map((tour) => (
                            // Deferred a tick so the menu has closed (and returned focus) before the spotlight measures the page.
                            <DropdownMenuItem key={tour.id} onSelect={() => window.setTimeout(() => tours.start(tour.id), 150)}>
                                <Compass className="mr-2 size-4" />
                                {tour.title}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                    </>
                )}
                <DropdownMenuItem asChild>
                    <Link href="/help" className="w-full">
                        <BookOpen className="mr-2 size-4" />
                        Help Center: all guides
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
