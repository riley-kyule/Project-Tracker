import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Permissions', href: '/admin/permissions' }];

type RoleRow = { id: number; name: string; locked: boolean; permissions: string[] };

/** "hr.employees.view" groups under "Hr"; a bare name like "view marketing statistics" groups under "Other". */
function groupOf(permission: string): string {
    const prefix = permission.includes('.') ? permission.split('.')[0] : 'other';
    return prefix.charAt(0).toUpperCase() + prefix.slice(1);
}

export default function PermissionsIndex({ permissions, roles }: { permissions: string[]; roles: RoleRow[] }) {
    // Optimistic per-role permission lists — the checkbox flips immediately;
    // a failed request rolls its role back to what the server last confirmed.
    const [rolePermissions, setRolePermissions] = useState<Record<number, string[]>>(() =>
        Object.fromEntries(roles.map((role) => [role.id, role.permissions])),
    );
    const [savingRoleId, setSavingRoleId] = useState<number | null>(null);
    const [search, setSearch] = useState('');

    const groups = useMemo(() => {
        const term = search.trim().toLowerCase();
        const filtered = term === '' ? permissions : permissions.filter((p) => p.toLowerCase().includes(term));
        const byGroup = new Map<string, string[]>();
        for (const permission of filtered) {
            const group = groupOf(permission);
            byGroup.set(group, [...(byGroup.get(group) ?? []), permission]);
        }
        return Array.from(byGroup.entries()).sort(([a], [b]) => a.localeCompare(b));
    }, [permissions, search]);

    const toggle = (role: RoleRow, permission: string) => {
        if (role.locked || savingRoleId !== null) return;

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permissions" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Permissions</h1>
                    <p className="text-muted-foreground text-sm">
                        Pick what each role can access. Changes apply immediately — there's nothing to save.
                    </p>
                </div>

                <Input
                    placeholder="Search permissions…"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-64"
                    aria-label="Search permissions"
                />

                <TooltipProvider>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground border-b text-left">
                                    <th className="bg-background sticky left-0 p-3 font-medium">Permission</th>
                                    {roles.map((role) => (
                                        <th key={role.id} className="p-3 text-center font-medium whitespace-nowrap">
                                            <span className="inline-flex items-center gap-1">
                                                {role.name}
                                                {role.locked && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Lock className="size-3 opacity-60" aria-label={`${role.name}'s permissions are fixed`} />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Fixed to full access — CEO and Administrator are kept permission-identical on purpose.
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {groups.map(([group, groupPermissions]) => (
                                    <Fragment key={group}>
                                        <tr className="bg-muted/40">
                                            <td colSpan={roles.length + 1} className="px-3 py-1.5">
                                                <Badge variant="secondary">{group}</Badge>
                                            </td>
                                        </tr>
                                        {groupPermissions.map((permission) => (
                                            <tr key={permission} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-t">
                                                <td className="bg-background sticky left-0 p-3 font-mono text-xs">{permission}</td>
                                                {roles.map((role) => {
                                                    const checked = (rolePermissions[role.id] ?? []).includes(permission);
                                                    return (
                                                        <td key={role.id} className="p-3 text-center">
                                                            <Checkbox
                                                                checked={role.locked ? true : checked}
                                                                disabled={role.locked || savingRoleId === role.id}
                                                                onCheckedChange={() => toggle(role, permission)}
                                                                aria-label={`${role.locked ? 'Locked — ' : ''}${role.name} · ${permission}`}
                                                                className={cn(role.locked && 'opacity-50')}
                                                            />
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </Fragment>
                                ))}
                                {groups.length === 0 && (
                                    <tr>
                                        <td colSpan={roles.length + 1} className="text-muted-foreground p-6 text-center">
                                            No permissions match "{search}".
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </TooltipProvider>
            </div>
        </AppLayout>
    );
}
