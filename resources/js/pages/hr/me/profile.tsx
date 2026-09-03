import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';

type Ref = { id: number; name: string };

type Employee = {
    staff_number: string;
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
    job_title: string | null;
    employment_type: string;
    date_hired: string | null;
    contract_start_date: string | null;
    contract_end_date: string | null;
    employment_status: string;
    bank_name: string | null;
    bank_branch: string | null;
    bank_account_number: string | null;
    payment_method: string;
    department: Ref | null;
    manager: { name: string } | null;
    next_of_kin: { id: number; name: string; relationship: string | null; phone: string | null; is_primary: boolean }[];
    contracts: { id: number; title: string; employment_type: string; start_date: string; end_date: string | null; department: Ref | null }[];
    assets: { id: number; asset: { asset_tag: string; name: string } | null; assigned_at: string }[];
    documents: { id: number; name: string; category: string | null; created_at: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My HR', href: '/hr/me/profile' }];

function label(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function Field({ label: l, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <span className="text-muted-foreground text-xs">{l}</span>
            <span className="text-sm">{value ?? '—'}</span>
        </div>
    );
}

export default function MyProfile({ employee }: { employee: Employee }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My HR" />
            <div className="flex flex-col gap-4 p-4">
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
                    </div>
                    <p className="text-muted-foreground mt-2 text-xs">Something out of date? Contact HR to update your record.</p>
                </div>

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
                            <Field label="County" value={employee.county} />
                            <Field label="Physical address" value={employee.physical_address} />
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
                        </div>
                    </Card>
                    <Card className="p-4">
                        <h2 className="mb-3 text-sm font-semibold">Payment</h2>
                        <div className="grid grid-cols-2 gap-3">
                            <Field label="Method" value={label(employee.payment_method)} />
                            <Field label="Bank" value={employee.bank_name} />
                            <Field label="Branch" value={employee.bank_branch} />
                            <Field label="Account number" value={employee.bank_account_number} />
                        </div>
                    </Card>
                </div>

                <Card className="p-4">
                    <h2 className="mb-3 text-sm font-semibold">Next of kin</h2>
                    <div className="grid gap-2">
                        {employee.next_of_kin.map((k) => (
                            <div key={k.id} className="rounded border p-3 text-sm">
                                <span className="font-medium">{k.name}</span>
                                {k.is_primary && <Badge className="ml-2">Primary</Badge>}
                                <div className="text-muted-foreground">{[k.relationship, k.phone].filter(Boolean).join(' · ') || '—'}</div>
                            </div>
                        ))}
                        {employee.next_of_kin.length === 0 && <p className="text-muted-foreground text-sm">None recorded.</p>}
                    </div>
                </Card>

                <Card className="p-4">
                    <h2 className="mb-3 text-sm font-semibold">Contract history</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-muted-foreground text-left">
                                <tr>
                                    <th className="py-1 pr-3">Title</th>
                                    <th className="py-1 pr-3">Department</th>
                                    <th className="py-1 pr-3">Type</th>
                                    <th className="py-1 pr-3">Start</th>
                                    <th className="py-1 pr-3">End</th>
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
                                    </tr>
                                ))}
                                {employee.contracts.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground py-4 text-center">
                                            No contracts recorded.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="p-4">
                        <h2 className="mb-3 text-sm font-semibold">Assets in my care</h2>
                        <div className="grid gap-2">
                            {employee.assets.map((a) => (
                                <div key={a.id} className="rounded border p-3 text-sm">
                                    <span className="font-medium">{a.asset?.name ?? '—'}</span>
                                    <span className="text-muted-foreground ml-2">{a.asset?.asset_tag}</span>
                                    <div className="text-muted-foreground text-xs">Since {fmtDate(a.assigned_at)}</div>
                                </div>
                            ))}
                            {employee.assets.length === 0 && <p className="text-muted-foreground text-sm">None.</p>}
                        </div>
                    </Card>
                    <Card className="p-4">
                        <h2 className="mb-3 text-sm font-semibold">My documents</h2>
                        <div className="grid gap-2">
                            {employee.documents.map((d) => (
                                <div key={d.id} className="flex items-center justify-between rounded border p-3 text-sm">
                                    <div>
                                        <span className="font-medium">{d.name}</span>
                                        {d.category && (
                                            <Badge variant="outline" className="ml-2">
                                                {label(d.category)}
                                            </Badge>
                                        )}
                                    </div>
                                    <a href={`/hr/me/documents/${d.id}`}>
                                        <Button variant="ghost" size="sm">
                                            <Download className="h-4 w-4" />
                                        </Button>
                                    </a>
                                </div>
                            ))}
                            {employee.documents.length === 0 && <p className="text-muted-foreground text-sm">No documents.</p>}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
