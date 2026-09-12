import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Lock, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Permissions', href: '/admin/permissions' }];

type PermissionInfo = { name: string; label: string; description: string; group: string };
type Member = { id: number; name: string; email: string };
type RoleRow = { id: number; name: string; description: string; locked: boolean; permissions: string[]; users: Member[] };

export default function PermissionsIndex({ permissions, roles }: { permissions: PermissionInfo[]; roles: RoleRow[] }) {
    const [selectedId, setSelectedId] = useState(roles[0]?.id);
    // Optimistic per-role permission lists — the toggle flips immediately;
    // a failed request rolls its role back to what the server last confirmed.
    const [rolePermissions, setRolePermissions] = useState<Record<number, string[]>>(() =>
        Object.fromEntries(roles.map((role) => [role.id, role.permissions])),
    );
    const [savingRoleId, setSavingRoleId] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    const role = roles.find((r) => r.id === selectedId) ?? roles[0];

    const groups = useMemo(() => {
        const term = search.trim().toLowerCase();
        const filtered =
            term === ''
                ? permissions
                : permissions.filter(
                      (p) =>
                          p.label.toLowerCase().includes(term) || p.description.toLowerCase().includes(term) || p.group.toLowerCase().includes(term),
                  );
        const byGroup = new Map<string, PermissionInfo[]>();
        for (const permission of filtered) {
            byGroup.set(permission.group, [...(byGroup.get(permission.group) ?? []), permission]);
        }
        return Array.from(byGroup.entries());
    }, [search, permissions]);

    const toggle = (permission: string) => {
        if (!role || role.locked || savingRoleId !== null) return;

        const current = rolePermissions[role.id] ?? [];
        const next = current.includes(permission) ? current.filter((p) => p !== permission) : [...current, permission];

        setRolePermissions((state) => ({ ...state, [role.id]: next }));
        setSavingRoleId(role.id);

        router.patch(
            `/admin/permissions/${role.id}`,
            { permissions: next },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    setRolePermissions((state) => ({ ...state, [role.id]: current }));
                    toast.error(Object.values(errors)[0] ?? `Could not update ${role.name}'s permissions.`);
                },
                onFinish: () => setSavingRoleId(null),
            },
        );
    };

    if (!role) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Permissions" />
                <p className="text-muted-foreground p-4 text-sm">No roles exist yet.</p>
            </AppLayout>
        );
    }

    const checkedPermissions = rolePermissions[role.id] ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permissions" />
            <TooltipProvider>
                <div className="flex flex-col gap-4 p-4">
                    <div>
                        <h1 className="text-xl font-semibold">Permissions</h1>
                        <p className="text-muted-foreground text-sm">
                            Pick a role below to see who has it and what it can do. Flip a switch to change it — it takes effect immediately, there's
                            nothing to save.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {roles.map((r) => (
                            <button
                                key={r.id}
                                type="button"
                                onClick={() => setSelectedId(r.id)}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                                    r.id === role.id
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-sidebar-border/70 dark:border-sidebar-border hover:border-primary/50',
                                )}
                            >
                                {r.name}
                                {r.locked && <Lock className="size-3 opacity-70" />}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 text-xs',
                                        r.id === role.id ? 'bg-primary-foreground/20' : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {r.users.length}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    {role.name}
                                    {role.locked && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Lock className="text-muted-foreground size-4" aria-label="This role's permissions are fixed" />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Fixed to full access — CEO and Administrator are kept permission-identical on purpose.
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </h2>
                                <p className="text-muted-foreground text-sm">{role.description}</p>
                            </div>
                            <Link href="/admin/users" className="text-brand-600 dark:text-brand-400 text-sm hover:underline">
                                Change who has this role →
                            </Link>
                        </div>

                        <div className="mt-3 flex items-start gap-2 text-sm">
                            <Users className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            {role.users.length === 0 ? (
                                <span className="text-muted-foreground">Nobody currently has this role.</span>
                            ) : (
                                <div className="flex flex-wrap gap-1.5">
                                    {role.users.map((user) => (
                                        <Tooltip key={user.id}>
                                            <TooltipTrigger asChild>
                                                <Badge variant="secondary" className="font-normal">
                                                    {user.name}
                                                </Badge>
                                            </TooltipTrigger>
                                            <TooltipContent>{user.email}</TooltipContent>
                                        </Tooltip>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    <Input
                        placeholder="Search what this role can do…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-72"
                        aria-label="Search permissions"
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        {groups.map(([group, groupPermissions]) => (
                            <div key={group} className="border-sidebar-border/70 dark:border-sidebar-border h-fit rounded-xl border p-4">
                                <h3 className="mb-3 text-sm font-semibold">{group}</h3>
                                <div className="space-y-3">
                                    {groupPermissions.map((permission) => {
                                        const checked = role.locked ? true : checkedPermissions.includes(permission.name);
                                        return (
                                            <label
                                                key={permission.name}
                                                className={cn(
                                                    'flex items-start gap-3 text-sm',
                                                    role.locked || savingRoleId !== null ? 'cursor-default' : 'cursor-pointer',
                                                )}
                                            >
                                                <Checkbox
                                                    checked={checked}
                                                    disabled={role.locked || savingRoleId !== null}
                                                    onCheckedChange={() => toggle(permission.name)}
                                                    className={cn('mt-0.5', role.locked && 'opacity-50')}
                                                />
                                                <span>
                                                    <span className="font-medium">{permission.label}</span>
                                                    {permission.description && (
                                                        <span className="text-muted-foreground block text-xs">{permission.description}</span>
                                                    )}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                        {groups.length === 0 && (
                            <p className="text-muted-foreground col-span-full py-6 text-center text-sm">No permissions match "{search}".</p>
                        )}
                    </div>
                </div>
            </TooltipProvider>
        </AppLayout>
    );
}
