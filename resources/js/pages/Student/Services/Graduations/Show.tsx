import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileText, GraduationCap, Info, Paperclip, Pencil, ShieldCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { ApprovalNote, ChecklistItem, DetailGrid, HistoryTimeline, NotesBlock, StatusBadge } from '../ServiceUI';
import { humanizeField, statusLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type App = {
    id: number;
    number: string;
    status: string;
    statusLabel: string;
    graduationPeriod: string | null;
    graduationDate: string | null;
    thesisTitle: string | null;
    reason: string | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    academicPeriod: string | null;
    graduationBatch: string | null;
    eligibilitySnapshot: Record<string, unknown> | null;
    submittedAt: string | null;
    updatedAt: string | null;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};
type CheckItem = { key: string; label: string; checked: boolean; checkedAt: string | null; checkedBy: string | null; notes: string | null };
type Doc = {
    id: number;
    fileName: string;
    verificationStatus: string;
    verificationNotes: string | null;
    verifiedAt: string | null;
    verifiedBy: string | null;
    previewUrl: string;
    isPdf: boolean;
    isImage: boolean;
};

type Props = {
    shell: ShellProps;
    application: App;
    checklistItems: CheckItem[];
    checklistCompleted: number;
    documents: Doc[];
    urls: { index: string; edit: string | null };
};

const statusHint = (status: string): string | null => {
    switch (status) {
        case 'submitted':
            return 'Pengajuan yudisium sudah masuk. Admin akan mengecek eligibility, dokumen, dan checklist.';
        case 'under_review':
            return 'Pengajuan sedang direview. Pantau progress checklist dan verifikasi dokumen di halaman ini.';
        case 'in_approval':
            return 'Pengajuan sedang menunggu persetujuan pejabat berwenang.';
        case 'revision_requested':
            return 'Ada perbaikan yang diminta. Baca catatan admin, lengkapi dokumen, lalu kirim ulang.';
        case 'approved':
            return 'Pengajuan disetujui. Menunggu finalisasi dan penetapan tanggal yudisium.';
        case 'finalized':
            return 'Yudisium sudah difinalkan. Data kelulusan sudah tercatat.';
        default:
            return null;
    }
};

const formatSnapshotValue = (value: unknown): string => {
    if (typeof value === 'boolean') return value ? 'Terpenuhi' : 'Belum terpenuhi';
    if (value === null || value === undefined || value === '') return '-';
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
};

export default function GraduationsShow({ shell, application, checklistItems, checklistCompleted, documents, urls }: Props) {
    const hint = statusHint(application.status);
    const snapshot = application.eligibilitySnapshot && typeof application.eligibilitySnapshot === 'object'
        ? Object.entries(application.eligibilitySnapshot).filter(([key]) => key !== 'checks')
        : [];
    const progress = checklistItems.length > 0 ? Math.round((checklistCompleted / checklistItems.length) * 100) : 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Student Services · Yudisium"
                        title={application.number}
                        description={application.graduationPeriod ?? 'Periode yudisium belum ditentukan'}
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
                                    <h2 className="db-card-title"><GraduationCap size={16} /> Detail Yudisium</h2>
                                    <StatusBadge status={application.status} label={application.statusLabel} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Status', value: <StatusBadge status={application.status} label={application.statusLabel} /> },
                                            { label: 'Periode Yudisium', value: application.graduationPeriod ?? '-' },
                                            { label: 'Tanggal Lulus', value: application.graduationDate ?? 'Belum final' },
                                            { label: 'Batch Yudisium', value: application.graduationBatch ?? '-' },
                                            { label: 'Periode Akademik', value: application.academicPeriod ?? '-' },
                                            { label: 'Diajukan', value: application.submittedAt ?? '-' },
                                            { label: 'Terakhir Diperbarui', value: application.updatedAt ?? '-' },
                                            ...(application.thesisTitle ? [{ label: 'Judul Skripsi / Tugas Akhir', value: application.thesisTitle, wide: true }] : []),
                                            ...(application.reason ? [{ label: 'Keterangan', value: application.reason, wide: true }] : []),
                                        ]}
                                    />
                                </div>
                            </section>

                            {snapshot.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><ShieldCheck size={16} /> Ringkasan Eligibility</h2></div>
                                    <div className="db-card-body">
                                        <DetailGrid
                                            items={snapshot.map(([key, value]) => ({
                                                label: humanizeField(key),
                                                value: formatSnapshotValue(value),
                                            }))}
                                        />
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CheckCircle2 size={16} /> Checklist Admin</h2>
                                    <span className="db-badge gray">{checklistCompleted}/{checklistItems.length}</span>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-progress" aria-hidden="true"><i style={{ width: `${progress}%` }} /></div>
                                    <div className="db-checklist" style={{ marginTop: 10 }}>
                                        {checklistItems.map((item) => (
                                            <ChecklistItem
                                                key={item.key}
                                                checked={item.checked}
                                                label={item.label}
                                                meta={[
                                                    item.checkedBy ? `oleh ${item.checkedBy}` : null,
                                                    item.checkedAt,
                                                    item.notes,
                                                ].filter(Boolean).join(' · ') || null}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </section>

                            {documents.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><FileText size={16} /> Dokumen Yudisium</h2></div>
                                    <div className="db-card-body">
                                        <div className="db-file-list">
                                            {documents.map((document) => (
                                                <div className="db-file" key={document.id}>
                                                    <div>
                                                        <b>{document.fileName}</b>
                                                        <small>
                                                            {document.verifiedBy ? `Diverifikasi ${document.verifiedBy}` : 'Belum diverifikasi'}
                                                            {document.verifiedAt ? ` · ${document.verifiedAt}` : ''}
                                                        </small>
                                                        {document.verificationNotes && <small>{document.verificationNotes}</small>}
                                                    </div>
                                                    <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                                                        <StatusBadge status={document.verificationStatus} />
                                                        <a href={document.previewUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm">Lihat</a>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
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
                                    {urls.edit && <Link href={urls.edit} className="db-btn primary"><Pencil size={14} /> Perbaiki Pengajuan</Link>}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
