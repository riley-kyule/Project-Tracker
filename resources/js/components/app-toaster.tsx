import { useIsDark } from '@/hooks/use-is-dark';
import { Toaster } from 'sonner';

export function AppToaster() {
    const isDark = useIsDark();

    return <Toaster theme={isDark ? 'dark' : 'light'} richColors closeButton position="bottom-right" />;
}
