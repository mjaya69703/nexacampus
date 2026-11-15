// Detail pengaduan mahasiswa + penanganan tiket oleh admin.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Clock3,
    Headset,
    Info,
    MessageSquare,
    Paperclip,
    Send,
    ShieldCheck,
    TriangleAlert,
    UserCheck,
    X,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField } from '../../../../components/Shared/Crud/fields';
import {
    DetailGrid,
    HistoryTimeline,
    PriorityBadge,
    StatusBadge,
} from '../../../../components/Shared/StudentServices/ServiceUI';
import { COMPLAINT_STATUS_LABELS, WorkflowModal } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Attachment = { id: number; name: string; size: number | null; url: string };

type Message = {
    id: number;
    senderType: string;
    sender: string;
    message: string;
    isInternal: boolean;
    createdAt: string | null;
    attachments: Attachment[];
};

type History = {
    id: number;
    from: string | null;
    to: string;
    notes: string | null;
    changedBy: string | null;
    changedAt: string | null;
};

type Complaint = {
    id: number;
    ticketNumber: string;
    subject: string;
    description: string;
    status: string;
    priority: string;
    student: string;
    nim: string;
    studyProgram: string;
    category: string;
    assignedWorkUnitId: number | null;
    assignedWorkUnit: string;
    assignedUserId: number | null;
    assignedUser: string;
    dueAt: string | null;
    slaLabel: string;
    slaTone: string;
    lastMessageAt: string | null;
    resolvedAt: string | null;
    closedAt: string | null;
    createdAt: string | null;
    messages: Message[];
    histories: History[];
};

type Props = {
    shell: ShellProps;
    can: { assign: boolean; respond: boolean; resolve: boolean };
    complaint: Complaint;
    workUnits: { id: number; name: string }[];
    assignableUsers: { id: number; name: string; email: string }[];
    statusActions: { id: string; name: string; tone: string }[];
    urls: {
        index: string;
        assign: string;
        claim: string;
        reply: string;
        setStatus: string;
    };
};

function senderLabel(senderType: string): string {
    if (senderType === 'student') return 'Mahasiswa';
    if (senderType === 'admin') return 'Petugas';
    return 'Sistem';
}

function formatFileSize(bytes: number | null): string {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const STATUS_HINTS: Record<string, string> = {
    submitted: 'Tiket baru masuk dan belum ditangani.',
    in_review: 'Tiket sedang ditangani petugas.',
    waiting_student: 'Petugas menunggu informasi tambahan dari mahasiswa.',
    responded: 'Petugas sudah membalas tiket ini.',
    resolved: 'Pengaduan sudah diselesaikan.',
    closed: 'Tiket sudah ditutup.',
    rejected: 'Tiket ditolak.',
    reopened: 'Tiket dibuka kembali dan perlu ditangani ulang.',
};

export default function ComplaintShow({
    shell,
    can,
    complaint,
    workUnits,
    assignableUsers,
    statusActions,
    urls,
}: Props) {
    const [assignOpen, setAssignOpen] = useState(false);
    const [claimOpen, setClaimOpen] = useState(false);
    const [statusTarget, setStatusTarget] = useState<{ id: string; name: string } | null>(null);

    const replyForm = useForm<{ message: string; attachments: File[] }>({
        message: '',
        attachments: [],
    });

    const assignForm = useForm<{ assigned_work_unit_id: string; assigned_user_id: string; notes: string }>({
        assigned_work_unit_id: String(complaint.assignedWorkUnitId ?? ''),
        assigned_user_id: String(complaint.assignedUserId ?? ''),
        notes: '',
    });

    const statusForm = useForm<{ status: string; notes: string }>({
        status: '',
        notes: '',
    });

    const hint = STATUS_HINTS[complaint.status] ?? null;

    const submitReply = (event: FormEvent) => {
        event.preventDefault();

        replyForm.post(urls.reply, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    const openAssign = () => {
        assignForm.setData({
            assigned_work_unit_id: String(complaint.assignedWorkUnitId ?? ''),
            assigned_user_id: String(complaint.assignedUserId ?? ''),
            notes: '',
        });
        assignForm.clearErrors();
        setAssignOpen(true);
    };

    const submitAssign = () => {
        assignForm.post(urls.assign, {
            preserveScroll: true,
            onSuccess: () => setAssignOpen(false),
        });
    };

    const claimTicket = () => {
        router.post(urls.claim, {}, {
            preserveScroll: true,
            onSuccess: () => setClaimOpen(false),
        });
    };

    const openStatus = (status: { id: string; name: string }) => {
        statusForm.setData({ status: status.id, notes: '' });
        statusForm.clearErrors();
        setStatusTarget(status);
    };

    const submitStatus = () => {
        statusForm.post(urls.setStatus, {
            preserveScroll: true,
            onSuccess: () => setStatusTarget(null),
        });
    };

    const removeAttachment = (index: number) => {
        replyForm.setData('attachments', replyForm.data.attachments.filter((_, i) => i !== index));
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${complaint.ticketNumber} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Headset}
                        eyebrow="Layanan Mahasiswa · Pengaduan"
                        title={complaint.ticketNumber}
                        description={complaint.subject}
                        badges={[COMPLAINT_STATUS_LABELS[complaint.status] ?? complaint.status]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>}
                    />

                    {hint && (
                        <div className={`db-note ${complaint.status === 'waiting_student' ? 'warning' : ['resolved', 'closed'].includes(complaint.status) ? 'success' : 'info'}`}>
                            <Info size={15} />
                            <span>{hint}</span>
                        </div>
                    )}

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><MessageSquare size={16} /> Percakapan</h2>
                                    <span className="db-badge gray">{complaint.messages.length} pesan</span>
                                </div>
                                <div className="db-card-body">
                                    {complaint.messages.length === 0 ? (
                                        <p className="db-hint">Belum ada pesan pada pengaduan ini.</p>
                                    ) : (
                                        <div className="db-messages">
                                            {complaint.messages.map((message) => (
                                                <article key={message.id} className={`db-message ${message.senderType !== 'student' ? 'staff' : ''}`.trim()}>
                                                    <div className="db-message-head">
                                                        <b>
                                                            {message.sender} · {senderLabel(message.senderType)}
                                                            {message.isInternal && <span className="db-badge gray" style={{ marginLeft: 8 }}>Catatan Internal</span>}
                                                        </b>
                                                        <small>{message.createdAt ?? '-'}</small>
                                                    </div>
                                                    <div className="db-message-body">{message.message}</div>
                                                    {message.attachments.length > 0 && (
                                                        <div className="db-message-files">
                                                            {message.attachments.map((attachment) => (
                                                                <a key={attachment.id} href={attachment.url} target="_blank" rel="noreferrer" className="db-btn ghost sm">
                                                                    <Paperclip size={12} /> {attachment.name}
                                                                    {attachment.size ? ` · ${formatFileSize(attachment.size)}` : ''}
                                                                </a>
                                                            ))}
                                                        </div>
                                                    )}
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {can.respond && complaint.status !== 'closed' && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Send size={16} /> Balas Pengaduan</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <form onSubmit={submitReply} className="db-form">
                                            <div>
                                                <label className="db-label">Balasan <span>*</span></label>
                                                <textarea
                                                    className="db-input"
                                                    rows={4}
                                                    value={replyForm.data.message}
                                                    onChange={(event) => replyForm.setData('message', event.target.value)}
                                                    placeholder="Tulis tanggapan atau informasi untuk mahasiswa…"
                                                />
                                                {replyForm.errors.message && <div className="db-note danger" style={{ marginTop: 8 }}>{replyForm.errors.message}</div>}
                                            </div>
                                            <div>
                                                <label className="db-label">Lampiran (opsional, maks. 6 berkas)</label>
                                                <input
                                                    type="file"
                                                    className="db-input"
                                                    multiple
                                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                                    onChange={(event) => replyForm.setData('attachments', Array.from(event.target.files ?? []))}
                                                />
                                                {replyForm.data.attachments.length > 0 && (
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                                        {replyForm.data.attachments.map((file, index) => (
                                                            <span key={`${file.name}-${index}`} className="db-badge gray" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                                <Paperclip size={11} /> {file.name}
                                                                <button type="button" onClick={() => removeAttachment(index)} aria-label={`Hapus ${file.name}`} style={{ border: 0, background: 'transparent', padding: 0, cursor: 'pointer', color: 'inherit' }}>
                                                                    <X size={11} />
                                                                </button>
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                                {replyForm.errors.attachments && <div className="db-error">{replyForm.errors.attachments}</div>}
                                                <p className="db-hint" style={{ marginTop: 6 }}>
                                                    Format: JPG, PNG, WEBP, PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT. Maksimal 5 MB per berkas.
                                                </p>
                                            </div>
                                            <div className="db-form-actions">
                                                <button type="submit" className="db-btn primary" disabled={replyForm.processing}>
                                                    <Send size={14} /> {replyForm.processing ? 'Mengirim…' : 'Kirim Balasan'}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Riwayat Status</h2>
                                </div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={complaint.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Detail Pengaduan</h2>
                                    <span className={`db-badge ${complaint.slaTone}`}>{complaint.slaLabel}</span>
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Status', value: <StatusBadge status={complaint.status} label={COMPLAINT_STATUS_LABELS[complaint.status]} /> },
                                            { label: 'Prioritas', value: <PriorityBadge priority={complaint.priority} /> },
                                            { label: 'Kategori', value: complaint.category },
                                            { label: 'Mahasiswa', value: complaint.student },
                                            { label: 'NIM', value: complaint.nim },
                                            { label: 'Program Studi', value: complaint.studyProgram },
                                            { label: 'Unit Penanganan', value: complaint.assignedWorkUnit },
                                            { label: 'Petugas', value: complaint.assignedUser },
                                            { label: 'Diajukan', value: complaint.createdAt ?? '-' },
                                            { label: 'Target SLA', value: complaint.dueAt ?? '-' },
                                            { label: 'Aktivitas Terakhir', value: complaint.lastMessageAt ?? '-' },
                                            { label: 'Diselesaikan', value: complaint.resolvedAt ?? '-' },
                                            { label: 'Ditutup', value: complaint.closedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserCheck size={16} /> Penanganan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {can.assign && (
                                        <>
                                            <button className="db-btn ghost" type="button" onClick={openAssign}>
                                                <UserCheck size={14} /> Ubah Penugasan
                                            </button>
                                            {complaint.assignedUserId !== null && (
                                                <button className="db-btn ghost" type="button" onClick={() => setClaimOpen(true)}>
                                                    <CheckCircle2 size={14} /> Ambil Alih Tiket
                                                </button>
                                            )}
                                        </>
                                    )}

                                    {can.resolve && (
                                        <>
                                            <span className="db-label" style={{ marginTop: 6 }}>Ubah Status</span>
                                            {statusActions.map((status) => (
                                                <button
                                                    key={status.id}
                                                    className={`db-btn ${status.id === 'rejected' ? 'danger' : status.id === 'resolved' ? 'primary' : 'ghost'}`}
                                                    type="button"
                                                    onClick={() => openStatus(status)}
                                                >
                                                    {status.id === 'resolved' ? <CheckCircle2 size={14} /> : status.id === 'rejected' ? <X size={14} /> : status.id === 'closed' ? <ShieldCheck size={14} /> : <Clock3 size={14} />}
                                                    {status.name}
                                                </button>
                                            ))}
                                        </>
                                    )}

                                    {!can.assign && !can.resolve && (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada tindakan penanganan yang tersedia.</p>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Deskripsi Awal</h2>
                                </div>
                                <div className="db-card-body">
                                    <p className="db-message-body" style={{ marginTop: 0 }}>{complaint.description}</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <WorkflowModal
                    open={assignOpen}
                    title="Ubah Penugasan Tiket"
                    message="Tentukan unit kerja dan petugas yang bertanggung jawab menangani pengaduan ini."
                    confirmLabel="Simpan Penugasan"
                    processing={assignForm.processing}
                    onConfirm={submitAssign}
                    onCancel={() => setAssignOpen(false)}
                >
                    <SelectField
                        label="Unit kerja"
                        value={assignForm.data.assigned_work_unit_id}
                        onChange={(event) => assignForm.setData('assigned_work_unit_id', event.target.value)}
                        error={assignForm.errors.assigned_work_unit_id}
                    >
                        <option value="">Belum ditentukan</option>
                        {workUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>{unit.name}</option>
                        ))}
                    </SelectField>
                    <SelectField
                        label="Petugas"
                        value={assignForm.data.assigned_user_id}
                        onChange={(event) => assignForm.setData('assigned_user_id', event.target.value)}
                        error={assignForm.errors.assigned_user_id}
                    >
                        <option value="">Belum ditentukan</option>
                        {assignableUsers.map((user) => (
                            <option key={user.id} value={user.id}>{user.name} · {user.email}</option>
                        ))}
                    </SelectField>
                    <TextareaField
                        label="Catatan penugasan"
                        rows={3}
                        placeholder="Opsional…"
                        value={assignForm.data.notes}
                        onChange={(event) => assignForm.setData('notes', event.target.value)}
                        error={assignForm.errors.notes}
                    />
                </WorkflowModal>

                <WorkflowModal
                    open={claimOpen}
                    title="Ambil Alih Tiket"
                    message={`Tiket ${complaint.ticketNumber} akan ditugaskan kepada kamu sebagai petugas penanggung jawab.`}
                    confirmLabel="Ya, ambil tiket"
                    processing={false}
                    onConfirm={claimTicket}
                    onCancel={() => setClaimOpen(false)}
                />

                <WorkflowModal
                    open={statusTarget !== null}
                    title={statusTarget?.name ?? 'Ubah Status'}
                    message="Status pengaduan akan diperbarui dan mahasiswa dapat melihat perubahannya."
                    confirmLabel="Simpan Status"
                    danger={statusTarget?.id === 'rejected'}
                    processing={statusForm.processing}
                    onConfirm={submitStatus}
                    onCancel={() => setStatusTarget(null)}
                >
                    <TextareaField
                        label="Catatan perubahan status"
                        rows={3}
                        placeholder="Opsional. Jelaskan alasan atau tindak lanjut…"
                        value={statusForm.data.notes}
                        onChange={(event) => statusForm.setData('notes', event.target.value)}
                        error={statusForm.errors.notes}
                    />
                </WorkflowModal>
            </div>
        </AdminShell>
    );
}
