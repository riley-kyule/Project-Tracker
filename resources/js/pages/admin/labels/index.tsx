import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label as FieldLabel } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type LabelRow = { id: number; name: string; color: string; tasks_count: number };

type LabelForm = { name: string; color: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Labels', href: '/admin/labels' }];

function LabelDialog({ label, trigger }: { label?: LabelRow; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset } = useForm<LabelForm>({
        name: label?.name ?? '',
        color: label?.color ?? '#2478be',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        };

        if (label) {
            patch(`/admin/labels/${label.id}`, options);
        } else {
            post('/admin/labels', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{label ? `Edit ${label.name}` : 'New label'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="name">Name</FieldLabel>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="color">Color</FieldLabel>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                id="color"
                                value={data.color}
                                onChange={(e) => setData('color', e.target.value)}
                                className="h-10 w-14 rounded-md border p-1"
                            />
                            <Input value={data.color} onChange={(e) => setData('color', e.target.value)} className="font-mono" />
                        </div>
                        <InputError message={errors.color} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        {label ? 'Save changes' : 'Create label'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function LabelsIndex({ labels, canManage }: { labels: LabelRow[]; canManage: boolean }) {
    const destroy = (label: LabelRow) => {
        if (!confirm(`Delete the "${label.name}" label?`)) {
            return;
        }
        router.delete(`/admin/labels/${label.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Labels" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Labels</h1>
                    {canManage && (
                        <LabelDialog
                            trigger={
                                <Button size="sm">
                                    <Plus className="mr-1 size-4" /> New label
                                </Button>
                            }
                        />
                    )}
                </div>
                <div className="border-sidebar-border/70 dark:border-sidebar-border overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border border-b text-left">
                                <th className="p-3 font-medium">Label</th>
                                <th className="p-3 font-medium">Tasks</th>
                                {canManage && <th className="p-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {labels.map((label) => (
                                <tr key={label.id} className="border-sidebar-border/40 dark:border-sidebar-border/40 border-b last:border-0">
                                    <td className="p-3 font-medium">
                                        <span className="inline-flex items-center gap-2">
                                            <span className="inline-block size-3 rounded-full" style={{ backgroundColor: label.color }} />
                                            {label.name}
                                        </span>
                                    </td>
                                    <td className="p-3">{label.tasks_count}</td>
                                    {canManage && (
                                        <td className="p-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <LabelDialog
                                                    label={label}
                                                    trigger={
                                                        <Button variant="ghost" size="sm" aria-label={`Edit ${label.name}`}>
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                    }
                                                />
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label={`Delete ${label.name}`}
                                                    onClick={() => destroy(label)}
                                                    disabled={label.tasks_count > 0}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {labels.length === 0 && (
                                <tr>
                                    <td colSpan={canManage ? 3 : 2} className="text-muted-foreground p-4 text-center">
                                        No labels yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
