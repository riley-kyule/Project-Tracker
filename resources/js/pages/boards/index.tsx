import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { KanbanSquare, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

type BoardRow = {
    id: number;
    name: string;
    description: string | null;
    visibility: 'company' | 'department' | 'restricted';
    department: { id: number; name: string } | null;
    tasks_count: number;
    is_active: boolean;
};

type DepartmentOption = { id: number; name: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Boards', href: '/boards' }];

const NONE = 'none';

function CreateBoardDialog({ departments }: { departments: DepartmentOption[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        name: '',
        description: '',
        department_id: NONE,
        visibility: 'company',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === NONE ? null : Number(form.department_id),
        }));
        post('/boards', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Plus className="mr-1 size-4" /> New board
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New board</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="board-name">Name</Label>
                        <Input id="board-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="board-description">Description</Label>
                        <Input id="board-description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Department</Label>
                        <Select value={data.department_id} onValueChange={(value) => setData('department_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Company-wide" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Company-wide</SelectItem>
                                {departments.map((department) => (
                                    <SelectItem key={department.id} value={department.id.toString()}>
                                        {department.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.department_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Visibility</Label>
                        <Select value={data.visibility} onValueChange={(value) => setData('visibility', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="company">Company</SelectItem>
                                <SelectItem value="department">Department</SelectItem>
                                <SelectItem value="restricted">Restricted</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.visibility} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create board
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function BoardsIndex({
    boards,
    departments,
    canCreate,
    canDelete,
}: {
    boards: BoardRow[];
    departments: DepartmentOption[];
    canCreate: boolean;
    canDelete: boolean;
}) {
    const destroy = (board: BoardRow, e: React.MouseEvent) => {
        e.preventDefault();
        if (!confirm(`Delete "${board.name}"? This cannot be undone from the UI.`)) return;
        router.delete(`/boards/${board.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Boards" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Boards</h1>
                    {canCreate && <CreateBoardDialog departments={departments} />}
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {boards.map((board) => (
                        <div
                            key={board.id}
                            className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 relative rounded-xl border p-4 transition-colors"
                        >
                            {canDelete && (
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive absolute top-2 right-2 size-7"
                                    aria-label={`Delete ${board.name}`}
                                    onClick={(e) => destroy(board, e)}
                                >
                                    <Trash2 className="size-3.5" />
                                </Button>
                            )}
                            <Link href={`/boards/${board.id}`} className="block">
                                <div className="flex items-center gap-2">
                                    <KanbanSquare className="text-brand-600 dark:text-brand-400 size-5" />
                                    <span className="font-medium">{board.name}</span>
                                </div>
                                <div className="text-muted-foreground mt-2 flex items-center gap-2 text-sm">
                                    <span>{board.department?.name ?? 'Company-wide'}</span>
                                    <span>·</span>
                                    <span>
                                        {board.tasks_count} {board.tasks_count === 1 ? 'task' : 'tasks'}
                                    </span>
                                    <Badge variant="secondary" className="ml-auto">
                                        {board.visibility}
                                    </Badge>
                                </div>
                            </Link>
                        </div>
                    ))}
                </div>
                {boards.length === 0 && (
                    <p className="text-muted-foreground text-sm">
                        No boards visible to you yet. {canCreate ? 'Create the first one.' : 'Ask a manager to add you to a board.'}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
