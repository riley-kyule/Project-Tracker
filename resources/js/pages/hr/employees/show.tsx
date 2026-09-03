import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

type Ref = { id: number; name: string };

type NextOfKin = {
    id: number;
    name: string;
    relationship: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    is_primary: boolean;
};

type Contract = {
    id: number;
    title: string;
    employment_type: string;
    start_date: string;
    end_date: string | null;
    reason: string | null;
    notes: string | null;
    department: Ref | null;
};

type Doc = { id: number; name: string; category: string | null; size_bytes: number; uploaded_by: string | null; created_at: string };

type Goal = {
    id: number;
    title: string;
    description: string | null;
    weight: number | null;
    metric: string | null;
    progress_pct: number;
    status: string;
    rating: number | null;
    due_on: string | null;
    cycle: string | null;
};
type ReviewRow = { id: number; cycle: string | null; status: string; overall_rating: number | null };

type Allowance = { name: string; amount: number; taxable?: boolean; pensionable?: boolean };
type Compensation = {
    id: number;
    effective_from: string;
    currency: string;
    basic_salary: number;
    allowances: Allowance[];
    change_reason: string | null;
};
type RecurringItem = {
    id: number;
    kind: string;
    name: string;
    calc_type: string;
    amount: number;
    balance: number | null;
    is_taxable: boolean;
    is_pretax: boolean;
    affects_nssf: boolean;
    is_active: boolean;
    starts_on: string | null;
    ends_on: string | null;
};

type AssetRow = {
    id: number;
    asset: { id: number; asset_tag: string; name: string } | null;
    assigned_at: string;
    returned_at: string | null;
    expected_return_at: string | null;
};

type Employee = {
    id: number;
    user_id: number | null;
    staff_number: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    full_name: string;
    tenure_months: number | null;
    date_of_birth: string | null;
    gender: string | null;
    marital_status: string | null;
    national_id_number: string | null;
    kra_pin: string | null;
    nssf_number: string | null;
    shif_number: string | null;
    insurance_membership_number: string | null;
    personal_email: string | null;
    phone: string | null;
    alt_phone: string | null;
    postal_address: string | null;
    physical_address: string | null;
    county: string | null;
    department_id: number | null;
    job_title: string | null;
    employment_type: string;
    manager_id: number | null;
    is_org_head: boolean;
    date_hired: string | null;
    contract_start_date: string | null;
    contract_end_date: string | null;
    probation_end_date: string | null;
    employment_status: string;
    termination_date: string | null;
    termination_reason: string | null;
    rehire_eligible: boolean;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    payment_method: string;
    mpesa_number: string | null;
    notes: string | null;
    department: Ref | null;
    manager: Ref | null;
    user: { id: number; name: string; email: string } | null;
    next_of_kin: NextOfKin[];
    contracts: Contract[];
    documents: Doc[];
    assets: AssetRow[];
    recurring_items: RecurringItem[];
    compensation: Compensation[] | null;
    goals: Goal[];
    reviews: ReviewRow[];
};

type PageProps = {
    employee: Employee;
    departments: Ref[];
    managers: Ref[];
    linkableUsers: { id: number; name: string; email: string }[];
    canManage: boolean;
    canManageCompensation: boolean;
    canViewCompensation: boolean;
    canManageGoals: boolean;
};

const NONE = 'none';
const BASE_TABS = ['Profile', 'Next of Kin', 'Contracts', 'Documents', 'Assets', 'Pay items', 'Performance'] as const;
const EMPLOYMENT_TYPES = ['permanent', 'contract', 'consultancy', 'casual', 'intern'];
const STATUSES = ['active', 'on_probation', 'on_leave', 'suspended', 'terminated'];
const DOC_CATEGORIES = ['contract', 'id_copy', 'kra', 'nssf', 'shif', 'certificate', 'other'];

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/** Normalise a date value to what <input type="date"> expects (YYYY-MM-DD). */
function dateInput(value: string | null | undefined) {
    return (value ?? '').slice(0, 10);
}

function Field({ label: l, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-muted-foreground text-xs">{l}</span>
            <span className="text-sm">{value ?? '—'}</span>
        </div>
    );
}

function EditProfileDialog({
    employee,
    departments,
    managers,
    linkableUsers,
}: {
    employee: Employee;
    departments: Ref[];
    managers: Ref[];
    linkableUsers: { id: number; name: string; email: string }[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, patch, processing, errors, transform } = useForm({
        user_id: employee.user_id ? String(employee.user_id) : NONE,
        staff_number: employee.staff_number,
        first_name: employee.first_name,
        middle_name: employee.middle_name ?? '',
        last_name: employee.last_name,
        date_of_birth: dateInput(employee.date_of_birth),
        gender: employee.gender ?? '',
        marital_status: employee.marital_status ?? '',
        national_id_number: employee.national_id_number ?? '',
        kra_pin: employee.kra_pin ?? '',
        nssf_number: employee.nssf_number ?? '',
        shif_number: employee.shif_number ?? '',
        insurance_membership_number: employee.insurance_membership_number ?? '',
        personal_email: employee.personal_email ?? '',
        phone: employee.phone ?? '',
        alt_phone: employee.alt_phone ?? '',
        postal_address: employee.postal_address ?? '',
        physical_address: employee.physical_address ?? '',
        county: employee.county ?? '',
        department_id: employee.department_id ? String(employee.department_id) : NONE,
        job_title: employee.job_title ?? '',
        employment_type: employee.employment_type,
        manager_id: employee.manager_id ? String(employee.manager_id) : NONE,
        is_org_head: employee.is_org_head,
        date_hired: dateInput(employee.date_hired),
        contract_start_date: dateInput(employee.contract_start_date),
        contract_end_date: dateInput(employee.contract_end_date),
        probation_end_date: dateInput(employee.probation_end_date),
        employment_status: employee.employment_status,
        termination_date: dateInput(employee.termination_date),
        termination_reason: employee.termination_reason ?? '',
        bank_name: employee.bank_name ?? '',
        bank_branch: employee.bank_branch ?? '',
        bank_account_name: employee.bank_account_name ?? '',
        bank_account_number: employee.bank_account_number ?? '',
        payment_method: employee.payment_method,
        mpesa_number: employee.mpesa_number ?? '',
        notes: employee.notes ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => {
            const out: Record<string, unknown> = { ...form };
            out.department_id = form.department_id === NONE ? null : Number(form.department_id);
            out.manager_id = form.is_org_head || form.manager_id === NONE ? null : Number(form.manager_id);
            out.user_id = form.user_id === NONE ? null : Number(form.user_id);
            for (const k of Object.keys(out)) {
                if (out[k] === '') out[k] = null;
            }
            return out;
        });
        patch(`/hr/employees/${employee.id}`, { onSuccess: () => setOpen(false) });
    };

    const text = (name: keyof typeof data, l: string, type = 'text') => (
        <div className="grid gap-1.5">
            <Label htmlFor={name}>{l}</Label>
            {type === 'date' ? (
                <DateField id={name} value={data[name] as string} onChange={(v) => setData(name, v)} />
            ) : (
                <Input id={name} type={type} value={data[name] as string} onChange={(e) => setData(name, e.target.value)} />
            )}
            <InputError message={errors[name as keyof typeof errors]} />
        </div>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="mr-1 h-4 w-4" /> Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit {employee.full_name}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-1.5 sm:col-span-3">
                        <Label htmlFor="link-user">Linked login account</Label>
                        <Select value={data.user_id} onValueChange={(v) => setData('user_id', v)}>
                            <SelectTrigger id="link-user">
                                <SelectValue placeholder="Not linked" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>Not linked — no system login</SelectItem>
                                {linkableUsers.map((u) => (
                                    <SelectItem key={u.id} value={String(u.id)}>
                                        {u.name} ({u.email})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.user_id} />
                        <p className="text-muted-foreground text-xs">
                            Links this record to the person's sign-in account — enables their self-service pages (My Employee Data, Leave Application,
                            My Payslips).
                        </p>
                    </div>
                    {text('staff_number', 'Staff number')}
                    {text('first_name', 'First name')}
                    {text('middle_name', 'Middle name')}
                    {text('last_name', 'Last name')}
                    {text('date_of_birth', 'Date of birth', 'date')}
                    {text('gender', 'Gender')}
                    {text('marital_status', 'Marital status')}
                    {text('national_id_number', 'National ID')}
                    {text('kra_pin', 'KRA PIN')}
                    {text('nssf_number', 'NSSF number')}
                    {text('shif_number', 'SHA/SHIF number')}
                    {text('insurance_membership_number', 'Insurance member ID')}
                    {text('personal_email', 'Personal email', 'email')}
                    {text('phone', 'Phone')}
                    {text('alt_phone', 'Alt phone')}
                    {text('physical_address', 'Physical address')}
                    {text('postal_address', 'Postal address')}
                    {text('county', 'County')}
                    {text('job_title', 'Job title')}
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
                        <Select value={data.manager_id} onValueChange={(v) => setData('manager_id', v)} disabled={data.is_org_head}>
                            <SelectTrigger id="manager_id">
                                <SelectValue placeholder="Select manager" />
                            </SelectTrigger>
                            <SelectContent>
                                {managers.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                    {text('date_hired', 'Date hired', 'date')}
                    {text('contract_start_date', 'Contract start', 'date')}
                    {text('contract_end_date', 'Contract end', 'date')}
                    {text('probation_end_date', 'Probation end', 'date')}
                    {text('termination_date', 'Termination date', 'date')}
                    {text('termination_reason', 'Termination reason')}
                    {text('bank_name', 'Bank')}
                    {text('bank_branch', 'Bank branch')}
                    {text('bank_account_name', 'Account name')}
                    {text('bank_account_number', 'Account number')}
                    <div className="grid gap-1.5">
                        <Label htmlFor="payment_method">Payment method</Label>
                        <Select value={data.payment_method} onValueChange={(v) => setData('payment_method', v)}>
                            <SelectTrigger id="payment_method">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['bank', 'mpesa', 'cash', 'cheque'].map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {label(m)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {text('mpesa_number', 'M-Pesa number')}
                    <div className="sm:col-span-3">
                        <Button type="submit" disabled={processing}>
                            Save
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NextOfKinDialog({ employeeId, kin }: { employeeId: number; kin?: NextOfKin }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: kin?.name ?? '',
        relationship: kin?.relationship ?? '',
        phone: kin?.phone ?? '',
        email: kin?.email ?? '',
        address: kin?.address ?? '',
        is_primary: kin?.is_primary ?? false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                if (!kin) reset();
            },
        };
        if (kin) patch(`/hr/employees/${employeeId}/next-of-kin/${kin.id}`, opts);
        else post(`/hr/employees/${employeeId}/next-of-kin`, opts);
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {kin ? (
                    <Button variant="ghost" size="sm">
                        <Pencil className="h-4 w-4" />
                    </Button>
                ) : (
                    <Button size="sm">
                        <Plus className="mr-1 h-4 w-4" /> Add contact
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{kin ? 'Edit' : 'Add'} next of kin</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="nok-name">Name</Label>
                        <Input id="nok-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="nok-rel">Relationship</Label>
                        <Input id="nok-rel" value={data.relationship} onChange={(e) => setData('relationship', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="nok-phone">Phone</Label>
                        <Input id="nok-phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="nok-email">Email</Label>
                        <Input id="nok-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="nok-address">Address</Label>
                        <Input id="nok-address" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_primary} onCheckedChange={(v) => setData('is_primary', v === true)} />
                        Primary contact
                    </label>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RenewContractDialog({ employeeId }: { employeeId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        start_date: '',
        end_date: '',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({ ...form, title: form.title || null, end_date: form.end_date || null, notes: form.notes || null }));
        post(`/hr/employees/${employeeId}/renew-contract`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Renew contract
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Renew contract</DialogTitle>
                </DialogHeader>
                <p className="text-muted-foreground text-sm">
                    Closes the current contract, opens a new one for the dates below, and resets the employee's leave entitlement to the configured
                    defaults for the new period.
                </p>
                <form onSubmit={submit} className="mt-3 grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="r-start">New start date *</Label>
                            <DateField id="r-start" value={data.start_date} onChange={(v) => setData('start_date', v)} />
                            <InputError message={errors.start_date} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="r-end">New end date</Label>
                            <DateField id="r-end" value={data.end_date} onChange={(v) => setData('end_date', v)} />
                            <InputError message={errors.end_date} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="r-title">Title (optional)</Label>
                        <Input
                            id="r-title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Defaults to current job title"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="r-notes">Notes</Label>
                        <Input id="r-notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing || !data.start_date}>
                        Renew
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ContractDialog({ employeeId, departments }: { employeeId: number; departments: Ref[] }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        department_id: NONE,
        employment_type: 'permanent',
        start_date: '',
        end_date: '',
        reason: 'hire',
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === NONE ? null : Number(form.department_id),
            end_date: form.end_date || null,
        }));
        post(`/hr/employees/${employeeId}/contracts`, {
            preserveScroll: true,
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
                    <Plus className="mr-1 h-4 w-4" /> Add contract
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add contract</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-title">Title</Label>
                        <Input id="c-title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-dept">Department</Label>
                        <Select value={data.department_id} onValueChange={(v) => setData('department_id', v)}>
                            <SelectTrigger id="c-dept">
                                <SelectValue placeholder="None" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>None</SelectItem>
                                {departments.map((d) => (
                                    <SelectItem key={d.id} value={String(d.id)}>
                                        {d.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-type">Employment type</Label>
                        <Select value={data.employment_type} onValueChange={(v) => setData('employment_type', v)}>
                            <SelectTrigger id="c-type">
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
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-start">Start date</Label>
                            <DateField id="c-start" value={data.start_date} onChange={(v) => setData('start_date', v)} />
                            <InputError message={errors.start_date} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="c-end">End date</Label>
                            <DateField id="c-end" value={data.end_date} onChange={(v) => setData('end_date', v)} />
                            <InputError message={errors.end_date} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="c-reason">Reason</Label>
                        <Select value={data.reason} onValueChange={(v) => setData('reason', v)}>
                            <SelectTrigger id="c-reason">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['hire', 'renewal', 'promotion', 'transfer', 'end'].map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {label(r)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DocumentUpload({ employeeId }: { employeeId: number }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; category: string }>({
        file: null,
        category: 'contract',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/hr/employees/${employeeId}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <div className="grid gap-1.5">
                <Label htmlFor="doc-file">File</Label>
                <Input id="doc-file" ref={fileRef} type="file" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} className="max-w-xs" />
                <InputError message={errors.file} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="doc-cat">Category</Label>
                <Select value={data.category} onValueChange={(v) => setData('category', v)}>
                    <SelectTrigger id="doc-cat" className="w-40">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DOC_CATEGORIES.map((c) => (
                            <SelectItem key={c} value={c}>
                                {label(c)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <Button type="submit" disabled={processing || !data.file}>
                Upload
            </Button>
        </form>
    );
}

function RecurringItemDialog({ employeeId }: { employeeId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        kind: 'deduction',
        name: '',
        calc_type: 'fixed',
        amount: '',
        is_taxable: true as boolean,
        is_pretax: false as boolean,
        affects_nssf: false as boolean,
        balance: '',
        is_active: true as boolean,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({ ...f, amount: Number(f.amount), balance: f.balance === '' ? null : Number(f.balance) }));
        post(`/hr/employees/${employeeId}/recurring-items`, {
            preserveScroll: true,
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
                    <Plus className="mr-1 h-4 w-4" /> Add pay item
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add recurring pay item</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="ri-kind">Kind</Label>
                            <Select value={data.kind} onValueChange={(v) => setData('kind', v)}>
                                <SelectTrigger id="ri-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="earning">Earning</SelectItem>
                                    <SelectItem value="deduction">Deduction</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ri-calc">Calculation</Label>
                            <Select value={data.calc_type} onValueChange={(v) => setData('calc_type', v)}>
                                <SelectTrigger id="ri-calc">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fixed">Fixed amount</SelectItem>
                                    <SelectItem value="percent_of_basic">% of basic</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="ri-name">Name</Label>
                        <Input id="ri-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="ri-amount">Amount</Label>
                            <Input id="ri-amount" type="number" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} />
                            <InputError message={errors.amount} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ri-balance">Loan balance (optional)</Label>
                            <Input
                                id="ri-balance"
                                type="number"
                                step="0.01"
                                value={data.balance}
                                onChange={(e) => setData('balance', e.target.value)}
                            />
                        </div>
                    </div>
                    {data.kind === 'earning' && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.is_taxable} onCheckedChange={(v) => setData('is_taxable', v === true)} /> Taxable
                        </label>
                    )}
                    {data.kind === 'deduction' && (
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={data.is_pretax} onCheckedChange={(v) => setData('is_pretax', v === true)} /> Taken before PAYE (e.g.
                            pension)
                        </label>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.affects_nssf} onCheckedChange={(v) => setData('affects_nssf', v === true)} /> Counts as pensionable
                        pay
                    </label>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CompensationDialog({ employeeId }: { employeeId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        effective_from: '',
        currency: 'KES',
        basic_salary: '',
        change_reason: '',
        allowances: [] as Allowance[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({
            ...f,
            basic_salary: Number(f.basic_salary),
            allowances: f.allowances.map((a) => ({ ...a, amount: Number(a.amount) })),
        }));
        post(`/hr/employees/${employeeId}/compensation`, {
            preserveScroll: true,
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
                    <Plus className="mr-1 h-4 w-4" /> New salary
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add compensation record</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="comp-from">Effective from</Label>
                            <DateField id="comp-from" value={data.effective_from} onChange={(v) => setData('effective_from', v)} />
                            <InputError message={errors.effective_from} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="comp-basic">Basic salary</Label>
                            <Input
                                id="comp-basic"
                                type="number"
                                step="0.01"
                                value={data.basic_salary}
                                onChange={(e) => setData('basic_salary', e.target.value)}
                            />
                            <InputError message={errors.basic_salary} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Allowances</Label>
                        {data.allowances.map((a, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <Input
                                    placeholder="Name"
                                    value={a.name}
                                    onChange={(e) => {
                                        const next = [...data.allowances];
                                        next[i] = { ...a, name: e.target.value };
                                        setData('allowances', next);
                                    }}
                                />
                                <Input
                                    type="number"
                                    placeholder="Amount"
                                    value={a.amount}
                                    onChange={(e) => {
                                        const next = [...data.allowances];
                                        next[i] = { ...a, amount: Number(e.target.value) };
                                        setData('allowances', next);
                                    }}
                                />
                                <label className="flex items-center gap-1 text-xs">
                                    <Checkbox
                                        checked={a.taxable ?? true}
                                        onCheckedChange={(v) => {
                                            const next = [...data.allowances];
                                            next[i] = { ...a, taxable: v === true };
                                            setData('allowances', next);
                                        }}
                                    />
                                    taxable
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        setData(
                                            'allowances',
                                            data.allowances.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setData('allowances', [...data.allowances, { name: '', amount: 0, taxable: true, pensionable: false }])}
                        >
                            Add allowance
                        </Button>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="comp-reason">Reason</Label>
                        <Input id="comp-reason" value={data.change_reason} onChange={(e) => setData('change_reason', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function GoalDialog({ employeeId }: { employeeId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        title: '',
        description: '',
        weight: '',
        metric: '',
        due_on: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((f) => ({
            ...f,
            weight: f.weight === '' ? null : Number(f.weight),
            description: f.description || null,
            metric: f.metric || null,
            due_on: f.due_on || null,
        }));
        post(`/hr/employees/${employeeId}/goals`, {
            preserveScroll: true,
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
                    <Plus className="mr-1 h-4 w-4" /> Add goal
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add goal</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="g-title">Title</Label>
                        <Input id="g-title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="g-desc">Description</Label>
                        <Input id="g-desc" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="g-weight">Weight (%)</Label>
                            <Input id="g-weight" type="number" value={data.weight} onChange={(e) => setData('weight', e.target.value)} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="g-due">Due</Label>
                            <DateField id="g-due" value={data.due_on} onChange={(v) => setData('due_on', v)} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="g-metric">Metric</Label>
                        <Input id="g-metric" value={data.metric} onChange={(e) => setData('metric', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeShow({
    employee,
    departments,
    managers,
    linkableUsers,
    canManage,
    canManageCompensation,
    canViewCompensation,
    canManageGoals,
}: PageProps) {
    const tabs: string[] = canViewCompensation ? [...BASE_TABS, 'Compensation'] : [...BASE_TABS];
    const [tab, setTab] = useState<string>('Profile');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'People', href: '/hr/employees' },
        { title: employee.full_name, href: `/hr/employees/${employee.id}` },
    ];

    const remove = (url: string) => {
        if (confirm('Remove this entry?')) router.delete(url, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={employee.full_name} />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{employee.full_name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {employee.job_title ?? 'No role'} · {employee.department?.name ?? 'No department'} · {employee.staff_number}
                        </p>
                        <div className="mt-1 flex items-center gap-2">
                            <Badge>{label(employee.employment_status)}</Badge>
                            {employee.tenure_months != null && (
                                <span className="text-muted-foreground text-xs">
                                    {Math.floor(employee.tenure_months / 12)}y {employee.tenure_months % 12}m tenure
                                </span>
                            )}
                            {employee.user && <span className="text-muted-foreground text-xs">· login: {employee.user.email}</span>}
                        </div>
                    </div>
                    {canManage && (
                        <EditProfileDialog employee={employee} departments={departments} managers={managers} linkableUsers={linkableUsers} />
                    )}
                </div>

                <div className="flex flex-wrap gap-1 border-b">
                    {tabs.map((t) => (
                        <button
                            key={t}
                            onClick={() => setTab(t)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
                                tab === t ? 'border-primary text-primary' : 'text-muted-foreground hover:text-foreground border-transparent'
                            }`}
                        >
                            {t}
                        </button>
                    ))}
                </div>

                {tab === 'Profile' && (
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card className="p-4">
                            <h2 className="mb-3 text-sm font-semibold">Personal</h2>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Date of birth" value={fmtDate(employee.date_of_birth)} />
                                <Field label="Gender" value={employee.gender} />
                                <Field label="Marital status" value={employee.marital_status} />
                                <Field label="National ID" value={employee.national_id_number} />
                                <Field label="Personal email" value={employee.personal_email} />
                                <Field label="Phone" value={employee.phone} />
                                <Field label="Alt phone" value={employee.alt_phone} />
                                <Field label="County" value={employee.county} />
                                <Field label="Physical address" value={employee.physical_address} />
                                <Field label="Postal address" value={employee.postal_address} />
                            </div>
                        </Card>
                        <Card className="p-4">
                            <h2 className="mb-3 text-sm font-semibold">Statutory</h2>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="KRA PIN" value={employee.kra_pin} />
                                <Field label="NSSF number" value={employee.nssf_number} />
                                <Field label="SHA/SHIF number" value={employee.shif_number} />
                                <Field label="Insurance member ID" value={employee.insurance_membership_number} />
                            </div>
                        </Card>
                        <Card className="p-4">
                            <h2 className="mb-3 text-sm font-semibold">Employment</h2>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Type" value={label(employee.employment_type)} />
                                <Field label="Reports to" value={employee.manager?.name} />
                                <Field label="Date hired" value={fmtDate(employee.date_hired)} />
                                <Field label="Contract start" value={fmtDate(employee.contract_start_date)} />
                                <Field label="Contract end" value={fmtDate(employee.contract_end_date)} />
                                <Field label="Probation end" value={fmtDate(employee.probation_end_date)} />
                                {employee.employment_status === 'terminated' && (
                                    <>
                                        <Field label="Termination date" value={fmtDate(employee.termination_date)} />
                                        <Field label="Termination reason" value={employee.termination_reason} />
                                    </>
                                )}
                            </div>
                        </Card>
                        <Card className="p-4">
                            <h2 className="mb-3 text-sm font-semibold">Payment</h2>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Method" value={label(employee.payment_method)} />
                                <Field label="Bank" value={employee.bank_name} />
                                <Field label="Branch" value={employee.bank_branch} />
                                <Field label="Account name" value={employee.bank_account_name} />
                                <Field label="Account number" value={employee.bank_account_number} />
                                <Field label="M-Pesa" value={employee.mpesa_number} />
                            </div>
                        </Card>
                    </div>
                )}

                {tab === 'Next of Kin' && (
                    <Card className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Next of kin</h2>
                            {canManage && <NextOfKinDialog employeeId={employee.id} />}
                        </div>
                        <div className="grid gap-2">
                            {employee.next_of_kin.map((k) => (
                                <div key={k.id} className="flex items-center justify-between rounded border p-3 text-sm">
                                    <div>
                                        <span className="font-medium">{k.name}</span>
                                        {k.is_primary && <Badge className="ml-2">Primary</Badge>}
                                        <div className="text-muted-foreground">
                                            {[k.relationship, k.phone, k.email, k.address].filter(Boolean).join(' · ') || '—'}
                                        </div>
                                    </div>
                                    {canManage && (
                                        <div className="flex items-center gap-1">
                                            <NextOfKinDialog employeeId={employee.id} kin={k} />
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => remove(`/hr/employees/${employee.id}/next-of-kin/${k.id}`)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {employee.next_of_kin.length === 0 && <p className="text-muted-foreground text-sm">None recorded.</p>}
                        </div>
                    </Card>
                )}

                {tab === 'Contracts' && (
                    <Card className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Contract history</h2>
                            {canManage && (
                                <div className="flex gap-2">
                                    <RenewContractDialog employeeId={employee.id} />
                                    <ContractDialog employeeId={employee.id} departments={departments} />
                                </div>
                            )}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="py-1 pr-3">Title</th>
                                        <th className="py-1 pr-3">Department</th>
                                        <th className="py-1 pr-3">Type</th>
                                        <th className="py-1 pr-3">Start</th>
                                        <th className="py-1 pr-3">End</th>
                                        <th className="py-1 pr-3">Reason</th>
                                        {canManage && <th />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {employee.contracts.map((c) => (
                                        <tr key={c.id} className="border-t">
                                            <td className="py-1.5 pr-3">{c.title}</td>
                                            <td className="py-1.5 pr-3">{c.department?.name ?? '—'}</td>
                                            <td className="py-1.5 pr-3">{label(c.employment_type)}</td>
                                            <td className="py-1.5 pr-3">{fmtDate(c.start_date)}</td>
                                            <td className="py-1.5 pr-3">{c.end_date ? fmtDate(c.end_date) : '—'}</td>
                                            <td className="py-1.5 pr-3">{c.reason ? label(c.reason) : '—'}</td>
                                            {canManage && (
                                                <td className="py-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => remove(`/hr/employees/${employee.id}/contracts/${c.id}`)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    {employee.contracts.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="text-muted-foreground py-4 text-center">
                                                No contracts recorded.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                {tab === 'Documents' && (
                    <Card className="p-4">
                        <h2 className="mb-3 text-sm font-semibold">Documents</h2>
                        {canManage && <DocumentUpload employeeId={employee.id} />}
                        <div className="mt-4 grid gap-2">
                            {employee.documents.map((d) => (
                                <div key={d.id} className="flex items-center justify-between rounded border p-3 text-sm">
                                    <div>
                                        <span className="font-medium">{d.name}</span>
                                        {d.category && (
                                            <Badge variant="outline" className="ml-2">
                                                {label(d.category)}
                                            </Badge>
                                        )}
                                        <div className="text-muted-foreground text-xs">
                                            {(d.size_bytes / 1024).toFixed(0)} KB · {d.uploaded_by ?? 'unknown'}
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <a href={`/hr/employees/${employee.id}/documents/${d.id}`}>
                                            <Button variant="ghost" size="sm">
                                                <Download className="h-4 w-4" />
                                            </Button>
                                        </a>
                                        {canManage && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => remove(`/hr/employees/${employee.id}/documents/${d.id}`)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {employee.documents.length === 0 && <p className="text-muted-foreground text-sm">No documents.</p>}
                        </div>
                    </Card>
                )}

                {tab === 'Assets' && (
                    <Card className="p-4">
                        <h2 className="mb-3 text-sm font-semibold">Assigned assets</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="py-1 pr-3">Asset</th>
                                        <th className="py-1 pr-3">Tag</th>
                                        <th className="py-1 pr-3">Assigned</th>
                                        <th className="py-1 pr-3">Expected return</th>
                                        <th className="py-1 pr-3">Returned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employee.assets.map((a) => (
                                        <tr key={a.id} className="border-t">
                                            <td className="py-1.5 pr-3">
                                                {a.asset ? (
                                                    <Link href={`/hr/assets/${a.asset.id}`} className="text-primary hover:underline">
                                                        {a.asset.name}
                                                    </Link>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="text-muted-foreground py-1.5 pr-3">{a.asset?.asset_tag ?? '—'}</td>
                                            <td className="py-1.5 pr-3">{fmtDate(a.assigned_at)}</td>
                                            <td className="py-1.5 pr-3">{a.expected_return_at ? fmtDate(a.expected_return_at) : '—'}</td>
                                            <td className="py-1.5 pr-3">{a.returned_at ? fmtDate(a.returned_at) : <Badge>Held</Badge>}</td>
                                        </tr>
                                    ))}
                                    {employee.assets.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="text-muted-foreground py-4 text-center">
                                                No assets assigned.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                {tab === 'Pay items' && (
                    <Card className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Recurring earnings &amp; deductions</h2>
                            {canManageCompensation && <RecurringItemDialog employeeId={employee.id} />}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground text-left">
                                    <tr>
                                        <th className="py-1 pr-3">Name</th>
                                        <th className="py-1 pr-3">Kind</th>
                                        <th className="py-1 pr-3">Amount</th>
                                        <th className="py-1 pr-3">Flags</th>
                                        <th className="py-1 pr-3">Balance</th>
                                        {canManageCompensation && <th />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {employee.recurring_items.map((i) => (
                                        <tr key={i.id} className="border-t">
                                            <td className="py-1.5 pr-3">
                                                {i.name} {!i.is_active && <Badge variant="outline">inactive</Badge>}
                                            </td>
                                            <td className="py-1.5 pr-3">{label(i.kind)}</td>
                                            <td className="py-1.5 pr-3">
                                                {i.calc_type === 'percent_of_basic' ? `${i.amount}% of basic` : i.amount.toLocaleString()}
                                            </td>
                                            <td className="text-muted-foreground py-1.5 pr-3 text-xs">
                                                {[
                                                    i.kind === 'earning' && (i.is_taxable ? 'taxable' : 'non-taxable'),
                                                    i.kind === 'deduction' && (i.is_pretax ? 'pre-tax' : 'post-tax'),
                                                    i.affects_nssf && 'affects NSSF',
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </td>
                                            <td className="py-1.5 pr-3">{i.balance != null ? i.balance.toLocaleString() : '—'}</td>
                                            {canManageCompensation && (
                                                <td className="py-1.5">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => remove(`/hr/employees/${employee.id}/recurring-items/${i.id}`)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    {employee.recurring_items.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="text-muted-foreground py-4 text-center">
                                                No recurring pay items.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                {tab === 'Performance' && (
                    <div className="grid gap-4">
                        <Card className="p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold">Goals</h2>
                                {canManageGoals && <GoalDialog employeeId={employee.id} />}
                            </div>
                            <div className="grid gap-2">
                                {employee.goals.map((g) => (
                                    <div key={g.id} className="rounded border p-3 text-sm">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">{g.title}</span>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">{label(g.status)}</Badge>
                                                {canManageGoals && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => remove(`/hr/employees/${employee.id}/goals/${g.id}`)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                        {g.description && <p className="text-muted-foreground text-xs">{g.description}</p>}
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {[
                                                g.cycle,
                                                g.weight != null ? `weight ${g.weight}%` : null,
                                                `progress ${g.progress_pct}%`,
                                                g.rating != null ? `rating ${g.rating}` : null,
                                                g.due_on ? `due ${fmtDate(g.due_on)}` : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </div>
                                ))}
                                {employee.goals.length === 0 && <p className="text-muted-foreground text-sm">No goals set.</p>}
                            </div>
                        </Card>
                        <Card className="p-4">
                            <h2 className="mb-3 text-sm font-semibold">Reviews</h2>
                            <div className="grid gap-2">
                                {employee.reviews.map((r) => (
                                    <Link
                                        key={r.id}
                                        href={`/hr/performance/reviews/${r.id}`}
                                        className="hover:bg-muted/30 flex items-center justify-between rounded border p-3 text-sm"
                                    >
                                        <span>{r.cycle ?? 'Review'}</span>
                                        <span className="flex items-center gap-2">
                                            {r.overall_rating != null && <span className="font-medium">{r.overall_rating}/5</span>}
                                            <Badge variant="outline">{label(r.status)}</Badge>
                                        </span>
                                    </Link>
                                ))}
                                {employee.reviews.length === 0 && <p className="text-muted-foreground text-sm">No reviews yet.</p>}
                            </div>
                        </Card>
                    </div>
                )}

                {tab === 'Compensation' && canViewCompensation && (
                    <Card className="p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Salary history</h2>
                            {canManageCompensation && <CompensationDialog employeeId={employee.id} />}
                        </div>
                        <div className="grid gap-2">
                            {(employee.compensation ?? []).map((c) => (
                                <div key={c.id} className="rounded border p-3 text-sm">
                                    <div className="flex justify-between">
                                        <span className="font-medium">
                                            {c.currency} {c.basic_salary.toLocaleString(undefined, { minimumFractionDigits: 2 })} basic
                                        </span>
                                        <span className="text-muted-foreground">from {fmtDate(c.effective_from)}</span>
                                    </div>
                                    {c.allowances.length > 0 && (
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            Allowances: {c.allowances.map((a) => `${a.name} ${a.amount.toLocaleString()}`).join(', ')}
                                        </div>
                                    )}
                                    {c.change_reason && <div className="text-muted-foreground mt-1 text-xs">{c.change_reason}</div>}
                                </div>
                            ))}
                            {(employee.compensation ?? []).length === 0 && (
                                <p className="text-muted-foreground text-sm">No compensation on file — payroll will skip this employee.</p>
                            )}
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
