import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type AppNotification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        type: string;
        message: string;
        board_id?: number;
        task_id?: number;
        ticket_id?: number;
    };
};

/** Polled every 20s rather than on a real-time transport (Reverb, Pusher) — see the
 * live-updates plan: no WebSocket infrastructure exists yet, and polling keeps this
 * simple against the current Docker stack. Toasts fire only for notifications that
 * showed up after the very first load, so opening the app never dumps a backlog of toasts. */
export function NotificationBell() {
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [unread, setUnread] = useState(0);
    const [hasLoaded, setHasLoaded] = useState(false);
    const seenIds = useRef<Set<string> | null>(null);

    const openNotification = useCallback((notification: AppNotification) => {
        fetch(`/notifications/${notification.id}/read`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf()) },
        }).finally(() => {
            if (notification.data.board_id) {
                router.visit(`/boards/${notification.data.board_id}`);
            } else if (notification.data.ticket_id) {
                router.visit(`/tickets/${notification.data.ticket_id}`);
            } else {
                load();
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const load = useCallback(() => {
        fetch('/notifications', { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : { notifications: [], unread_count: 0 }))
            .then((payload: { notifications: AppNotification[]; unread_count: number }) => {
                if (seenIds.current) {
                    payload.notifications
                        .filter((notification) => notification.read_at === null && !seenIds.current!.has(notification.id))
                        .forEach((notification) => {
                            toast(notification.data.message, { action: { label: 'View', onClick: () => openNotification(notification) } });
                        });
                }
                seenIds.current = new Set(payload.notifications.map((notification) => notification.id));
                setNotifications(payload.notifications);
                setUnread(payload.unread_count);
                setHasLoaded(true);
            })
            .catch(() => undefined);
    }, [openNotification]);

    useEffect(() => {
        load();
        const interval = setInterval(load, 20_000);
        return () => clearInterval(interval);
    }, [load]);

    const csrf = () => document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    const markAllRead = () => {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf()) },
        }).then(load);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label="Notifications">
                    <Bell className="size-5" />
                    {unread > 0 && (
                        <span className="bg-destructive absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full text-[10px] font-semibold text-white">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifications
                    {unread > 0 && (
                        <button type="button" onClick={markAllRead} className="text-brand-600 dark:text-brand-400 text-xs font-normal">
                            Mark all read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup className="max-h-96 overflow-y-auto">
                    {!hasLoaded && (
                        <div className="flex flex-col gap-2 p-2">
                            {Array.from({ length: 3 }).map((_, i) => (
                                <Skeleton key={i} className="h-10 rounded-md" />
                            ))}
                        </div>
                    )}
                    {hasLoaded && notifications.length === 0 && <DropdownMenuItem disabled>No notifications yet.</DropdownMenuItem>}
                    {notifications.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            onClick={() => openNotification(notification)}
                            className={notification.read_at === null ? 'font-medium' : 'opacity-70'}
                        >
                            <div className="flex flex-col gap-0.5">
                                <span className="text-sm leading-snug">{notification.data.message}</span>
                                <span className="text-muted-foreground text-xs">{new Date(notification.created_at).toLocaleString()}</span>
                            </div>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
