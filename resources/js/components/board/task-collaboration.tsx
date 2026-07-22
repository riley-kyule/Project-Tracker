import { type Member } from '@/components/board/task-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Download, ExternalLink, Lock, Paperclip, ShieldAlert, Trash2, Users, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

type Reply = {
    id: number;
    body: string;
    user: Member;
    created_at: string;
};

type CommentNode = Reply & { replies: Reply[] };

type ChecklistItemNode = {
    id: number;
    title: string;
    is_completed: boolean;
};

type ChecklistNode = {
    id: number;
    name: string;
    items: ChecklistItemNode[];
};

type AttachmentNode = {
    id: number;
    original_name: string;
    size_bytes: number;
    uploader: Member;
    created_at: string;
};

type LinkNode = {
    id: number;
    url: string;
    label: string | null;
    creator: Member;
};

type ActivityNode = {
    id: number;
    event: string;
    actor: Member | null;
    created_at: string;
};

type TaskRef = { id: number; title: string; task_number: number; completed_at?: string | null };

type DependencyNode = {
    id: number;
    overridden_at: string | null;
    override_reason: string | null;
    predecessor: TaskRef;
};

type BlockingNode = {
    id: number;
    successor: TaskRef;
};

type RelationNode = {
    id: number;
    task: TaskRef;
};

type RecurrenceRuleNode = {
    id: number;
    frequency: string;
    interval_value: number;
    next_run_at: string | null;
    is_active: boolean;
    template_task_id: number;
};

type TimeEntryNode = {
    id: number;
    user: Member;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number;
    source: 'timer' | 'manual' | 'imported';
    work_location: string;
    adjustment_status: 'pending' | 'approved' | 'rejected' | null;
    adjustment_reason: string | null;
    approved_by: Member | null;
};

type ApprovalNode = {
    status: 'pending' | 'approved' | 'rejected' | null;
    approver: Member | null;
    note: string | null;
};

type AssigneeNode = Member & { assignment_type: 'assignee' | 'collaborator' | 'reviewer' | 'watcher' };

type Detail = {
    canDelete: boolean;
    comments: CommentNode[];
    checklists: ChecklistNode[];
    canEditChecklist: boolean;
    links: LinkNode[];
    canEditLinks: boolean;
    project: { id: number; name: string } | null;
    projectOptions: { id: number; name: string }[];
    attachments: AttachmentNode[];
    dependencies: DependencyNode[];
    blocking: BlockingNode[];
    relations: RelationNode[];
    recurrenceRule: RecurrenceRuleNode | null;
    canManageRecurrence: boolean;
    timeEntries: TimeEntryNode[];
    canApproveTime: boolean;
    estimatedMinutes: number | null;
    actualMinutes: number;
    approval: ApprovalNode;
    canReviewApproval: boolean;
    assignees: AssigneeNode[];
    confidentiality: 'normal' | 'restricted' | 'confidential';
    confidentialGrants: Member[];
    canManageConfidentiality: boolean;
    activity: ActivityNode[];
};

const emptyDetail: Detail = {
    canDelete: false,
    comments: [],
    checklists: [],
    canEditChecklist: false,
    links: [],
    canEditLinks: false,
    project: null,
    projectOptions: [],
    attachments: [],
    dependencies: [],
    blocking: [],
    relations: [],
    recurrenceRule: null,
    canManageRecurrence: false,
    timeEntries: [],
    canApproveTime: false,
    estimatedMinutes: null,
    actualMinutes: 0,
    approval: { status: null, approver: null, note: null },
    canReviewApproval: false,
    assignees: [],
    confidentiality: 'normal',
    confidentialGrants: [],
    canManageConfidentiality: false,
    activity: [],
};

const confidentialityLabels: Record<Detail['confidentiality'], string> = {
    normal: 'Normal',
    restricted: 'Restricted',
    confidential: 'Confidential',
};

function formatDuration(totalSeconds: number) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
}

function formatMinutes(totalMinutes: number) {
    return formatDuration(totalMinutes * 60);
}

const NO_DEPENDENCY = 'none';

const frequencyLabels: Record<string, string> = {
    daily: 'Daily',
    weekly: 'Weekly',
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    custom: 'Custom',
    after_completion: 'Repeat after completion',
};

function formatBytes(bytes: number) {
    if (bytes >= 1_048_576) return `${(bytes / 1_048_576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
}

export function TaskCollaboration({
    taskId,
    members,
    allMembers,
    boardTasks,
    onDeleted,
}: {
    taskId: number;
    members: Member[];
    allMembers: Member[];
    boardTasks: { id: number; title: string; task_number: number }[];
    onDeleted: () => void;
}) {
    const { auth } = usePage<SharedData>().props;
    const [detail, setDetail] = useState<Detail>(emptyDetail);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
    const [commentBody, setCommentBody] = useState('');
    const [replyTo, setReplyTo] = useState<CommentNode | null>(null);
    const [mentionIds, setMentionIds] = useState<number[]>([]);
    const [showMentions, setShowMentions] = useState(false);
    const [checklistName, setChecklistName] = useState('');
    const [newLinkUrl, setNewLinkUrl] = useState('');
    const [newLinkLabel, setNewLinkLabel] = useState('');
    const [itemTitles, setItemTitles] = useState<Record<number, string>>({});
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [editingItemTitle, setEditingItemTitle] = useState('');
    const [newDependencyId, setNewDependencyId] = useState(NO_DEPENDENCY);
    const [newRelationId, setNewRelationId] = useState(NO_DEPENDENCY);
    const [newFrequency, setNewFrequency] = useState('weekly');
    const [newInterval, setNewInterval] = useState(1);
    const [showManualEntry, setShowManualEntry] = useState(false);
    const [manualMinutes, setManualMinutes] = useState(30);
    const [manualLocation, setManualLocation] = useState('unspecified');
    const [manualReason, setManualReason] = useState('');
    const [reviewerId, setReviewerId] = useState(NO_DEPENDENCY);
    const [newGranteeId, setNewGranteeId] = useState(NO_DEPENDENCY);
    const [newCollaboratorId, setNewCollaboratorId] = useState(NO_DEPENDENCY);
    const [newCollaboratorType, setNewCollaboratorType] = useState<'collaborator' | 'reviewer' | 'watcher'>('collaborator');
    const [showRejectForm, setShowRejectForm] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const fileInput = useRef<HTMLInputElement>(null);

    const reload = useCallback(() => {
        fetch(`/tasks/${taskId}/detail`, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error(`Request failed: ${response.status}`);
                return response.json();
            })
            .then((data) => {
                setDetail(data);
                setStatus('ready');
            })
            .catch(() => setStatus('error'));
    }, [taskId]);

    useEffect(reload, [reload]);

    const showError = (errors: Record<string, string>) => {
        toast.error(Object.values(errors)[0] ?? 'That action failed.');
    };

    const post = (url: string, data: Parameters<typeof router.post>[1], done?: () => void) => {
        router.post(url, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reload();
                done?.();
            },
            onError: showError,
        });
    };

    const destroy = (url: string) => {
        router.delete(url, { preserveScroll: true, preserveState: true, onSuccess: reload, onError: showError });
    };

    const submitComment = (e: React.FormEvent) => {
        e.preventDefault();
        if (commentBody.trim() === '') return;
        post(`/tasks/${taskId}/comments`, { body: commentBody, parent_id: replyTo?.id ?? null, mention_ids: mentionIds }, () => {
            setCommentBody('');
            setReplyTo(null);
            setMentionIds([]);
            setShowMentions(false);
        });
    };

    if (status === 'loading') {
        return (
            <div className="space-y-6">
                <section>
                    <Skeleton className="mb-2 h-4 w-24" />
                    <Skeleton className="h-20 rounded-lg" />
                </section>
                <section className="space-y-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-12 rounded-lg" />
                    <Skeleton className="h-12 rounded-lg" />
                </section>
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div className="border-destructive/30 bg-destructive/5 rounded-lg border p-4 text-sm">
                <p className="text-destructive font-medium">Couldn't load this task's details.</p>
                <button type="button" onClick={reload} className="text-brand-600 dark:text-brand-400 mt-1 hover:underline">
                    Try again
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Checklists */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Checklists</h3>
                {detail.checklists.map((checklist) => {
                    const done = checklist.items.filter((item) => item.is_completed).length;
                    return (
                        <div key={checklist.id} className="border-sidebar-border/70 dark:border-sidebar-border mb-3 rounded-lg border p-3">
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-sm font-medium">{checklist.name}</span>
                                <span className="text-muted-foreground text-xs">
                                    {done}/{checklist.items.length}
                                    <button
                                        type="button"
                                        aria-label={`Delete ${checklist.name}`}
                                        disabled={!detail.canEditChecklist}
                                        onClick={() => destroy(`/checklists/${checklist.id}`)}
                                        className="text-muted-foreground hover:text-destructive ml-2 disabled:pointer-events-none disabled:opacity-40"
                                    >
                                        <Trash2 className="inline size-3.5" />
                                    </button>
                                </span>
                            </div>
                            <ul className="space-y-1.5">
                                {checklist.items.map((item) => (
                                    <li key={item.id} className="group flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={item.is_completed}
                                            disabled={!detail.canEditChecklist}
                                            onCheckedChange={(checked) =>
                                                router.patch(
                                                    `/checklist-items/${item.id}`,
                                                    { is_completed: checked === true },
                                                    { preserveScroll: true, preserveState: true, onSuccess: reload, onError: showError },
                                                )
                                            }
                                        />
                                        {editingItemId === item.id ? (
                                            <form
                                                className="flex-1"
                                                onSubmit={(e) => {
                                                    e.preventDefault();
                                                    const title = editingItemTitle.trim();
                                                    if (!title) return;
                                                    router.patch(
                                                        `/checklist-items/${item.id}`,
                                                        { title },
                                                        {
                                                            preserveScroll: true,
                                                            preserveState: true,
                                                            onSuccess: () => {
                                                                setEditingItemId(null);
                                                                reload();
                                                            },
                                                            onError: showError,
                                                        },
                                                    );
                                                }}
                                            >
                                                <Input
                                                    autoFocus
                                                    value={editingItemTitle}
                                                    onChange={(e) => setEditingItemTitle(e.target.value)}
                                                    onBlur={(e) => e.currentTarget.form?.requestSubmit()}
                                                    className="h-7 text-sm"
                                                />
                                            </form>
                                        ) : (
                                            <span
                                                className={`flex-1 ${item.is_completed ? 'text-muted-foreground line-through' : ''} ${detail.canEditChecklist ? 'cursor-text' : ''}`}
                                                onClick={() => {
                                                    if (!detail.canEditChecklist) return;
                                                    setEditingItemId(item.id);
                                                    setEditingItemTitle(item.title);
                                                }}
                                            >
                                                {item.title}
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            aria-label={`Delete ${item.title}`}
                                            disabled={!detail.canEditChecklist}
                                            onClick={() => destroy(`/checklist-items/${item.id}`)}
                                            className="text-muted-foreground hover:text-destructive opacity-0 group-hover:opacity-100 disabled:pointer-events-none disabled:opacity-0"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    const title = itemTitles[checklist.id]?.trim();
                                    if (!title) return;
                                    post(`/checklists/${checklist.id}/items`, { title }, () =>
                                        setItemTitles((current) => ({ ...current, [checklist.id]: '' })),
                                    );
                                }}
                                className="mt-2"
                            >
                                <Input
                                    placeholder="Add item…"
                                    value={itemTitles[checklist.id] ?? ''}
                                    onChange={(e) => setItemTitles((current) => ({ ...current, [checklist.id]: e.target.value }))}
                                    disabled={!detail.canEditChecklist}
                                    className="h-8 text-sm"
                                />
                            </form>
                        </div>
                    );
                })}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (checklistName.trim() === '') return;
                        post(`/tasks/${taskId}/checklists`, { name: checklistName }, () => setChecklistName(''));
                    }}
                    className="flex gap-2"
                >
                    <Input
                        placeholder="New checklist name…"
                        value={checklistName}
                        onChange={(e) => setChecklistName(e.target.value)}
                        disabled={!detail.canEditChecklist}
                        className="h-8 text-sm"
                    />
                    <Button type="submit" size="sm" variant="secondary" disabled={!detail.canEditChecklist}>
                        Add
                    </Button>
                </form>
            </section>

            {/* People */}
            <section>
                <h3 className="mb-1 flex items-center gap-1.5 text-sm font-semibold">
                    <Users className="size-4" /> People
                </h3>
                <p className="text-muted-foreground mb-2 text-xs">
                    Anyone can be added, including people outside this board's department — adding them here is what gives them access to this
                    task specifically.
                </p>
                <ul className="mb-2 space-y-1.5">
                    {detail.assignees
                        .filter((person) => person.assignment_type !== 'assignee')
                        .map((person) => (
                            <li key={person.id} className="flex items-center gap-2 text-sm">
                                {person.name}
                                <span className="text-muted-foreground text-xs capitalize">{person.assignment_type}</span>
                                <button
                                    type="button"
                                    aria-label={`Remove ${person.name}`}
                                    onClick={() => destroy(`/tasks/${taskId}/assignees/${person.id}`)}
                                    className="text-muted-foreground hover:text-destructive ml-auto"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </li>
                        ))}
                    {detail.assignees.filter((person) => person.assignment_type !== 'assignee').length === 0 && (
                        <li className="text-muted-foreground text-sm">No collaborators, reviewers, or watchers yet.</li>
                    )}
                </ul>
                <div className="flex flex-wrap gap-2">
                    <Select value={newCollaboratorId} onValueChange={setNewCollaboratorId}>
                        <SelectTrigger className="h-8 flex-1 text-sm">
                            <SelectValue placeholder="Add a person…" />
                        </SelectTrigger>
                        <SelectContent>
                            {allMembers
                                .filter((member) => !detail.assignees.some((person) => person.id === member.id))
                                .map((member) => (
                                    <SelectItem key={member.id} value={member.id.toString()}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <Select value={newCollaboratorType} onValueChange={(value) => setNewCollaboratorType(value as typeof newCollaboratorType)}>
                        <SelectTrigger className="h-8 w-32 text-sm">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="collaborator">Collaborator</SelectItem>
                            <SelectItem value="reviewer">Reviewer</SelectItem>
                            <SelectItem value="watcher">Watcher</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        disabled={newCollaboratorId === NO_DEPENDENCY}
                        onClick={() =>
                            post(`/tasks/${taskId}/assignees`, { user_id: Number(newCollaboratorId), assignment_type: newCollaboratorType }, () =>
                                setNewCollaboratorId(NO_DEPENDENCY),
                            )
                        }
                    >
                        Add
                    </Button>
                </div>
            </section>

            {/* Confidentiality */}
            {(detail.confidentiality !== 'normal' || detail.canManageConfidentiality) && (
                <section>
                    <h3 className="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                        <ShieldAlert className="size-4" /> Confidentiality
                    </h3>
                    {detail.canManageConfidentiality ? (
                        <Select
                            value={detail.confidentiality}
                            onValueChange={(value) =>
                                router.patch(
                                    `/tasks/${taskId}`,
                                    { confidentiality: value },
                                    { preserveScroll: true, preserveState: true, onSuccess: reload, onError: showError },
                                )
                            }
                        >
                            <SelectTrigger className="h-8 w-44 text-sm">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="normal">Normal</SelectItem>
                                <SelectItem value="restricted">Restricted</SelectItem>
                                <SelectItem value="confidential">Confidential</SelectItem>
                            </SelectContent>
                        </Select>
                    ) : (
                        <p className="text-sm">{confidentialityLabels[detail.confidentiality]}</p>
                    )}
                    {detail.confidentiality !== 'normal' && detail.canManageConfidentiality && (
                        <div className="mt-2">
                            <p className="text-muted-foreground mb-1 text-xs">
                                Only CEO, Administrators, and department managers listed below can see this task.
                            </p>
                            <ul className="mb-2 space-y-1">
                                {detail.confidentialGrants.map((grantee) => (
                                    <li key={grantee.id} className="flex items-center gap-2 text-sm">
                                        {grantee.name}
                                        <button
                                            type="button"
                                            aria-label={`Remove ${grantee.name}'s access`}
                                            onClick={() => destroy(`/tasks/${taskId}/confidential-grants/${grantee.id}`)}
                                            className="text-muted-foreground hover:text-destructive ml-auto"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    </li>
                                ))}
                                {detail.confidentialGrants.length === 0 && (
                                    <li className="text-muted-foreground text-sm">No one else has been granted access.</li>
                                )}
                            </ul>
                            <div className="flex gap-2">
                                <Select value={newGranteeId} onValueChange={setNewGranteeId}>
                                    <SelectTrigger className="h-8 flex-1 text-sm">
                                        <SelectValue placeholder="Grant access to…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {members
                                            .filter((member) => !detail.confidentialGrants.some((grantee) => grantee.id === member.id))
                                            .map((member) => (
                                                <SelectItem key={member.id} value={member.id.toString()}>
                                                    {member.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    disabled={newGranteeId === NO_DEPENDENCY}
                                    onClick={() =>
                                        post(`/tasks/${taskId}/confidential-grants`, { user_id: Number(newGranteeId) }, () =>
                                            setNewGranteeId(NO_DEPENDENCY),
                                        )
                                    }
                                >
                                    Grant
                                </Button>
                            </div>
                        </div>
                    )}
                </section>
            )}

            {/* Dependencies */}
            <section>
                <h3 className="mb-2 flex items-center gap-1.5 text-sm font-semibold">
                    <Lock className="size-4" /> Dependencies
                </h3>
                <ul className="space-y-1.5">
                    {detail.dependencies.map((dependency) => {
                        const resolved = dependency.predecessor.completed_at !== null && dependency.predecessor.completed_at !== undefined;
                        return (
                            <li key={dependency.id} className="flex items-center gap-2 text-sm">
                                <span className={resolved ? 'text-muted-foreground line-through' : ''}>
                                    T-{dependency.predecessor.task_number} {dependency.predecessor.title}
                                </span>
                                {!resolved && dependency.overridden_at && (
                                    <span className="text-xs text-amber-600 dark:text-amber-400" title={dependency.override_reason ?? ''}>
                                        overridden
                                    </span>
                                )}
                                <button
                                    type="button"
                                    aria-label="Remove dependency"
                                    onClick={() => destroy(`/task-dependencies/${dependency.id}`)}
                                    className="text-muted-foreground hover:text-destructive ml-auto"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </li>
                        );
                    })}
                    {detail.dependencies.length === 0 && <li className="text-muted-foreground text-sm">No prerequisites.</li>}
                </ul>
                {detail.blocking.length > 0 && (
                    <p className="text-muted-foreground mt-2 text-xs">
                        Blocks: {detail.blocking.map((b) => `T-${b.successor.task_number} ${b.successor.title}`).join(', ')}
                    </p>
                )}
                <div className="mt-2 flex gap-2">
                    <Select value={newDependencyId} onValueChange={setNewDependencyId}>
                        <SelectTrigger className="h-8 flex-1 text-sm">
                            <SelectValue placeholder="Add a prerequisite task…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_DEPENDENCY}>Choose a task…</SelectItem>
                            {boardTasks
                                .filter((candidate) => candidate.id !== taskId && !detail.dependencies.some((d) => d.predecessor.id === candidate.id))
                                .map((candidate) => (
                                    <SelectItem key={candidate.id} value={candidate.id.toString()}>
                                        T-{candidate.task_number} {candidate.title}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        disabled={newDependencyId === NO_DEPENDENCY}
                        onClick={() =>
                            post(`/tasks/${taskId}/dependencies`, { predecessor_task_id: Number(newDependencyId) }, () =>
                                setNewDependencyId(NO_DEPENDENCY),
                            )
                        }
                    >
                        Add
                    </Button>
                </div>
            </section>

            {/* Related tasks */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Related tasks</h3>
                <ul className="space-y-1.5">
                    {detail.relations.map((relation) => (
                        <li key={relation.id} className="flex items-center gap-2 text-sm">
                            <span>
                                T-{relation.task.task_number} {relation.task.title}
                            </span>
                            <button
                                type="button"
                                aria-label="Remove relation"
                                onClick={() => destroy(`/task-relations/${relation.id}`)}
                                className="text-muted-foreground hover:text-destructive ml-auto"
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                    {detail.relations.length === 0 && <li className="text-muted-foreground text-sm">No related tasks.</li>}
                </ul>
                <div className="mt-2 flex gap-2">
                    <Select value={newRelationId} onValueChange={setNewRelationId}>
                        <SelectTrigger className="h-8 flex-1 text-sm">
                            <SelectValue placeholder="Link a related task…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_DEPENDENCY}>Choose a task…</SelectItem>
                            {boardTasks
                                .filter((candidate) => candidate.id !== taskId && !detail.relations.some((r) => r.task.id === candidate.id))
                                .map((candidate) => (
                                    <SelectItem key={candidate.id} value={candidate.id.toString()}>
                                        T-{candidate.task_number} {candidate.title}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        disabled={newRelationId === NO_DEPENDENCY}
                        onClick={() =>
                            post(`/tasks/${taskId}/relations`, { related_task_id: Number(newRelationId) }, () => setNewRelationId(NO_DEPENDENCY))
                        }
                    >
                        Add
                    </Button>
                </div>
            </section>

            {/* Project */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Project</h3>
                {detail.project ? (
                    <div className="flex items-center gap-2 text-sm">
                        <Link href={`/projects/${detail.project.id}`} className="text-brand-600 dark:text-brand-400 hover:underline">
                            {detail.project.name}
                        </Link>
                        {detail.canEditLinks && (
                            <button
                                type="button"
                                aria-label="Unlink project"
                                onClick={() =>
                                    router.patch(
                                        `/tasks/${taskId}`,
                                        { project_id: null },
                                        { preserveScroll: true, preserveState: true, onSuccess: reload, onError: showError },
                                    )
                                }
                                className="text-muted-foreground hover:text-destructive ml-auto"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">Not linked to a project.</p>
                )}
                {detail.canEditLinks && (
                    <div className="mt-2 flex gap-2">
                        <Select
                            value={NO_DEPENDENCY}
                            onValueChange={(value) =>
                                router.patch(
                                    `/tasks/${taskId}`,
                                    { project_id: Number(value) },
                                    { preserveScroll: true, preserveState: true, onSuccess: reload, onError: showError },
                                )
                            }
                        >
                            <SelectTrigger className="h-8 flex-1 text-sm">
                                <SelectValue placeholder={detail.project ? 'Change project…' : 'Link a project…'} />
                            </SelectTrigger>
                            <SelectContent>
                                {detail.projectOptions
                                    .filter((project) => project.id !== detail.project?.id)
                                    .map((project) => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
            </section>

            {/* Recurrence */}
            {(detail.recurrenceRule || detail.canManageRecurrence) && (
                <section>
                    <h3 className="mb-2 text-sm font-semibold">Recurrence</h3>
                    {detail.recurrenceRule ? (
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span>
                                {frequencyLabels[detail.recurrenceRule.frequency] ?? detail.recurrenceRule.frequency}
                                {detail.recurrenceRule.interval_value > 1 && ` (every ${detail.recurrenceRule.interval_value})`}
                            </span>
                            {!detail.recurrenceRule.is_active && <span className="text-muted-foreground text-xs">stopped</span>}
                            {detail.recurrenceRule.is_active && detail.recurrenceRule.next_run_at && (
                                <span className="text-muted-foreground text-xs">
                                    next: {new Date(detail.recurrenceRule.next_run_at).toLocaleDateString()}
                                </span>
                            )}
                            {detail.canManageRecurrence && detail.recurrenceRule.is_active && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="ml-auto"
                                    onClick={() => post(`/recurrence-rules/${detail.recurrenceRule?.id}`, { is_active: false })}
                                >
                                    Stop recurring
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={newFrequency} onValueChange={setNewFrequency}>
                                <SelectTrigger className="h-8 w-44 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(frequencyLabels).map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {newFrequency !== 'after_completion' && (
                                <Input
                                    type="number"
                                    min={1}
                                    max={365}
                                    value={newInterval}
                                    onChange={(e) => setNewInterval(Number(e.target.value))}
                                    className="h-8 w-20 text-sm"
                                />
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={() => post(`/tasks/${taskId}/recurrence`, { frequency: newFrequency, interval_value: newInterval })}
                            >
                                Make recurring
                            </Button>
                        </div>
                    )}
                </section>
            )}

            {/* Approval */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Approval</h3>
                {detail.approval.status === null && (
                    <div className="flex flex-wrap gap-2">
                        <Select value={reviewerId} onValueChange={setReviewerId}>
                            <SelectTrigger className="h-8 w-48 text-sm">
                                <SelectValue placeholder="Choose a reviewer…" />
                            </SelectTrigger>
                            <SelectContent>
                                {members.map((member) => (
                                    <SelectItem key={member.id} value={member.id.toString()}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            disabled={reviewerId === NO_DEPENDENCY}
                            onClick={() =>
                                post(`/tasks/${taskId}/request-approval`, { reviewer_id: Number(reviewerId) }, () => setReviewerId(NO_DEPENDENCY))
                            }
                        >
                            Request approval
                        </Button>
                    </div>
                )}
                {detail.approval.status === 'pending' && (
                    <div>
                        <p className="text-sm">
                            Awaiting approval from <span className="font-medium">{detail.approval.approver?.name}</span>
                        </p>
                        {detail.canReviewApproval && (
                            <div className="mt-2 flex flex-wrap items-center gap-2">
                                <Button type="button" size="sm" onClick={() => post(`/tasks/${taskId}/approve-review`, {})}>
                                    Approve
                                </Button>
                                <Button type="button" size="sm" variant="destructive" onClick={() => setShowRejectForm((current) => !current)}>
                                    Send back
                                </Button>
                            </div>
                        )}
                        {showRejectForm && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    post(`/tasks/${taskId}/reject-review`, { reason: rejectReason }, () => {
                                        setRejectReason('');
                                        setShowRejectForm(false);
                                    });
                                }}
                                className="mt-2 flex gap-2"
                            >
                                <Input
                                    placeholder="What needs to change?"
                                    value={rejectReason}
                                    onChange={(e) => setRejectReason(e.target.value)}
                                    required
                                    className="h-8 flex-1 text-sm"
                                />
                                <Button type="submit" size="sm" variant="destructive">
                                    Confirm
                                </Button>
                            </form>
                        )}
                    </div>
                )}
                {detail.approval.status === 'approved' && (
                    <p className="text-sm text-emerald-600 dark:text-emerald-400">Approved by {detail.approval.approver?.name}.</p>
                )}
                {detail.approval.status === 'rejected' && (
                    <div>
                        <p className="text-destructive text-sm">
                            Sent back by {detail.approval.approver?.name}: {detail.approval.note}
                        </p>
                        <Select value={reviewerId} onValueChange={setReviewerId}>
                            <SelectTrigger className="mt-2 h-8 w-48 text-sm">
                                <SelectValue placeholder="Re-request from…" />
                            </SelectTrigger>
                            <SelectContent>
                                {members.map((member) => (
                                    <SelectItem key={member.id} value={member.id.toString()}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="mt-2 ml-2"
                            disabled={reviewerId === NO_DEPENDENCY}
                            onClick={() =>
                                post(`/tasks/${taskId}/request-approval`, { reviewer_id: Number(reviewerId) }, () => setReviewerId(NO_DEPENDENCY))
                            }
                        >
                            Re-request approval
                        </Button>
                    </div>
                )}
            </section>

            {/* Time tracking */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Time tracking</h3>
                <p className="text-muted-foreground mb-2 text-sm">
                    {formatMinutes(detail.actualMinutes)} logged
                    {detail.estimatedMinutes !== null && ` of ${formatMinutes(detail.estimatedMinutes)} estimated`}
                </p>
                <ul className="mb-2 space-y-1.5">
                    {detail.timeEntries.map((entry) => (
                        <li key={entry.id} className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="font-medium">{entry.user.name}</span>
                            <span>{formatDuration(entry.duration_seconds)}</span>
                            {entry.source === 'manual' && (
                                <span
                                    className={`text-xs ${
                                        entry.adjustment_status === 'approved'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : entry.adjustment_status === 'rejected'
                                              ? 'text-destructive'
                                              : 'text-amber-600 dark:text-amber-400'
                                    }`}
                                >
                                    manual · {entry.adjustment_status}
                                </span>
                            )}
                            {entry.source === 'manual' && entry.adjustment_status === 'pending' && detail.canApproveTime && (
                                <span className="ml-auto flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => post(`/time-entries/${entry.id}/approve`, {})}
                                        className="text-xs text-emerald-600 hover:underline dark:text-emerald-400"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => post(`/time-entries/${entry.id}/reject`, {})}
                                        className="text-destructive text-xs hover:underline"
                                    >
                                        Reject
                                    </button>
                                </span>
                            )}
                        </li>
                    ))}
                    {detail.timeEntries.length === 0 && <li className="text-muted-foreground text-sm">No time logged yet.</li>}
                </ul>
                {(() => {
                    const runningEntry = detail.timeEntries.find((entry) => entry.user.id === auth.user.id && entry.ended_at === null);
                    return runningEntry ? (
                        <Button type="button" size="sm" variant="destructive" onClick={() => post(`/time-entries/${runningEntry.id}/stop`, {})}>
                            Stop timer
                        </Button>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" size="sm" variant="secondary" onClick={() => post(`/tasks/${taskId}/time-entries/start`, {})}>
                                Start timer
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => setShowManualEntry((current) => !current)}>
                                Log time manually
                            </Button>
                        </div>
                    );
                })()}
                {showManualEntry && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(
                                `/tasks/${taskId}/time-entries`,
                                { duration_minutes: manualMinutes, work_location: manualLocation, adjustment_reason: manualReason },
                                () => {
                                    setManualReason('');
                                    setShowManualEntry(false);
                                },
                            );
                        }}
                        className="border-sidebar-border/70 dark:border-sidebar-border mt-2 space-y-2 rounded-lg border p-3"
                    >
                        <div className="flex gap-2">
                            <Input
                                type="number"
                                min={1}
                                max={1440}
                                value={manualMinutes}
                                onChange={(e) => setManualMinutes(Number(e.target.value))}
                                className="h-8 w-24 text-sm"
                            />
                            <Select value={manualLocation} onValueChange={setManualLocation}>
                                <SelectTrigger className="h-8 flex-1 text-sm">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unspecified">Unspecified</SelectItem>
                                    <SelectItem value="remote">Remote</SelectItem>
                                    <SelectItem value="office">Office</SelectItem>
                                    <SelectItem value="onsite">Onsite</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Input
                            placeholder="Reason for manual entry…"
                            value={manualReason}
                            onChange={(e) => setManualReason(e.target.value)}
                            required
                            className="h-8 text-sm"
                        />
                        <Button type="submit" size="sm">
                            Submit for approval
                        </Button>
                    </form>
                )}
            </section>

            {/* Attachments */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Attachments</h3>
                <ul className="space-y-1.5">
                    {detail.attachments.map((attachment) => (
                        <li key={attachment.id} className="flex items-center gap-2 text-sm">
                            <Paperclip className="text-muted-foreground size-3.5 shrink-0" />
                            <a href={`/attachments/${attachment.id}`} className="text-brand-600 dark:text-brand-400 truncate hover:underline">
                                {attachment.original_name}
                            </a>
                            <span className="text-muted-foreground text-xs">{formatBytes(attachment.size_bytes)}</span>
                            {(attachment.uploader.id === auth.user.id || auth.roles.includes('Administrator')) && (
                                <button
                                    type="button"
                                    aria-label={`Delete ${attachment.original_name}`}
                                    onClick={() => destroy(`/attachments/${attachment.id}`)}
                                    className="text-muted-foreground hover:text-destructive ml-auto"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
                <input
                    ref={fileInput}
                    type="file"
                    className="hidden"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (!file) return;
                        router.post(
                            `/tasks/${taskId}/attachments`,
                            { file },
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                preserveState: true,
                                onSuccess: reload,
                                onError: showError,
                                onFinish: () => {
                                    if (fileInput.current) fileInput.current.value = '';
                                },
                            },
                        );
                    }}
                />
                <Button type="button" size="sm" variant="secondary" className="mt-2" onClick={() => fileInput.current?.click()}>
                    <Download className="mr-1 size-4 rotate-180" /> Upload file
                </Button>
            </section>

            {/* Links */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Links</h3>
                <ul className="space-y-1.5">
                    {detail.links.map((link) => (
                        <li key={link.id} className="flex items-center gap-2 text-sm">
                            <ExternalLink className="text-muted-foreground size-3.5 shrink-0" />
                            <a
                                href={link.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-brand-600 dark:text-brand-400 truncate hover:underline"
                            >
                                {link.label || link.url}
                            </a>
                            <span className="text-muted-foreground text-xs">{link.creator.name}</span>
                            {detail.canEditLinks && (
                                <button
                                    type="button"
                                    aria-label={`Delete ${link.label || link.url}`}
                                    onClick={() => destroy(`/task-links/${link.id}`)}
                                    className="text-muted-foreground hover:text-destructive ml-auto"
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </li>
                    ))}
                    {detail.links.length === 0 && <li className="text-muted-foreground text-sm">No links yet.</li>}
                </ul>
                {detail.canEditLinks && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const url = newLinkUrl.trim();
                            if (!url) return;
                            post(`/tasks/${taskId}/links`, { url, label: newLinkLabel.trim() || null }, () => {
                                setNewLinkUrl('');
                                setNewLinkLabel('');
                            });
                        }}
                        className="mt-2 flex gap-2"
                    >
                        <Input
                            placeholder="https://…"
                            value={newLinkUrl}
                            onChange={(e) => setNewLinkUrl(e.target.value)}
                            className="h-8 flex-1 text-sm"
                        />
                        <Input
                            placeholder="Label (optional)"
                            value={newLinkLabel}
                            onChange={(e) => setNewLinkLabel(e.target.value)}
                            className="h-8 flex-1 text-sm"
                        />
                        <Button type="submit" size="sm" variant="secondary">
                            Add
                        </Button>
                    </form>
                )}
            </section>

            {/* Comments */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Comments</h3>
                <ul className="space-y-3">
                    {detail.comments.map((comment) => (
                        <li key={comment.id} className="border-sidebar-border/70 dark:border-sidebar-border rounded-lg border p-3">
                            <div className="mb-1 flex items-center gap-2 text-xs">
                                <span className="font-semibold">{comment.user.name}</span>
                                <span className="text-muted-foreground">{new Date(comment.created_at).toLocaleString()}</span>
                                <button
                                    type="button"
                                    onClick={() => setReplyTo(comment)}
                                    className="text-brand-600 dark:text-brand-400 ml-auto hover:underline"
                                >
                                    Reply
                                </button>
                                {(comment.user.id === auth.user.id || auth.roles.includes('Administrator')) && (
                                    <button
                                        type="button"
                                        aria-label="Delete comment"
                                        onClick={() => destroy(`/comments/${comment.id}`)}
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                )}
                            </div>
                            <p className="text-sm whitespace-pre-wrap">{comment.body}</p>
                            {comment.replies.length > 0 && (
                                <ul className="border-sidebar-border/70 dark:border-sidebar-border mt-2 space-y-2 border-l-2 pl-3">
                                    {comment.replies.map((reply) => (
                                        <li key={reply.id}>
                                            <div className="flex items-center gap-2 text-xs">
                                                <span className="font-semibold">{reply.user.name}</span>
                                                <span className="text-muted-foreground">{new Date(reply.created_at).toLocaleString()}</span>
                                            </div>
                                            <p className="text-sm whitespace-pre-wrap">{reply.body}</p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                    {detail.comments.length === 0 && <li className="text-muted-foreground text-sm">No comments yet.</li>}
                </ul>
                <form onSubmit={submitComment} className="mt-3 space-y-2">
                    {replyTo && (
                        <div className="text-muted-foreground flex items-center gap-1 text-xs">
                            Replying to {replyTo.user.name}
                            <button type="button" aria-label="Cancel reply" onClick={() => setReplyTo(null)}>
                                <X className="size-3.5" />
                            </button>
                        </div>
                    )}
                    <textarea
                        value={commentBody}
                        onChange={(e) => setCommentBody(e.target.value)}
                        rows={2}
                        placeholder="Write a comment…"
                        className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                    />
                    <div className="flex items-center gap-2">
                        <Button type="submit" size="sm">
                            {replyTo ? 'Reply' : 'Comment'}
                        </Button>
                        <button
                            type="button"
                            onClick={() => setShowMentions((current) => !current)}
                            className="text-brand-600 dark:text-brand-400 text-xs hover:underline"
                        >
                            @ Mention {mentionIds.length > 0 && `(${mentionIds.length})`}
                        </button>
                    </div>
                    {showMentions && (
                        <div className="border-sidebar-border/70 dark:border-sidebar-border flex max-h-32 flex-wrap gap-3 overflow-y-auto rounded-md border p-2">
                            {members
                                .filter((member) => member.id !== auth.user.id)
                                .map((member) => (
                                    <label key={member.id} className="flex items-center gap-1.5 text-xs">
                                        <Checkbox
                                            checked={mentionIds.includes(member.id)}
                                            onCheckedChange={(checked) =>
                                                setMentionIds((current) =>
                                                    checked === true ? [...current, member.id] : current.filter((id) => id !== member.id),
                                                )
                                            }
                                        />
                                        {member.name}
                                    </label>
                                ))}
                        </div>
                    )}
                </form>
            </section>

            {/* Activity */}
            <section>
                <h3 className="mb-2 text-sm font-semibold">Activity</h3>
                <ul className="space-y-1.5">
                    {detail.activity.map((entry) => (
                        <li key={entry.id} className="text-muted-foreground text-xs">
                            <span className="text-foreground font-medium">{entry.actor?.name ?? 'System'}</span> {entry.event}
                            {' · '}
                            {new Date(entry.created_at).toLocaleString()}
                        </li>
                    ))}
                    {detail.activity.length === 0 && <li className="text-muted-foreground text-xs">No activity recorded yet.</li>}
                </ul>
            </section>

            {/* Danger zone */}
            {detail.canDelete && (
                <section className="border-destructive/30 rounded-lg border p-3">
                    <h3 className="text-destructive mb-2 text-sm font-semibold">Danger zone</h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        onClick={() => {
                            if (!confirm('Delete this task permanently? This cannot be undone.')) return;
                            router.delete(`/tasks/${taskId}`, { onSuccess: onDeleted, onError: showError });
                        }}
                    >
                        <Trash2 className="mr-1 size-3.5" /> Delete task
                    </Button>
                </section>
            )}
        </div>
    );
}
