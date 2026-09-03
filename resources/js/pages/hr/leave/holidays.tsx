import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DateField } from '@/components/ui/date-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { fmtDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

type Holiday = { id: number; name: string; date: string; is_recurring: boolean };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave', href: '/hr/leave' },
    { title: 'Public holidays', href: '/hr/leave/holidays' },
];

export default function HolidaysPage({ holidays }: { holidays: Holiday[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', date: '', is_recurring: true as boolean });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/hr/leave/holidays', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Public holidays" />
            <div className="flex max-w-2xl flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Public holidays</h1>
                <p className="text-muted-foreground text-sm">Holidays are excluded from leave working-day counts.</p>

                <form onSubmit={submit} className="flex flex-wrap items-end gap-3 rounded-lg border p-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="h-name">Name</Label>
                        <Input id="h-name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="h-date">Date</Label>
                        <DateField id="h-date" value={data.date} onChange={(v) => setData('date', v)} />
                        <InputError message={errors.date} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={data.is_recurring} onCheckedChange={(v) => setData('is_recurring', v === true)} />
                        Recurs yearly
                    </label>
                    <Button type="submit" disabled={processing}>
                        Add
                    </Button>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <tbody>
                            {holidays.map((h) => (
                                <tr key={h.id} className="border-b last:border-0">
                                    <td className="px-3 py-2 font-medium">{h.name}</td>
                                    <td className="px-3 py-2">{fmtDate(h.date)}</td>
                                    <td className="px-3 py-2">{h.is_recurring && <Badge variant="outline">yearly</Badge>}</td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm('Remove this holiday?'))
                                                    router.delete(`/hr/leave/holidays/${h.id}`, { preserveScroll: true });
                                            }}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
