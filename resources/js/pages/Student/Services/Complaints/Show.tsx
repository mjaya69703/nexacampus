import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Headset, Info, MessageSquare, Paperclip, Send, ShieldCheck, TriangleAlert, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { createPortal } from 'react-dom';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { DetailGrid, HistoryTimeline, PriorityBadge, StatusBadge } from '../ServiceUI';
import { senderLabel, statusLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Att = { id: number; fileName: string; url: string };
type Msg = { id: number; senderType: string; senderName: string; message: string; createdAt: string | null; attachments: Att[] };
type Hist = { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null };
type Complaint = {
    id: number;
    ticketNumber: string;
    subject: string;
    description: string;
    priority: string;
    priorityLabel: string;
    status: string;
    statusLabel: string;
    category: string;
    assignedUnit: string | null;
    submittedAt: string | null;
    updatedAt: string | null;
    dueAt: string | null;
    messages: Msg[];
    histories: Hist[];
};

type Props = {
    shell: ShellProps;
    complaint: Complaint;
    canReply: boolean;
    canClose: boolean;
    urls: { index: string; reply: string; close: string; attachmentDownload: string };
};

const statusHint = (status: string): string | null => {
    switch (status) {
        case 'submitted':
            return 'Pengaduan sudah masuk dan akan diteruskan ke unit terkait.';
        case 'in_review':
            return 'Pengaduan sedang direview oleh petugas.';
        case 'waiting_student':
            return 'Petugas menunggu informasi tambahan dari kamu. Silakan balas thread ini.';
        case 'responded':
            return 'Petugas sudah membalas. Kamu bisa menanggapi; tiket dapat ditutup setelah status pengaduan selesai.';
        case 'resolved':
            return 'Pengaduan sudah diselesaikan. Kamu bisa menutup tiket ini jika tidak ada kendala lain.';
        case 'closed':
            return 'Tiket pengaduan sudah ditutup.';
        case 'rejected':
            return 'Pengaduan ditolak. Baca riwayat dan balasan petugas untuk detailnya.';
        case 'reopened':
            return 'Pengaduan dibuka kembali dan sedang ditangani petugas.';
        default:
            return null;
    }
};

export default function ComplaintsShow({ shell, complaint, canReply, canClose, urls }: Props) {
    const reply = useForm<{ replyMessage: string; replyAttachments: File[] }>({ replyMessage: '', replyAttachments: [] });
    const [confirmClose, setConfirmClose] = useState(false);
    const hint = statusHint(complaint.status);

    const submitReply = (event: FormEvent) => {
        event.preventDefault();
        reply.post(urls.reply, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reply.reset(),
        });
    };

    const closeTicket = () => {
        router.post(urls.close, {}, {
            preserveScroll: true,
            onSuccess: () => setConfirmClose(false),
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Headset}
                        eyebrow="Student Services · Pengaduan"
                        title={complaint.ticketNumber}
                        description={complaint.subject}
                        badges={[statusLabel(complaint.status)]}
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {hint && (
                        <div className={`db-note ${complaint.status === 'waiting_student' ? 'warning' : complaint.status === 'closed' || complaint.status === 'resolved' ? 'success' : 'info'}`}>
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
                                                        <b>{message.senderName} · {senderLabel(message.senderType)}</b>
                                                        <small>{message.createdAt ?? '-'}</small>
                                                    </div>
                                                    <div className="db-message-body">{message.message}</div>
                                                    {message.attachments.length > 0 && (
                                                        <div className="db-message-files">
                                                            {message.attachments.map((attachment) => (
                                                                <a key={attachment.id} href={attachment.url} target="_blank" rel="noreferrer" className="db-btn ghost sm">
                                                                    <Paperclip size={12} /> {attachment.fileName}
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

                            {canReply && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><Send size={16} /> Balas Pengaduan</h2></div>
                                    <div className="db-card-body">
                                        <form onSubmit={submitReply} className="db-form">
                                            <div>
                                                <label className="db-label">Balasan <span>*</span></label>
                                                <textarea
                                                    className="db-input"
                                                    rows={4}
                                                    value={reply.data.replyMessage}
                                                    onChange={(event) => reply.setData('replyMessage', event.target.value)}
                                                    placeholder="Tulis informasi tambahan atau tanggapan untuk petugas…"
                                                />
                                                {reply.errors.replyMessage && <div className="db-note danger" style={{ marginTop: 8 }}>{reply.errors.replyMessage}</div>}
                                            </div>
                                            <div>
                                                <label className="db-label">Lampiran (opsional, maks. 6 berkas)</label>
                                                <input
                                                    type="file"
                                                    className="db-input"
                                                    multiple
                                                    accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                                    onChange={(event) => reply.setData('replyAttachments', Array.from(event.target.files ?? []))}
                                                />
                                                <p className="db-hint" style={{ marginTop: 6 }}>
                                                    Format: JPG, PNG, WEBP, PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT.
                                                </p>
                                            </div>
                                            <div className="db-form-actions">
                                                <button type="submit" className="db-btn primary" disabled={reply.processing}>
                                                    <Send size={14} /> {reply.processing ? 'Mengirim…' : 'Kirim Balasan'}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </section>
                            )}

                            {canClose && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><ShieldCheck size={16} /> Selesaikan Pengaduan</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                        <p className="db-hint">Jika kendala sudah selesai, tutup tiket ini. Tiket yang ditutup tidak bisa dibalas lagi.</p>
                                        <div>
                                            <button type="button" className="db-btn danger" onClick={() => setConfirmClose(true)}>
                                                <X size={14} /> Tutup Pengaduan
                                            </button>
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Riwayat Status</h2></div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={complaint.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Detail Pengaduan</h2></div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Status', value: <StatusBadge status={complaint.status} label={complaint.statusLabel} /> },
                                            { label: 'Prioritas', value: <PriorityBadge priority={complaint.priority} /> },
                                            { label: 'Kategori', value: complaint.category },
                                            { label: 'Unit Penanganan', value: complaint.assignedUnit ?? 'Belum ditentukan' },
                                            { label: 'Diajukan', value: complaint.submittedAt ?? '-' },
                                            { label: 'Target Penyelesaian', value: complaint.dueAt ?? '-' },
                                            { label: 'Terakhir Diperbarui', value: complaint.updatedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Deskripsi Awal</h2></div>
                                <div className="db-card-body">
                                    <p className="db-message-body" style={{ marginTop: 0 }}>{complaint.description}</p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            {confirmClose && typeof document !== 'undefined' && createPortal(
                <div className="db-root">
                    <div className="db-modal-backdrop" role="presentation" onClick={() => setConfirmClose(false)}>
                        <div className="db-modal" role="dialog" aria-modal="true" aria-label="Tutup pengaduan" onClick={(event) => event.stopPropagation()}>
                            <div className="db-modal-icon red"><TriangleAlert size={22} /></div>
                            <h3>Tutup pengaduan?</h3>
                            <p>Tiket {complaint.ticketNumber} akan ditutup dan tidak bisa dibalas lagi. Pastikan kendala sudah benar-benar selesai.</p>
                            <div className="db-modal-actions">
                                <button type="button" className="db-btn danger" onClick={closeTicket}>
                                    <X size={14} /> Ya, tutup tiket
                                </button>
                                <button type="button" className="db-btn ghost" onClick={() => setConfirmClose(false)}>Batal</button>
                            </div>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </AdminShell>
    );
}
