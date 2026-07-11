import { type Member } from '@/components/board/task-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Download, Paperclip, Trash2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

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

type ActivityNode = {
    id: number;
    event: string;
    actor: Member | null;
    created_at: string;
};

type Detail = {
    comments: CommentNode[];
    checklists: ChecklistNode[];
    attachments: AttachmentNode[];
    activity: ActivityNode[];
};

const emptyDetail: Detail = { comments: [], checklists: [], attachments: [], activity: [] };

function formatBytes(bytes: number) {
    if (bytes >= 1_048_576) return `${(bytes / 1_048_576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
}

export function TaskCollaboration({ taskId, members }: { taskId: number; members: Member[] }) {
    const { auth } = usePage<SharedData>().props;
    const [detail, setDetail] = useState<Detail>(emptyDetail);
    const [commentBody, setCommentBody] = useState('');
    const [replyTo, setReplyTo] = useState<CommentNode | null>(null);
    const [mentionIds, setMentionIds] = useState<number[]>([]);
    const [showMentions, setShowMentions] = useState(false);
    const [checklistName, setChecklistName] = useState('');
    const [itemTitles, setItemTitles] = useState<Record<number, string>>({});
    const fileInput = useRef<HTMLInputElement>(null);

    const reload = useCallback(() => {
        fetch(`/tasks/${taskId}/detail`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : emptyDetail))
            .then(setDetail)
            .catch(() => setDetail(emptyDetail));
    }, [taskId]);

    useEffect(reload, [reload]);

    const post = (url: string, data: Parameters<typeof router.post>[1], done?: () => void) => {
        router.post(url, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                reload();
                done?.();
            },
        });
    };

    const destroy = (url: string) => {
        router.delete(url, { preserveScroll: true, preserveState: true, onSuccess: reload });
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
                                        onClick={() => destroy(`/checklists/${checklist.id}`)}
                                        className="text-muted-foreground hover:text-destructive ml-2"
                                    >
                                        <Trash2 className="inline size-3.5" />
                                    </button>
                                </span>
                            </div>
                            <ul className="space-y-1.5">
                                {checklist.items.map((item) => (
                                    <li key={item.id} className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={item.is_completed}
                                            onCheckedChange={(checked) =>
                                                router.patch(
                                                    `/checklist-items/${item.id}`,
                                                    { is_completed: checked === true },
                                                    { preserveScroll: true, preserveState: true, onSuccess: reload },
                                                )
                                            }
                                        />
                                        <span className={item.is_completed ? 'text-muted-foreground line-through' : ''}>{item.title}</span>
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
                        className="h-8 text-sm"
                    />
                    <Button type="submit" size="sm" variant="secondary">
                        Add
                    </Button>
                </form>
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
        </div>
    );
}
