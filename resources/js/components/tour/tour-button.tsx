import { Button } from '@/components/ui/button';
import { Compass } from 'lucide-react';
import { PageTour } from './page-tour';
import { useTours } from './tour-provider';

/**
 * A visible "Take the tour" button for dense screens (the score boards) where
 * the header's help menu is easy to miss. Also registers the tour, so it
 * still auto-plays the first time.
 */
export function TourButton({ id }: { id: string }) {
    const tours = useTours();

    return (
        <>
            <PageTour id={id} />
            {tours && (
                <Button type="button" size="sm" variant="outline" className="gap-1.5" onClick={() => tours.start(id)}>
                    <Compass className="h-3.5 w-3.5" />
                    Take the tour
                </Button>
            )}
        </>
    );
}
