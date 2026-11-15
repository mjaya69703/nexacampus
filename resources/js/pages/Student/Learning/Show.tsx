// Ruang belajar materi: deskripsi, lampiran, like/bookmark, dan diskusi
// (Inertia React). Pengganti komponen Livewire learning.show + komentar.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, Bookmark, BookmarkCheck, Check, Download, ExternalLink,
    FileText, Heart, MessageCircle, MessagesSquare, Paperclip, Pencil,
    Send, Trash2, X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Attachment = {
    id: number | null;
    fileName: string;
    fileType: string | null;
    sizeLabel: string;
    downloadCount: number;
    isLink: boolean;
    isPdf: boolean;
    embedUrl: string | null;
    downloadUrl: string | null;
    previewUrl: string | null;
    linkUrl: string | null;
    openLinkUrl: string | null;
};

type Material = {
    id: number;
    offeringId: number;
    courseCode: string;
    courseName: string;
    title: string;
    description: string | null;
    category: string | null;
    categoryLabel: string;
    meetingNumber: number | null;
    uploadedAt: string;
    uploadedBy: string;
    downloadCount: number;
    bookmarked: boolean;
    liked: boolean;
    likesCount: number;
};

type Reply = {
    id: number;
    content: string;
    userName: string;
    userRole: string;
    createdAt: string | null;
    editedAt: string | null;
    isEdited: boolean;
    likesCount: number;
    isLiked: boolean;
    canEdit: boolean;
};

type CommentRow = Reply & { replies: Reply[] };

type Comments = {
    items: CommentRow[];
    total: number;
    limit: number;
    hasMore: boolean;
};

type Props = {
    shell: ShellProps;
    material: Material;
    attachments: Attachment[];
    comments: Comments;
    urls: {
        index: string;
        offering: string;
        download: string;
        show: string;
        bookmark: string;
        like: string;
        commentStore: string;
        commentUpdate: string;
        commentDelete: string;
        commentLike: string;
    };
};

const withId = (url: string, id: number): string => url.replace('__ID__', String(id));

const roleLabel = (role: string): string => {
    const map: Record<string, string> = {
        student: 'Mahasiswa',
        lecturer: 'Dosen',
        admin: 'Admin',
        superuser: 'Superuser',
        'academic-leader': 'Pimpinan',
        alumni: 'Alumni',
    };
    return map[role] ?? role;
};

function CommentBody({
    comment,
    editing,
    editContent,
    editError,
    editProcessing,
    onEditChange,
    onStartEdit,
    onCancelEdit,
    onSaveEdit,
}: {
    comment: Reply;
    editing: boolean;
    editContent: string;
    editError?: string;
    editProcessing: boolean;
    onEditChange: (value: string) => void;
    onStartEdit: () => void;
    onCancelEdit: () => void;
    onSaveEdit: () => void;
}) {
    return (
        <>
            {editing ? (
                <div style={{ marginBottom: 8 }}>
                    <textarea
                        className="db-input"
                        rows={3}
                        value={editContent}
                        onChange={(event) => onEditChange(event.target.value)}
                    />
                    {editError && <div className="db-error" style={{ marginTop: 4 }}>{editError}</div>}
                    <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                        <button type="button" className="db-btn sm primary" disabled={editProcessing} onClick={onSaveEdit}>
                            <Check size={13} /> Simpan
                        </button>
                        <button type="button" className="db-btn sm ghost" onClick={onCancelEdit}>
                            <X size={13} /> Batal
                        </button>
                    </div>
                </div>
            ) : (
                <p style={{ margin: '0 0 10px', color: 'var(--db-ink)', lineHeight: 1.6, whiteSpace: 'pre-line' }}>
                    {comment.content}
                </p>
            )}
        </>
    );
}

export default function LearningShow({ shell, material, attachments, comments, urls }: Props) {
    const [replyingTo, setReplyingTo] = useState<number | null>(null);
    const [editingComment, setEditingComment] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Reply | null>(null);
    const [busyLike, setBusyLike] = useState(false);

    const commentForm = useForm({ content: '' });
    const replyForm = useForm({ content: '', parent_id: 0 });
    const editForm = useForm({ content: '' });

    const submitComment = (event: FormEvent): void => {
        event.preventDefault();

        commentForm.post(withId(urls.commentStore, material.id), {
            preserveScroll: true,
            onSuccess: () => commentForm.reset(),
        });
    };

    const submitReply = (event: FormEvent): void => {
        event.preventDefault();

        replyForm.post(withId(urls.commentStore, material.id), {
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset();
                setReplyingTo(null);
            },
        });
    };

    const startReply = (commentId: number): void => {
        setReplyingTo(commentId);
        replyForm.setData({ content: '', parent_id: commentId });
        replyForm.clearErrors();
    };

    const startEdit = (comment: Reply): void => {
        setEditingComment(comment.id);
        editForm.setData({ content: comment.content });
        editForm.clearErrors();
    };

    const saveEdit = (commentId: number): void => {
        editForm.patch(withId(urls.commentUpdate, commentId), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingComment(null);
                editForm.reset();
            },
        });
    };

    const confirmDelete = (): void => {
        if (!deleteTarget) return;

        router.delete(withId(urls.commentDelete, deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    };

    const toggleLike = (commentId: number): void => {
        router.post(withId(urls.commentLike, commentId), {}, { preserveScroll: true });
    };

    const loadMore = (): void => {
        router.get(withId(urls.show, material.id), { comments: comments.limit + 10 }, {
            preserveScroll: true,
            preserveState: true,
            only: ['comments'],
        });
    };

    const toggleMaterialLike = (): void => {
        router.post(withId(urls.like, material.id), {}, {
            preserveScroll: true,
            onStart: () => setBusyLike(true),
            onFinish: () => setBusyLike(false),
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${material.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MessagesSquare}
                        eyebrow="Learning"
                        title={material.title}
                        description={`${material.courseCode} — ${material.courseName}`}
                        badges={[
                            material.meetingNumber ? `Pertemuan ${material.meetingNumber}` : null,
                            material.categoryLabel,
                            `${attachments.length} lampiran`,
                        ].filter((badge): badge is string => badge !== null)}
                        actions={(
                            <div style={{ display: 'grid', gap: 8 }}>
                                <a className="db-btn light" href={withId(urls.offering, material.offeringId)}>
                                    <ArrowLeft size={15} /> Kembali ke materi
                                </a>
                                <button type="button" className="db-btn light" disabled={busyLike} onClick={toggleMaterialLike}>
                                    <Heart size={15} fill={material.liked ? 'currentColor' : 'none'} />
                                    {material.likesCount} {material.liked ? 'Disukai' : 'Suka'}
                                </button>
                                <button
                                    type="button"
                                    className="db-btn light"
                                    onClick={() => router.post(withId(urls.bookmark, material.id), {}, { preserveScroll: true })}
                                >
                                    {material.bookmarked ? <BookmarkCheck size={15} /> : <Bookmark size={15} />}
                                    {material.bookmarked ? 'Tersimpan' : 'Bookmark'}
                                </button>
                            </div>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Deskripsi</h2>
                            <span className="db-badge gray">Diunggah {material.uploadedAt} · {material.uploadedBy}</span>
                        </div>
                        <div className="db-card-body">
                            {material.description ? (
                                <div
                                    style={{ color: 'var(--db-ink)', lineHeight: 1.7 }}
                                    dangerouslySetInnerHTML={{ __html: material.description }}
                                />
                            ) : (
                                <div className="db-hint">Tidak ada deskripsi untuk materi ini.</div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Paperclip size={16} /> Lampiran</h2>
                            <span className="db-badge gray">{attachments.length} berkas</span>
                        </div>
                        <div className="db-card-body">
                            {attachments.length === 0 ? (
                                <EmptyState
                                    icon={Paperclip}
                                    title="Belum ada lampiran"
                                    description="Dosen belum menambahkan berkas untuk materi ini."
                                />
                            ) : (
                                <div style={{ display: 'grid', gap: 14 }}>
                                    {attachments.map((attachment) => (
                                        <article key={attachment.id ?? attachment.fileName} className="db-card" style={{ margin: 0 }}>
                                            <div className="db-card-body" style={{ display: 'flex', justifyContent: 'space-between', gap: 14, flexWrap: 'wrap', alignItems: 'center' }}>
                                                <div>
                                                    <div style={{ fontWeight: 600, color: 'var(--db-heading)', display: 'flex', alignItems: 'center', gap: 8 }}>
                                                        <Paperclip size={14} /> {attachment.fileName}
                                                    </div>
                                                    <div className="db-hint" style={{ marginTop: 4 }}>
                                                        {String(attachment.fileType ?? '-').toUpperCase()}
                                                        {!attachment.isLink && ` · ${attachment.sizeLabel}`}
                                                        {` · ${attachment.downloadCount}x diunduh`}
                                                    </div>
                                                </div>

                                                {attachment.isLink ? (
                                                    <a
                                                        className="db-btn sm primary"
                                                        href={attachment.openLinkUrl ?? attachment.linkUrl ?? '#'}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <ExternalLink size={14} /> Buka link
                                                    </a>
                                                ) : (
                                                    <a className="db-btn sm primary" href={attachment.downloadUrl ?? '#'}>
                                                        <Download size={14} /> Download
                                                    </a>
                                                )}
                                            </div>

                                            {attachment.embedUrl && (
                                                <div className="db-card-body" style={{ paddingTop: 0 }}>
                                                    <div style={{ position: 'relative', paddingTop: '56.25%', borderRadius: 12, overflow: 'hidden', border: '1px solid var(--db-line)' }}>
                                                        <iframe
                                                            src={attachment.embedUrl}
                                                            title={attachment.fileName}
                                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                            allowFullScreen
                                                            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', border: 0 }}
                                                        />
                                                    </div>
                                                </div>
                                            )}

                                            {attachment.isPdf && attachment.previewUrl && (
                                                <div className="db-card-body" style={{ paddingTop: 0 }}>
                                                    <iframe
                                                        src={attachment.previewUrl}
                                                        title={attachment.fileName}
                                                        loading="lazy"
                                                        style={{ width: '100%', height: 520, border: '1px solid var(--db-line)', borderRadius: 12 }}
                                                    />
                                                </div>
                                            )}
                                        </article>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><MessageCircle size={16} /> Diskusi Materi</h2>
                            <span className="db-badge gray">{comments.total} komentar</span>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submitComment} style={{ marginBottom: 20 }}>
                                <textarea
                                    className="db-input"
                                    rows={3}
                                    placeholder="Tulis pertanyaan atau komentar Anda di sini…"
                                    value={commentForm.data.content}
                                    onChange={(event) => commentForm.setData('content', event.target.value)}
                                />
                                {commentForm.errors.content && <div className="db-error" style={{ marginTop: 4 }}>{commentForm.errors.content}</div>}
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 8, gap: 10, flexWrap: 'wrap' }}>
                                    <span className="db-hint">Minimal 3 karakter. Hormati sesama mahasiswa dan dosen.</span>
                                    <button type="submit" className="db-btn primary" disabled={commentForm.processing}>
                                        <Send size={15} /> {commentForm.processing ? 'Mengirim…' : 'Kirim komentar'}
                                    </button>
                                </div>
                            </form>

                            {comments.items.length === 0 ? (
                                <EmptyState
                                    icon={MessagesSquare}
                                    title="Belum ada diskusi"
                                    description="Jadilah yang pertama bertanya tentang materi ini."
                                />
                            ) : (
                                <div style={{ display: 'grid', gap: 16 }}>
                                    {comments.items.map((comment) => (
                                        <article key={comment.id} className="db-card" style={{ margin: 0 }}>
                                            <div className="db-card-body">
                                                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
                                                    <div>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                                            <b style={{ color: 'var(--db-heading)' }}>{comment.userName}</b>
                                                            <span className="db-badge gray">{roleLabel(comment.userRole)}</span>
                                                            {comment.isEdited && <span className="db-badge gold">Diedit</span>}
                                                        </div>
                                                        <div className="db-hint" style={{ marginTop: 2 }}>
                                                            {comment.createdAt}
                                                            {comment.editedAt ? ` · diedit ${comment.editedAt}` : ''}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style={{ marginTop: 10 }}>
                                                    <CommentBody
                                                        comment={comment}
                                                        editing={editingComment === comment.id}
                                                        editContent={editForm.data.content}
                                                        editError={editForm.errors.content}
                                                        editProcessing={editForm.processing}
                                                        onEditChange={(value) => editForm.setData('content', value)}
                                                        onStartEdit={() => startEdit(comment)}
                                                        onCancelEdit={() => { setEditingComment(null); editForm.reset(); }}
                                                        onSaveEdit={() => saveEdit(comment.id)}
                                                    />
                                                </div>

                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                                                    <button
                                                        type="button"
                                                        className={`db-btn sm ${comment.isLiked ? 'primary' : 'ghost'}`}
                                                        onClick={() => toggleLike(comment.id)}
                                                    >
                                                        <Heart size={13} fill={comment.isLiked ? 'currentColor' : 'none'} /> {comment.likesCount}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        className="db-btn sm ghost"
                                                        onClick={() => startReply(comment.id)}
                                                    >
                                                        <MessageCircle size={13} /> Balas
                                                    </button>

                                                    {comment.canEdit && (
                                                        <>
                                                            <button type="button" className="db-btn sm ghost" onClick={() => startEdit(comment)}>
                                                                <Pencil size={13} /> Edit
                                                            </button>
                                                            <button type="button" className="db-btn sm danger" onClick={() => setDeleteTarget(comment)}>
                                                                <Trash2 size={13} /> Hapus
                                                            </button>
                                                        </>
                                                    )}
                                                </div>

                                                {replyingTo === comment.id && (
                                                    <form onSubmit={submitReply} style={{ marginTop: 12, paddingLeft: 16, borderLeft: '3px solid var(--db-brand)' }}>
                                                        <textarea
                                                            className="db-input"
                                                            rows={2}
                                                            placeholder="Tulis balasan Anda…"
                                                            value={replyForm.data.content}
                                                            onChange={(event) => replyForm.setData('content', event.target.value)}
                                                        />
                                                        {replyForm.errors.content && <div className="db-error" style={{ marginTop: 4 }}>{replyForm.errors.content}</div>}
                                                        <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                                                            <button type="submit" className="db-btn sm primary" disabled={replyForm.processing}>
                                                                <Send size={13} /> Kirim
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="db-btn sm ghost"
                                                                onClick={() => { setReplyingTo(null); replyForm.reset(); }}
                                                            >
                                                                Batal
                                                            </button>
                                                        </div>
                                                    </form>
                                                )}

                                                {comment.replies.length > 0 && (
                                                    <div style={{ marginTop: 14, display: 'grid', gap: 10, paddingLeft: 16, borderLeft: '3px solid var(--db-line)' }}>
                                                        {comment.replies.map((reply) => (
                                                            <div key={reply.id} style={{ padding: '10px 12px', background: 'var(--db-soft)', borderRadius: 10 }}>
                                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                                                    <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{reply.userName}</b>
                                                                    <span className="db-badge gray">{roleLabel(reply.userRole)}</span>
                                                                    {reply.isEdited && <span className="db-badge gold">Diedit</span>}
                                                                    <span className="db-hint">{reply.createdAt}</span>
                                                                </div>

                                                                <div style={{ marginTop: 8 }}>
                                                                    <CommentBody
                                                                        comment={reply}
                                                                        editing={editingComment === reply.id}
                                                                        editContent={editForm.data.content}
                                                                        editError={editForm.errors.content}
                                                                        editProcessing={editForm.processing}
                                                                        onEditChange={(value) => editForm.setData('content', value)}
                                                                        onStartEdit={() => startEdit(reply)}
                                                                        onCancelEdit={() => { setEditingComment(null); editForm.reset(); }}
                                                                        onSaveEdit={() => saveEdit(reply.id)}
                                                                    />
                                                                </div>

                                                                <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
                                                                    <button
                                                                        type="button"
                                                                        className={`db-btn sm ${reply.isLiked ? 'primary' : 'ghost'}`}
                                                                        onClick={() => toggleLike(reply.id)}
                                                                    >
                                                                        <Heart size={12} fill={reply.isLiked ? 'currentColor' : 'none'} /> {reply.likesCount}
                                                                    </button>
                                                                    {reply.canEdit && (
                                                                        <>
                                                                            <button type="button" className="db-btn sm ghost" onClick={() => startEdit(reply)}>
                                                                                <Pencil size={12} /> Edit
                                                                            </button>
                                                                            <button type="button" className="db-btn sm danger" onClick={() => setDeleteTarget(reply)}>
                                                                                <Trash2 size={12} /> Hapus
                                                                            </button>
                                                                        </>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            )}

                            {comments.hasMore && (
                                <div style={{ display: 'flex', justifyContent: 'center', marginTop: 16 }}>
                                    <button type="button" className="db-btn ghost" onClick={loadMore}>
                                        Muat {Math.min(10, comments.total - comments.limit)} komentar lagi
                                    </button>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>

            <ConfirmModal
                open={deleteTarget !== null}
                title="Hapus komentar?"
                message={deleteTarget
                    ? `Komentar “${deleteTarget.content.slice(0, 80)}${deleteTarget.content.length > 80 ? '…' : ''}” beserta seluruh balasannya akan dihapus permanen.`
                    : ''}
                confirmLabel="Ya, hapus"
                onConfirm={confirmDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </AdminShell>
    );
}
