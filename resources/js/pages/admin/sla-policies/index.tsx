import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

type SlaPolicy = {
    id: number;
    priority: 'critical' | 'high' | 'medium' | 'low';
    first_response_minutes: number;
    resolution_minutes: number;
    response_gap_minutes: number | null;
    business_hours_only: boolean;
    is_active: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'SLA policies', href: '/admin/sla-policies' }];

function SlaPolicyRow({ policy }: { policy: SlaPolicy }) {
    const { data, setData, patch, processing, errors, recentlySuccessful, transform } = useForm({
        first_response_minutes: policy.first_response_minutes,
        resolution_minutes: policy.resolution_minutes,
        response_gap_minutes: policy.response_gap_minutes?.toString() ?? '',
        business_hours_only: policy.business_hours_only,
        is_active: policy.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            response_gap_minutes: form.response_gap_minutes === '' ? null : Number(form.response_gap_minutes),
        }));
        patch(`/admin/sla-policies/${policy.id}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="border-sidebar-border/70 dark:border-sidebar-border space-y-4 rounded-xl border p-4">
            <h2 className="text-sm font-semibold capitalize">{policy.priority}</h2>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor={`first-response-${policy.id}`}>First response SLA (minutes)</Label>
                    <Input
                        id={`first-response-${policy.id}`}
                        type="number"
                        min={1}
                        value={data.first_response_minutes}
                        onChange={(e) => setData('first_response_minutes', Number(e.target.value))}
                    />
                    <InputError message={errors.first_response_minutes} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`resolution-${policy.id}`}>Resolution SLA (minutes)</Label>
                    <Input
                        id={`resolution-${policy.id}`}
                        type="number"
                        min={1}
                        value={data.resolution_minutes}
                        onChange={(e) => setData('resolution_minutes', Number(e.target.value))}
                    />
                    <InputError message={errors.resolution_minutes} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`gap-${policy.id}`}>Response-gap SLA (minutes)</Label>
                    <Input
                        id={`gap-${policy.id}`}
                        type="number"
                        min={1}
                        placeholder="No limit"
                        value={data.response_gap_minutes}
                        onChange={(e) => setData('response_gap_minutes', e.target.value)}
                    />
                    <p className="text-muted-foreground text-xs">
                        How long a ticket can wait on the requester before it's auto-closed for inactivity.
                    </p>
                    <InputError message={errors.response_gap_minutes} />
                </div>
            </div>
            <div className="flex items-center gap-6">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={data.business_hours_only}
                        onCheckedChange={(checked) => setData('business_hours_only', checked === true)}
                    />
                    Business hours only
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                    Active
                </label>
            </div>
            <div className="flex items-center gap-4">
                <Button type="submit" size="sm" disabled={processing}>
                    Save
                </Button>
                {recentlySuccessful && <span className="text-muted-foreground text-xs">Saved.</span>}
            </div>
        </form>
    );
}

export default function SlaPoliciesIndex({ policies }: { policies: SlaPolicy[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SLA policies" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">SLA policies</h1>
                    <p className="text-muted-foreground text-sm">
                        Thresholds used by the Service Desk to flag breaches and auto-close inactive tickets.
                    </p>
                </div>
                <div className="flex flex-col gap-4">
                    {policies.map((policy) => (
                        <SlaPolicyRow key={policy.id} policy={policy} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
