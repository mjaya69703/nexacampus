import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, ArrowRightLeft, Coins, Info, Paperclip, Pencil, ReceiptText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { ApprovalNote, DetailGrid, HistoryTimeline, NotesBlock, StatusBadge } from '../ServiceUI';
import { formatRupiah, statusLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Invoice = {
    id: number;
    number: string;
    status: string;
    statusLabel: string;
    amount: number;
    paidAmount: number;
    dueDate: string | null;
    url: string;
};

type Req = {
    id: number;
    number: string;
    fromProgram: string;
    toProgram: string;
    transferType: string;
    transferTypeLabel: string;
    fromClassType: string | null;
    toClassType: string | null;
    fromClassTypeLabel: string;
    toClassTypeLabel: string;
    currentSemester: number | null;
    recommendedSemester: number | null;
    reason: string;
    status: string;
    statusLabel: string;
    studentNotes: string | null;
    adminNotes: string | null;
    academicEvaluationNotes: string | null;
    creditMappingNotes: string | null;
    financeNotes: string | null;
    transferFeeAmount: number | null;
    transferFeeDueDate: string | null;
    invoice: Invoice | null;
    attachmentUrl: string | null;
    submittedAt: string | null;
    updatedAt: string | null;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};

type Props = { shell: ShellProps; request: Req; urls: { index: string; edit: string | null } };

const statusHint = (status: string): string | null => {
    switch (status) {
        case 'submitted':
            return 'Pengajuan pindah sudah masuk dan menunggu pemeriksaan petugas.';
        case 'under_review':
            return 'Pengajuan sedang ditinjau, termasuk evaluasi akademik dan pemetaan kredit.';
        case 'in_approval':
            return 'Pengajuan sedang menunggu persetujuan pejabat berwenang.';
        case 'approved_pending_payment':
            return 'Pengajuan disetujui. Selesaikan pembayaran biaya pindah agar proses dapat dilanjutkan.';
        case 'revision_requested':
            return 'Petugas meminta perbaikan. Baca catatan admin, perbaiki data, lalu kirim ulang.';
        case 'approved':
            return 'Pengajuan disetujui dan akan segera diterapkan pada data akademik.';
        case 'applied':
            return 'Perpindahan sudah diterapkan. Data akademik dan kelas mengikuti program tujuan.';
        default:
            return null;
    }
};

export default function TransfersShow({ shell, request, urls }: Props) {
    const hint = statusHint(request.status);
    const notes = [
        { title: 'Catatan Mahasiswa', notes: request.studentNotes },
        { title: 'Catatan Admin', notes: request.adminNotes },
        { title: 'Evaluasi Akademik', notes: request.academicEvaluationNotes },
        { title: 'Pemetaan Kredit', notes: request.creditMappingNotes },
        { title: 'Catatan Keuangan', notes: request.financeNotes },
    ].filter((note) => Boolean(note.notes));

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowRightLeft}
                        eyebrow="Student Services · Pindah"
                        title={request.number}
                        description={`${request.fromProgram} → ${request.toProgram}`}
                        badges={[statusLabel(request.status)]}
                        actions={
                            <>
                                <Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>
                                {urls.edit && <Link href={urls.edit} className="db-btn primary"><Pencil size={15} /> Perbaiki</Link>}
                            </>
                        }
                    />

                    {hint && (
                        <div className={`db-note ${request.status === 'revision_requested' || request.status === 'rejected' ? 'warning' : 'info'}`}>
                            <Info size={15} />
                            <span>{hint}</span>
                        </div>
                    )}

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ArrowRightLeft size={16} /> Detail Pindah</h2>
                                    <StatusBadge status={request.status} label={request.statusLabel} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Status', value: <StatusBadge status={request.status} label={request.statusLabel} /> },
                                            { label: 'Jenis Pindah', value: request.transferTypeLabel },
                                            { label: 'Program Asal', value: request.fromProgram },
                                            { label: 'Program Tujuan', value: request.toProgram },
                                            { label: 'Kelas Asal', value: request.fromClassTypeLabel },
                                            { label: 'Kelas Tujuan', value: request.toClassTypeLabel },
                                            { label: 'Semester Saat Ini', value: request.currentSemester ?? '-' },
                                            { label: 'Semester Rekomendasi', value: request.recommendedSemester ?? '-' },
                                            { label: 'Diajukan', value: request.submittedAt ?? '-' },
                                            { label: 'Terakhir Diperbarui', value: request.updatedAt ?? '-' },
                                        ]}
                                    />
                                    <div className="db-class-meta" style={{ marginTop: 14, color: 'var(--db-heading)', fontWeight: 700 }}>
                                        <span>{request.fromProgram} <ArrowRight size={13} /> {request.toProgram}</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Alasan Pengajuan</h2></div>
                                <div className="db-card-body">
                                    <p className="db-class-reason" style={{ WebkitLineClamp: 'unset', display: 'block' }}>{request.reason}</p>
                                </div>
                            </section>

                            {(request.invoice || request.transferFeeAmount !== null) && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><ReceiptText size={16} /> Biaya Pindah</h2>
                                        {request.invoice && <StatusBadge status={request.invoice.status} label={request.invoice.statusLabel} />}
                                    </div>
                                    <div className="db-card-body">
                                        {request.invoice ? (
                                            <div className="db-invoice">
                                                <div>
                                                    <b>{request.invoice.number}</b>
                                                    <small>Jatuh tempo {request.invoice.dueDate ?? request.transferFeeDueDate ?? '-'}</small>
                                                </div>
                                                <div>
                                                    <b>{formatRupiah(request.invoice.amount)}</b>
                                                    <small>Dibayar {formatRupiah(request.invoice.paidAmount)}</small>
                                                </div>
                                                <a href={request.invoice.url} className="db-btn primary sm">Lihat Tagihan</a>
                                            </div>
                                        ) : (
                                            <div className="db-invoice">
                                                <div>
                                                    <b>{(request.transferFeeAmount ?? 0) > 0 ? formatRupiah(request.transferFeeAmount) : 'Tanpa biaya'}</b>
                                                    <small>
                                                        {(request.transferFeeAmount ?? 0) > 0
                                                            ? `Jatuh tempo ${request.transferFeeDueDate ?? '-'}`
                                                            : 'Tidak ada biaya pindah untuk pengajuan ini.'}
                                                    </small>
                                                </div>
                                                <span className={`db-badge ${(request.transferFeeAmount ?? 0) > 0 ? 'amber' : 'green'}`}>
                                                    {(request.transferFeeAmount ?? 0) > 0 ? 'Menunggu Tagihan' : 'Tanpa Biaya'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}

                            {notes.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><Pencil size={16} /> Catatan Proses</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        {notes.map((note) => <NotesBlock key={note.title} title={note.title} notes={note.notes} />)}
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Riwayat Status</h2></div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={request.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Status & Berkas</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <ApprovalNote approval={request.approval} />
                                    {request.attachmentUrl ? (
                                        <a href={request.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost">
                                            <Paperclip size={14} /> Lampiran Pendukung
                                        </a>
                                    ) : (
                                        <p className="db-hint">Tidak ada lampiran pendukung.</p>
                                    )}
                                    {request.transferFeeAmount !== null && (request.transferFeeAmount ?? 0) > 0 && (
                                        <div className="db-note muted">
                                            <Coins size={15} />
                                            <span>Biaya pindah: <b>{formatRupiah(request.transferFeeAmount)}</b></span>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
