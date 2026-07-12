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
import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

type AppNotification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        type: string;
        message: string;
        board_id?: number;
        task_id?: number;
    };
};

export function NotificationBell() {
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [unread, setUnread] = useState(0);
    const [hasLoaded, setHasLoaded] = useState(false);

    const load = useCallback(() => {
        fetch('/notifications', { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : { notifications: [], unread_count: 0 }))
            .then((payload) => {
                setNotifications(payload.notifications);
                setUnread(payload.unread_count);
                setHasLoaded(true);
            })
            .catch(() => undefined);
    }, []);

    useEffect(() => {
        load();
        const interval = setInterval(load, 60_000);
        return () => clearInterval(interval);
    }, [load]);

    const csrf = () => document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    const markAllRead = () => {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf()) },
        }).then(load);
    };

    const openNotification = (notification: AppNotification) => {
        fetch(`/notifications/${notification.id}/read`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(csrf()) },
        }).finally(() => {
            if (notification.data.board_id) {
                router.visit(`/boards/${notification.data.board_id}`);
            } else {
                load();
            }
        });
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
                    {!hasLoaded && <DropdownMenuItem disabled>Loading…</DropdownMenuItem>}
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
