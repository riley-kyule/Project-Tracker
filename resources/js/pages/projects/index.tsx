import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Plus } from 'lucide-react';
import { useState } from 'react';

type ProjectRow = {
    id: number;
    name: string;
    status: string;
    health_status: string;
    priority: string;
    progress_percentage: number;
    deadline: string | null;
    department: { id: number; name: string } | null;
    owner: { id: number; name: string };
    tasks_count: number;
};

type Option = { id: number; name: string };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Projects', href: '/projects' }];

const healthVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    on_track: 'default',
    at_risk: 'secondary',
    off_track: 'destructive',
};

const NONE = 'none';

function NewProjectDialog({ departments, owners }: { departments: Option[]; owners: Option[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        name: '',
        department_id: NONE,
        owner_id: '',
        status: 'planned',
        health_status: 'on_track',
        priority: 'medium',
        deadline: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === NONE ? null : Number(form.department_id),
            owner_id: Number(form.owner_id),
            deadline: form.deadline === '' ? null : form.deadline,
        }));
        post('/projects', {
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
                    <Plus className="mr-1 size-4" /> New project
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New project</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="project-name">Name</Label>
                        <Input id="project-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label>Owner</Label>
                        <Select value={data.owner_id} onValueChange={(value) => setData('owner_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Choose an owner" />
                            </SelectTrigger>
                            <SelectContent>
                                {owners.map((owner) => (
                                    <SelectItem key={owner.id} value={owner.id.toString()}>
                                        {owner.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.owner_id} />
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
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="project-deadline">Deadline</Label>
                        <Input id="project-deadline" type="date" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} />
                        <InputError message={errors.deadline} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Create project
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ProjectsIndex({
    projects,
    departments,
    owners,
    canManage,
}: {
    projects: ProjectRow[];
    departments: Option[];
    owners: Option[];
    canManage: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Projects</h1>
                    {canManage && <NewProjectDialog departments={departments} owners={owners} />}
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {projects.map((project) => {
                        const overdue = project.deadline !== null && new Date(project.deadline) < new Date() && project.status !== 'completed';
                        return (
                            <Link
                                key={project.id}
                                href={`/projects/${project.id}`}
                                className="border-sidebar-border/70 dark:border-sidebar-border hover:border-brand-500 rounded-xl border p-4 transition-colors"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">{project.name}</span>
                                    {(project.health_status !== 'on_track' || overdue) && <AlertTriangle className="size-4 text-amber-500" />}
                                </div>
                                <div className="text-muted-foreground mt-1 text-sm">
                                    {project.department?.name ?? 'Company-wide'} · {project.owner.name} · {project.tasks_count} tasks
                                </div>
                                <div className="bg-secondary mt-3 h-1.5 overflow-hidden rounded-full">
                                    <div className="bg-brand-600 h-full" style={{ width: `${project.progress_percentage}%` }} />
                                </div>
                                <div className="mt-2 flex items-center gap-2">
                                    <Badge variant={healthVariant[project.health_status]}>{project.health_status.replace('_', ' ')}</Badge>
                                    <Badge variant="secondary" className="capitalize">
                                        {project.status.replace('_', ' ')}
                                    </Badge>
                                    {project.deadline && (
                                        <span className={`ml-auto text-xs ${overdue ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                                            {new Date(project.deadline).toLocaleDateString()}
                                        </span>
                                    )}
                                </div>
                            </Link>
                        );
                    })}
                    {projects.length === 0 && <p className="text-muted-foreground text-sm">No projects yet.</p>}
                </div>
            </div>
        </AppLayout>
    );
}
