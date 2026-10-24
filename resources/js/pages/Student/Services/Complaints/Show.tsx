import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Headset, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Att = { id: number; fileName: string; url: string };
type Msg = { id: number; senderType: string; senderName: string; message: string; isInternal: boolean; createdAt: string | null; attachments: Att[] };
type Hist = { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null };
type Complaint = {
    id: number; ticketNumber: string; subject: string; description: string; priority: string; priorityLabel: string;
    status: string; statusLabel: string; category: string; assignedUnit: string | null; submittedAt: string | null; dueAt: string | null;
    messages: Msg[]; histories: Hist[];
};

type Props = { shell: ShellProps; complaint: Complaint; canReply: boolean; canClose: boolean; urls: { index: string; reply: string; close: string; attachmentDownload: string } };

export default function ComplaintsShow({ shell, complaint, canReply, canClose, urls }: Props) {
    const reply = useForm<{ replyMessage: string; replyAttachments: File[] }>({ replyMessage: '', replyAttachments: [] });

    const submitReply = (e: FormEvent) => {
        e.preventDefault();
        reply.post(urls.reply, { forceFormData: true, preserveScroll: true, onSuccess: () => { reply.reset(); } });
    };

    const closeTicket = () => {
        reply.post(urls.close, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Headset} eyebrow="Student Services" title={complaint.ticketNumber} description={complaint.subject} badges={[complaint.statusLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Status:</b> {complaint.statusLabel}</div>
                            <div><b>Kategori:</b> {complaint.category}</div>
                            <div><b>Prioritas:</b> {complaint.priorityLabel}</div>
                            {complaint.assignedUnit && <div><b>Unit:</b> {complaint.assignedUnit}</div>}
                            <div><b>Deskripsi:</b> {complaint.description}</div>
                        </div>
                    </section>
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Thread</h2></div>
                        <div className="db-card-body">
                            {complaint.messages.map((m) => (
                                <div key={m.id} style={{ padding: '8px 0', borderBottom: '1px solid #eee' }}>
                                    <b>{m.senderName}</b> · {m.createdAt}
                                    <div style={{ marginTop: 4 }}>{m.message}</div>
                                    {m.attachments.length > 0 && (
                                        <div style={{ marginTop: 4, display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                            {m.attachments.map((a) => (
                                                <a key={a.id} href={a.url} target="_blank" className="db-btn ghost sm">{a.fileName}</a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                    {canReply && (
                        <section className="db-card">
                            <div className="db-card-body">
                                <form onSubmit={submitReply} style={{ display: 'grid', gap: 12 }}>
                                    <label className="db-label">Balasan</label>
                                    <textarea className="db-input" rows={4} value={reply.data.replyMessage}
                                        onChange={(e) => reply.setData('replyMessage', e.target.value)} />
                                    {reply.errors.replyMessage && <div className="db-note danger">{reply.errors.replyMessage}</div>}
                                    <label className="db-label">Lampiran (opsional)</label>
                                    <input type="file" className="db-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                        onChange={(e) => reply.setData('replyAttachments', Array.from(e.target.files ?? []))} />
                                    <button type="submit" className="db-btn primary" disabled={reply.processing}><Send size={14} /> Kirim Balasan</button>
                                </form>
                            </div>
                        </section>
                    )}
                    {canClose && (
                        <button className="db-btn primary" onClick={closeTicket}>Konfirmasi Tutup</button>
                    )}
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Riwayat Status</h2></div>
                        <div className="db-card-body">
                            {complaint.histories.map((h) => (
                                <div key={h.id} style={{ padding: '6px 0', borderBottom: '1px solid #eee' }}>
                                    <b>{h.to}</b> · {h.changedAt} · {h.changedBy ?? '-'}{h.notes ? ` — ${h.notes}` : ''}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
