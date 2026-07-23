import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notification settings', href: '/settings/notifications' }];

const GROUPS: { title: string; types: [string, string][] }[] = [
    {
        title: 'Tasks',
        types: [
            ['task_assigned', 'A task is assigned to you'],
            ['task_commented', 'A comment is added to a task you’re watching'],
            ['comment_mention', 'You’re @mentioned in a comment'],
            ['task_due_soon', 'A task you own is due within 24 hours'],
            ['task_overdue', 'A task you own is overdue'],
            ['task_blocked', 'A task you own moves into a Blocked column'],
            ['task_collaborator_added', 'You’re added to a task as a collaborator, reviewer, or watcher'],
            ['task_approval_requested', 'You’re asked to review a task'],
            ['task_approval_decided', 'A task you requested approval on is decided'],
            ['recurrence_missed', 'A recurring task is generated late'],
            ['task_completed_ceo', 'Any task across the company is completed (CEO only)'],
            ['task_completed_department', 'A task in your department is completed (department heads only)'],
        ],
    },
    {
        title: 'Service desk',
        types: [
            ['ticket_submitted', 'Confirmation that your ticket was submitted'],
            ['ticket_assigned', 'A ticket is assigned to you'],
            ['ticket_updated', 'A ticket you submitted changes status'],
            ['ticket_overdue', 'A ticket assigned to you is past its SLA'],
            ['ticket_response_overdue', 'A ticket assigned to you is past its first-response SLA'],
            ['ticket_response', 'A new response is posted on a ticket you’re part of'],
            ['ticket_closed_inactivity', 'A ticket is closed automatically after no reply'],
        ],
    },
    {
        title: 'Analytics',
        types: [['analytics_source_stale', 'A marketing analytics source (GA4, GSC, Ahrefs) goes stale or fails']],
    },
];

export default function NotificationSettings({ preferences }: { preferences: Record<string, boolean> }) {
    const { data, setData, patch, processing, recentlySuccessful } = useForm({ preferences });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch('/settings/notifications', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Notifications" description="Choose which in-app notifications you want to receive" />

                    <form onSubmit={submit} className="space-y-6">
                        {GROUPS.map((group) => (
                            <div key={group.title} className="space-y-3">
                                <h3 className="text-sm font-semibold">{group.title}</h3>
                                <div className="space-y-2">
                                    {group.types.map(([type, description]) => (
                                        <label key={type} className="flex items-start gap-2 text-sm">
                                            <Checkbox
                                                checked={data.preferences[type] ?? true}
                                                onCheckedChange={(checked) =>
                                                    setData('preferences', { ...data.preferences, [type]: checked === true })
                                                }
                                            />
                                            <Label className="font-normal">{description}</Label>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ))}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save preferences</Button>
                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
