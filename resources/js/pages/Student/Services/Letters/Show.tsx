import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText, Info, Paperclip, Pencil, Stamp } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { ApprovalNote, DetailGrid, HistoryTimeline, NotesBlock, StatusBadge } from '../ServiceUI';
import { humanizeField, statusLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RequestData = {
    id: number;
    number: string;
    type: string;
    typeDescription: string | null;
    fulfillmentMode: string | null;
    purpose: string;
    status: string;
    statusLabel: string;
    requestData: Record<string, string> | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    isDownloadable: boolean;
    submittedAt: string | null;
    updatedAt: string | null;
    issuedAt: string | null;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};

type Props = {
    shell: ShellProps;
    request: RequestData;
    urls: { index: string; edit: string | null; download: string | null };
};

const statusHint = (status: string): string | null => {
    switch (status) {
        case 'submitted':
            return 'Pengajuan sudah masuk dan akan segera diperiksa oleh petugas layanan akademik.';
        case 'under_review':
            return 'Pengajuan sedang ditinjau. Kamu bisa memantau perubahan status di halaman ini.';
        case 'revision_requested':
            return 'Petugas meminta perbaikan. Baca catatan admin, perbaiki data atau lampiran, lalu kirim ulang.';
        case 'approved':
            return 'Pengajuan disetujui. Surat sedang disiapkan dan akan bisa diunduh setelah diterbitkan.';
        case 'issued':
            return 'Surat sudah diterbitkan. Unduh berkas final melalui tombol di samping.';
        case 'rejected':
            return 'Pengajuan ditolak. Silakan baca catatan admin untuk mengetahui alasannya.';
        default:
            return null;
    }
};

export default function LettersShow({ shell, request, urls }: Props) {
    const hint = statusHint(request.status);
    const requestData = Object.entries(request.requestData ?? {}).filter(([, value]) => value !== null && value !== '');

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Student Services · Surat"
                        title={request.number}
                        description={request.type}
                        badges={[statusLabel(request.status)]}
                        actions={
                            <>
                                <Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>
                                {urls.download && (
                                    <a href={urls.download} target="_blank" rel="noreferrer" className="db-btn primary">
                                        <Download size={15} /> Unduh Surat
                                    </a>
                                )}
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
                                    <h2 className="db-card-title"><FileText size={16} /> Detail Pengajuan</h2>
                                    <StatusBadge status={request.status} label={request.statusLabel} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Jenis Surat', value: request.type },
                                            { label: 'Status', value: <StatusBadge status={request.status} label={request.statusLabel} /> },
                                            { label: 'Diajukan', value: request.submittedAt ?? '-' },
                                            { label: 'Terakhir Diperbarui', value: request.updatedAt ?? '-' },
                                            ...(request.issuedAt ? [{ label: 'Diterbitkan', value: request.issuedAt }] : []),
                                            { label: 'Tujuan / Keperluan', value: request.purpose, wide: true },
                                        ]}
                                    />
                                    {request.typeDescription && <p className="db-hint" style={{ marginTop: 12 }}>{request.typeDescription}</p>}
                                </div>
                            </section>

                            {requestData.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><Stamp size={16} /> Data Permohonan</h2></div>
                                    <div className="db-card-body">
                                        <DetailGrid
                                            items={requestData.map(([key, value]) => ({
                                                label: humanizeField(key),
                                                value,
                                                wide: String(value).length > 60,
                                            }))}
                                        />
                                    </div>
                                </section>
                            )}

                            {(request.studentNotes || request.adminNotes) && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><Pencil size={16} /> Catatan</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <NotesBlock title="Catatan Mahasiswa" notes={request.studentNotes} />
                                        <NotesBlock title="Catatan Admin" notes={request.adminNotes} />
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
                                            <Paperclip size={14} /> Lampiran Pengajuan
                                        </a>
                                    ) : (
                                        <p className="db-hint">Tidak ada lampiran pengajuan.</p>
                                    )}

                                    {urls.download ? (
                                        <a href={urls.download} target="_blank" rel="noreferrer" className="db-btn primary">
                                            <Download size={14} /> Unduh Surat Final
                                        </a>
                                    ) : (
                                        <div className="db-note muted">
                                            <Info size={15} />
                                            <span>Surat final belum tersedia. Tombol unduh aktif setelah surat diterbitkan.</span>
                                        </div>
                                    )}

                                    {urls.edit && (
                                        <Link href={urls.edit} className="db-btn primary">
                                            <Pencil size={14} /> Perbaiki Pengajuan
                                        </Link>
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
