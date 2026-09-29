import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Info } from 'lucide-react';

/**
 * A small "i" icon that reveals an explanation on hover/focus — for a badge,
 * label or button whose meaning isn't already spelled out in visible text
 * nearby. Each instance carries its own TooltipProvider (Radix requires one
 * and the app has no page-wide provider), so this is safe to drop in
 * anywhere without coordinating with a parent.
 */
export function InfoTooltip({ text, className = 'ml-1' }: { text: string; className?: string }) {
    return (
        <TooltipProvider>
            <Tooltip delayDuration={200}>
                <TooltipTrigger asChild>
                    <button type="button" className={`text-muted-foreground hover:text-foreground inline-flex align-middle ${className}`}>
                        <Info className="size-3.5" />
                        <span className="sr-only">More information</span>
                    </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-64 text-xs">{text}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
