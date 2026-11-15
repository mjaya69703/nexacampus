import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus, Coins, Info, Paperclip, Pencil, ReceiptText } from 'lucide-react';
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

type App = {
    id: number;
    number: string;
    academicYear: string;
    semester: number;
    duration: number;
    reasonCategory: string;
    reasonLabel: string;
    reason: string;
    status: string;
    statusLabel: string;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    leaveFeeAmount: number | null;
    leaveFeeInvoiceId: number | null;
    invoice: Invoice | null;
    submittedAt: string | null;
    updatedAt: string | null;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};

type Props = { shell: ShellProps; application: App; urls: { index: string; edit: string | null } };

const statusHint = (status: string): string | null => {
    switch (status) {
        case 'submitted':
            return 'Pengajuan cuti sudah masuk dan menunggu pemeriksaan petugas.';
        case 'under_review':
            return 'Pengajuan cuti sedang ditinjau. Pantau perubahan status di halaman ini.';
        case 'in_approval':
            return 'Pengajuan cuti sedang menunggu persetujuan pejabat berwenang.';
        case 'approved_pending_payment':
            return 'Pengajuan disetujui. Selesaikan pembayaran biaya cuti agar status cuti dapat diaktifkan.';
        case 'revision_requested':
            return 'Petugas meminta perbaikan. Baca catatan admin, perbaiki data, lalu kirim ulang.';
        case 'approved':
            return 'Pengajuan cuti disetujui dan akan segera diaktifkan.';
        case 'activated':
            return 'Cuti akademik sedang aktif. Ajukan pengaktifan kembali saat masa cuti berakhir.';
        case 'returned':
            return 'Kamu sudah kembali aktif. Riwayat cuti tetap tersimpan di halaman ini.';
        default:
            return null;
    }
};

export default function LeavesShow({ shell, application, urls }: Props) {
    const hint = statusHint(application.status);

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Student Services · Cuti"
                        title={application.number}
                        description={`${application.academicYear} · Semester ${application.semester}`}
                        badges={[statusLabel(application.status)]}
                        actions={
                            <>
                                <Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>
                                {urls.edit && <Link href={urls.edit} className="db-btn primary"><Pencil size={15} /> Perbaiki</Link>}
                            </>
                        }
                    />

                    {hint && (
                        <div className={`db-note ${application.status === 'revision_requested' || application.status === 'rejected' ? 'warning' : 'info'}`}>
                            <Info size={15} />
                            <span>{hint}</span>
                        </div>
                    )}

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarMinus size={16} /> Detail Cuti</h2>
                                    <StatusBadge status={application.status} label={application.statusLabel} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Status', value: <StatusBadge status={application.status} label={application.statusLabel} /> },
                                            { label: 'Tahun Akademik', value: application.academicYear },
                                            { label: 'Semester', value: application.semester },
                                            { label: 'Durasi', value: `${application.duration} semester` },
                                            { label: 'Kategori Alasan', value: application.reasonLabel },
                                            { label: 'Diajukan', value: application.submittedAt ?? '-' },
                                            { label: 'Terakhir Diperbarui', value: application.updatedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Alasan Pengajuan</h2></div>
                                <div className="db-card-body">
                                    <p className="db-class-reason" style={{ WebkitLineClamp: 'unset', display: 'block' }}>{application.reason}</p>
                                </div>
                            </section>

                            {(application.invoice || application.leaveFeeAmount !== null) && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><ReceiptText size={16} /> Biaya Cuti</h2>
                                        {application.invoice && <StatusBadge status={application.invoice.status} label={application.invoice.statusLabel} />}
                                    </div>
                                    <div className="db-card-body">
                                        {application.invoice ? (
                                            <div className="db-invoice">
                                                <div>
                                                    <b>{application.invoice.number}</b>
                                                    <small>Jatuh tempo {application.invoice.dueDate ?? '-'}</small>
                                                </div>
                                                <div>
                                                    <b>{formatRupiah(application.invoice.amount)}</b>
                                                    <small>Dibayar {formatRupiah(application.invoice.paidAmount)}</small>
                                                </div>
                                                <a href={application.invoice.url} className="db-btn primary sm">Lihat Tagihan</a>
                                            </div>
                                        ) : (
                                            <div className="db-invoice">
                                                <div>
                                                    <b>{(application.leaveFeeAmount ?? 0) > 0 ? formatRupiah(application.leaveFeeAmount) : 'Tanpa biaya'}</b>
                                                    <small>
                                                        {(application.leaveFeeAmount ?? 0) > 0
                                                            ? 'Tagihan cuti sedang disiapkan.'
                                                            : 'Tidak ada biaya cuti untuk pengajuan ini.'}
                                                    </small>
                                                </div>
                                                <span className={`db-badge ${(application.leaveFeeAmount ?? 0) > 0 ? 'amber' : 'green'}`}>
                                                    {(application.leaveFeeAmount ?? 0) > 0 ? 'Menunggu Tagihan' : 'Tanpa Biaya'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}

                            {(application.studentNotes || application.adminNotes) && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><Pencil size={16} /> Catatan</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <NotesBlock title="Catatan Mahasiswa" notes={application.studentNotes} />
                                        <NotesBlock title="Catatan Admin" notes={application.adminNotes} />
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Riwayat Status</h2></div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={application.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Status & Berkas</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <ApprovalNote approval={application.approval} />
                                    {application.attachmentUrl ? (
                                        <a href={application.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost">
                                            <Paperclip size={14} /> Lampiran Pendukung
                                        </a>
                                    ) : (
                                        <p className="db-hint">Tidak ada lampiran pendukung.</p>
                                    )}
                                    {application.leaveFeeAmount !== null && (application.leaveFeeAmount ?? 0) > 0 && (
                                        <div className="db-note muted">
                                            <Coins size={15} />
                                            <span>Biaya cuti: <b>{formatRupiah(application.leaveFeeAmount)}</b></span>
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
