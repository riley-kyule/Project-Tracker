import InputError from '@/components/input-error';
import { ListCappedNotice } from '@/components/list-capped-notice';
import { SortableHeader, useClientSort } from '@/components/sortable-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';

type Ref = { id: number; name: string };

type EmployeeRow = {
    id: number;
    staff_number: string;
    full_name: string;
    job_title: string | null;
    department: { id: number; name: string } | null;
    employment_type: string;
    employment_status: string;
    contract_end_date: string | null;
    has_login: boolean;
};

type PageProps = {
    employees: EmployeeRow[];
    departments: Ref[];
    managers: Ref[];
    linkableUsers: { id: number; name: string; email: string }[];
    suggestedStaffNumber: string;
    staffNumberPrefix: string;
    listCapped: boolean;
    canManage: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'People', href: '/hr/employees' }];

const NONE = 'none';
const EMPLOYMENT_TYPES = ['permanent', 'contract', 'consultancy', 'casual', 'intern'];
const STATUSES = ['active', 'on_probation', 'on_leave', 'suspended', 'terminated'];

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'default',
    on_probation: 'secondary',
    on_leave: 'secondary',
    suspended: 'destructive',
    terminated: 'outline',
};

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function CreateEmployeeDialog({
    departments,
    managers,
    linkableUsers,
    suggestedStaffNumber,
}: Pick<PageProps, 'departments' | 'managers' | 'linkableUsers' | 'suggestedStaffNumber'>) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        staff_number: suggestedStaffNumber,
        first_name: '',
        middle_name: '',
        last_name: '',
        job_title: '',
        department_id: NONE,
        manager_id: NONE,
        is_org_head: false as boolean,
        employment_type: 'permanent',
        employment_status: 'active',
        user_id: NONE,
        contract_start_date: '',
        contract_end_date: '',
        date_hired: '',
        personal_email: '',
        phone: '',
        national_id_number: '',
        kra_pin: '',
        payment_method: 'bank',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === NONE ? null : Number(form.department_id),
            manager_id: form.is_org_head || form.manager_id === NONE ? null : Number(form.manager_id),
            user_id: form.user_id === NONE ? null : Number(form.user_id),
            middle_name: form.middle_name || null,
            contract_start_date: form.contract_start_date || null,
            contract_end_date: form.contract_end_date || null,
            date_hired: form.date_hired || null,
        }));
        post('/hr/employees', {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="mr-1 h-4 w-4" /> New employee
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>New employee record</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor="staff_number">Staff number</Label>
                        <Input id="staff_number" value={data.staff_number} onChange={(e) => setData('staff_number', e.target.value)} />
                        <InputError message={errors.staff_number} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="user_id">Linked login</Label>
                        <Combobox
                            id="user_id"
                            value={data.user_id}
                            onChange={(v) => setData('user_id', v || NONE)}
                            placeholder="No login"
                            options={[
                                { value: NONE, label: 'No login' },
                                ...linkableUsers.map((u) => ({ value: String(u.id), label: u.name, hint: u.email })),
                            ]}
                        />
                        <InputError message={errors.user_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="first_name">First name</Label>
                        <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                        <InputError message={errors.first_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                        <InputError message={errors.last_name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="job_title">Job title</Label>
                        <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                        <InputError message={errors.job_title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="department_id">Department *</Label>
                        <Select value={data.department_id} onValueChange={(v) => setData('department_id', v)}>
                            <SelectTrigger id="department_id">
                                <SelectValue placeholder="Select department" />
                            </SelectTrigger>
                            <SelectContent>
                                {departments.map((d) => (
                                    <SelectItem key={d.id} value={String(d.id)}>
                                        {d.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.department_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="manager_id">Reports to {data.is_org_head ? '' : '*'}</Label>
                        <Combobox
                            id="manager_id"
                            value={data.is_org_head ? '' : data.manager_id === NONE ? '' : data.manager_id}
                            onChange={(v) => setData('manager_id', v || NONE)}
                            disabled={data.is_org_head}
                            placeholder="Select manager"
                            options={managers.map((m) => ({ value: String(m.id), label: m.name }))}
                        />
                        <label className="text-muted-foreground flex items-center gap-2 text-xs">
                            <Checkbox checked={data.is_org_head} onCheckedChange={(v) => setData('is_org_head', v === true)} />
                            Top of the reporting line (no manager)
                        </label>
                        <InputError message={errors.manager_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="employment_type">Employment type</Label>
                        <Select value={data.employment_type} onValueChange={(v) => setData('employment_type', v)}>
                            <SelectTrigger id="employment_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {EMPLOYMENT_TYPES.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {label(t)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="employment_status">Status</Label>
                        <Select value={data.employment_status} onValueChange={(v) => setData('employment_status', v)}>
                            <SelectTrigger id="employment_status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {label(s)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="date_hired">Date hired</Label>
                        <DateField id="date_hired" value={data.date_hired} onChange={(v) => setData('date_hired', v)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="contract_start_date">Contract start</Label>
                        <DateField id="contract_start_date" value={data.contract_start_date} onChange={(v) => setData('contract_start_date', v)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="contract_end_date">Contract end</Label>
                        <DateField id="contract_end_date" value={data.contract_end_date} onChange={(v) => setData('contract_end_date', v)} />
                        <InputError message={errors.contract_end_date} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="national_id_number">National ID</Label>
                        <Input
                            id="national_id_number"
                            value={data.national_id_number}
                            onChange={(e) => setData('national_id_number', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="kra_pin">KRA PIN</Label>
                        <Input id="kra_pin" value={data.kra_pin} onChange={(e) => setData('kra_pin', e.target.value)} />
                        <InputError message={errors.kra_pin} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="personal_email">Personal email</Label>
                        <Input
                            id="personal_email"
                            type="email"
                            value={data.personal_email}
                            onChange={(e) => setData('personal_email', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="phone">Phone</Label>
                        <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </div>
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            Create
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NumberingDialog({ prefix, sample }: { prefix: string; sample: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({ staff_number_prefix: prefix });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/hr/employees/numbering', { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Numbering</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Staff numbering</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="prefix">Prefix</Label>
                        <Input
                            id="prefix"
                            value={data.staff_number_prefix}
                            onChange={(e) => setData('staff_number_prefix', e.target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase())}
                            placeholder="e.g. EXO"
                        />
                        <InputError message={errors.staff_number_prefix} />
                        <p className="text-muted-foreground text-xs">
                            New staff numbers are suggested as <span className="font-medium">{data.staff_number_prefix || 'EXO'}-030</span> (the next
                            free number, padded to match existing ones). Currently: {sample}. Leave blank to infer the prefix from existing records.
                        </p>
                    </div>
                    <div>
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeesIndex({
    employees,
    departments,
    managers,
    linkableUsers,
    suggestedStaffNumber,
    staffNumberPrefix,
    listCapped,
    canManage,
}: PageProps) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [includeTerminated, setIncludeTerminated] = useState(false);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return employees.filter((e) => {
            if (!includeTerminated && e.employment_status === 'terminated') return false;
            if (statusFilter !== 'all' && e.employment_status !== statusFilter) return false;
            if (!q) return true;
            return (
                e.full_name.toLowerCase().includes(q) ||
                e.staff_number.toLowerCase().includes(q) ||
                (e.job_title ?? '').toLowerCase().includes(q) ||
                (e.department?.name ?? '').toLowerCase().includes(q)
            );
        });
    }, [employees, search, statusFilter, includeTerminated]);

    const { sorted, sort, onSort } = useClientSort(filtered, (e, column) => {
        switch (column) {
            case 'full_name':
                return e.full_name;
            case 'staff_number':
                return e.staff_number;
            case 'job_title':
                return e.job_title ?? null;
            case 'department':
                return e.department?.name ?? null;
            case 'employment_type':
                return e.employment_type;
            case 'employment_status':
                return e.employment_status;
            case 'contract_end_date':
                return e.contract_end_date ?? null;
            default:
                return null;
        }
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="People" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">People</h1>
                        <p className="text-muted-foreground text-sm">{filtered.length} employees</p>
                        <ListCappedNotice capped={listCapped} />
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            <NumberingDialog prefix={staffNumberPrefix} sample={suggestedStaffNumber} />
                            <CreateEmployeeDialog
                                departments={departments}
                                managers={managers}
                                linkableUsers={linkableUsers}
                                suggestedStaffNumber={suggestedStaffNumber}
                            />
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        placeholder="Search name, staff number, role…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-xs"
                    />
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {STATUSES.map((s) => (
                                <SelectItem key={s} value={s}>
                                    {label(s)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={includeTerminated} onCheckedChange={(v) => setIncludeTerminated(v === true)} />
                        Include terminated
                    </label>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <SortableHeader column="full_name" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Name
                                </SortableHeader>
                                <SortableHeader column="staff_number" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Staff #
                                </SortableHeader>
                                <SortableHeader column="job_title" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Role
                                </SortableHeader>
                                <SortableHeader column="department" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Department
                                </SortableHeader>
                                <SortableHeader column="employment_type" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Type
                                </SortableHeader>
                                <SortableHeader column="employment_status" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Status
                                </SortableHeader>
                                <SortableHeader column="contract_end_date" sort={sort} onSort={onSort} className="px-3 py-2">
                                    Contract ends
                                </SortableHeader>
                            </tr>
                        </thead>
                        <tbody>
                            {sorted.map((e) => (
                                <tr key={e.id} className="hover:bg-muted/30 border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/hr/employees/${e.id}`} className="text-primary font-medium hover:underline">
                                            {e.full_name}
                                        </Link>
                                        {!e.has_login && <span className="text-muted-foreground ml-2 text-xs">no login</span>}
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2">{e.staff_number}</td>
                                    <td className="px-3 py-2">{e.job_title ?? '—'}</td>
                                    <td className="px-3 py-2">{e.department?.name ?? '—'}</td>
                                    <td className="px-3 py-2">{label(e.employment_type)}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={STATUS_VARIANT[e.employment_status] ?? 'outline'}>{label(e.employment_status)}</Badge>
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2">{e.contract_end_date ? fmtDate(e.contract_end_date) : '—'}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-3 py-8 text-center">
                                        No employees match.
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
