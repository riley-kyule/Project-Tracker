import { Button } from '@/components/ui/button';
import { cn, fmtDateTime } from '@/lib/utils';
import { Lock } from 'lucide-react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';

export type ThreadPerson = { id: number; name: string } | null;

export type ThreadReply = {
    id: number;
    body: string;
    user: ThreadPerson;
    created_at: string;
    edited_at?: string | null;
    is_internal?: boolean;
};

export type ThreadComment = ThreadReply & {
    gap_minutes?: number | null;
    replies: ThreadReply[];
};

function initials(name: string) {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase();
}

function isEditable(message: ThreadReply, currentUserId: number, windowMinutes: number) {
    if (!message.user || message.user.id !== currentUserId) return false;
    return Date.now() - new Date(message.created_at).getTime() < windowMinutes * 60000;
}

function Bubble({
    message,
    currentUserId,
    canModerate,
    editWindowMinutes,
    onReply,
    onEdit,
    onDelete,
    showName,
    meta,
}: {
    message: ThreadReply;
    currentUserId: number;
    canModerate: boolean;
    editWindowMinutes: number;
    onReply?: () => void;
    onEdit?: (id: number, body: string) => void | Promise<void>;
    onDelete?: (id: number) => void;
    showName: boolean;
    meta?: ReactNode;
}) {
    const mine = message.user?.id === currentUserId;
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(message.body);
    const [saving, setSaving] = useState(false);

    // Recompute the edit affordance on a timer so it disappears when the window lapses.
    const [, force] = useState(0);
    useEffect(() => {
        if (!onEdit || !mine) return;
        const id = window.setInterval(() => force((n) => n + 1), 20000);
        return () => window.clearInterval(id);
    }, [message.id, message.created_at, mine, onEdit]);

    const canEdit = Boolean(onEdit) && isEditable(message, currentUserId, editWindowMinutes);
    const canDelete = Boolean(onDelete) && (mine || canModerate);

    const save = async () => {
        const next = draft.trim();
        if (next === '' || next === message.body) {
            setEditing(false);
            setDraft(message.body);
            return;
        }
        setSaving(true);
        try {
            await onEdit?.(message.id, next);
            setEditing(false);
        } catch {
            // The caller surfaces the failure (toast); keep the editor open.
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className={cn('group flex flex-col gap-1', mine ? 'items-end' : 'items-start')}>
            {showName && <span className="text-muted-foreground px-1 text-[11px]">{mine ? 'You' : (message.user?.name ?? 'Deleted user')}</span>}
            <div className={cn('flex max-w-[85%] items-end gap-2', mine ? 'flex-row-reverse' : 'flex-row')}>
                {!mine && (
                    <span className="bg-muted text-muted-foreground mb-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold">
                        {message.user ? initials(message.user.name) : '?'}
                    </span>
                )}
                <div
                    className={cn(
                        'rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap',
                        message.is_internal
                            ? 'border border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100'
                            : mine
                              ? 'bg-brand-600 text-white'
                              : 'bg-muted text-foreground',
                    )}
                >
                    {editing ? (
                        <div className="flex w-64 flex-col gap-2">
                            <textarea
                                value={draft}
                                autoFocus
                                onChange={(e) => setDraft(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) save();
                                    if (e.key === 'Escape') {
                                        setEditing(false);
                                        setDraft(message.body);
                                    }
                                }}
                                rows={3}
                                className="text-foreground border-input bg-background focus-visible:ring-ring w-full rounded-md border px-2 py-1 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <div className="flex gap-2">
                                <Button type="button" size="sm" className="h-7" onClick={save} disabled={saving}>
                                    Save
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="h-7"
                                    onClick={() => {
                                        setEditing(false);
                                        setDraft(message.body);
                                    }}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <>
                            {message.is_internal && (
                                <span className="mb-1 flex items-center gap-1 text-[10px] font-semibold text-amber-700 uppercase dark:text-amber-400">
                                    <Lock className="size-3" /> Internal note
                                </span>
                            )}
                            {message.body}
                        </>
                    )}
                </div>
            </div>
            {!editing && (
                <div className={cn('flex items-center gap-2 px-1 text-[11px]', mine ? 'flex-row-reverse' : 'flex-row')}>
                    <span className="text-muted-foreground">{fmtDateTime(message.created_at)}</span>
                    {message.edited_at && <span className="text-muted-foreground italic">edited</span>}
                    {meta}
                    <span className="flex gap-2 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100">
                        {onReply && (
                            <button type="button" onClick={onReply} className="text-brand-600 dark:text-brand-400 hover:underline">
                                Reply
                            </button>
                        )}
                        {canEdit && (
                            <button
                                type="button"
                                onClick={() => {
                                    setDraft(message.body);
                                    setEditing(true);
                                }}
                                className="text-muted-foreground hover:text-foreground"
                            >
                                Edit
                            </button>
                        )}
                        {canDelete && (
                            <button type="button" onClick={() => onDelete?.(message.id)} className="text-muted-foreground hover:text-destructive">
                                Delete
                            </button>
                        )}
                    </span>
                </div>
            )}
        </div>
    );
}

export function CommentThread({
    comments,
    currentUserId,
    canModerate = false,
    editWindowMinutes = 10,
    nounSingular = 'comment',
    initialVisible = 5,
    onReply,
    onEdit,
    onDelete,
    renderMeta,
}: {
    comments: ThreadComment[];
    currentUserId: number;
    canModerate?: boolean;
    editWindowMinutes?: number;
    nounSingular?: string;
    initialVisible?: number;
    onReply?: (comment: ThreadComment) => void;
    onEdit?: (id: number, body: string) => void | Promise<void>;
    onDelete?: (id: number) => void;
    renderMeta?: (comment: ThreadComment) => ReactNode;
}) {
    const [expanded, setExpanded] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);
    const totalMessages = useMemo(() => comments.reduce((sum, c) => sum + 1 + c.replies.length, 0), [comments]);

    const prevTotal = useRef(0);
    const hiddenCount = expanded ? 0 : Math.max(0, comments.length - initialVisible);
    const visible = hiddenCount > 0 ? comments.slice(hiddenCount) : comments;

    // Stick to the bottom on first paint and whenever a new message arrives —
    // but not when the reader expands earlier history (they want to scroll up).
    useLayoutEffect(() => {
        const el = scrollRef.current;
        if (el && totalMessages !== prevTotal.current) el.scrollTop = el.scrollHeight;
        prevTotal.current = totalMessages;
    }, [totalMessages]);

    if (comments.length === 0) {
        return <p className="text-muted-foreground text-sm">No {nounSingular}s yet.</p>;
    }

    return (
        <div ref={scrollRef} className="max-h-[26rem] space-y-4 overflow-y-auto pr-1">
            {hiddenCount > 0 && (
                <button
                    type="button"
                    onClick={() => setExpanded(true)}
                    className="text-brand-600 dark:text-brand-400 mx-auto block rounded px-2 py-1 text-xs hover:underline"
                >
                    Show {hiddenCount} earlier {nounSingular}
                    {hiddenCount === 1 ? '' : 's'}
                </button>
            )}
            {visible.map((comment) => (
                <div key={comment.id} className="space-y-2">
                    <Bubble
                        message={comment}
                        currentUserId={currentUserId}
                        canModerate={canModerate}
                        editWindowMinutes={editWindowMinutes}
                        onReply={onReply ? () => onReply(comment) : undefined}
                        onEdit={onEdit}
                        onDelete={onDelete}
                        showName
                        meta={renderMeta?.(comment)}
                    />
                    {comment.replies.length > 0 && (
                        <div className="border-sidebar-border/70 dark:border-sidebar-border ml-4 space-y-3 border-l-2 pl-3">
                            {comment.replies.map((reply, index) => (
                                <Bubble
                                    key={reply.id}
                                    message={reply}
                                    currentUserId={currentUserId}
                                    canModerate={canModerate}
                                    editWindowMinutes={editWindowMinutes}
                                    onReply={onReply ? () => onReply(comment) : undefined}
                                    onEdit={onEdit}
                                    onDelete={onDelete}
                                    showName={index === 0 || reply.user?.id !== comment.replies[index - 1]?.user?.id}
                                />
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}
