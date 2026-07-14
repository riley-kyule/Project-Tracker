import { Skeleton } from '@/components/ui/skeleton';

export function BreakdownsSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-72 rounded-xl" />
            ))}
        </div>
    );
}
