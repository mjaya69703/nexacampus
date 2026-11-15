// Detail pengajuan yudisium + checklist, verifikasi dokumen, dan workflow admin.
import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileCheck2,
    GraduationCap,
    Info,
    Paperclip,
    Pencil,
    Send,
    ShieldCheck,
    Trophy,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import {
    ApprovalNote,
    DetailGrid,
    HistoryTimeline,
    NotesBlock,
    StatusBadge,
} from '../../../../components/Shared/StudentServices/ServiceUI';
import { statusLabel } from '../../../../components/Shared/StudentServices/labels';
import { WorkflowModal } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type History = {
    id: number;
    from: string | null;
    to: string;
    notes: string | null;
    changedBy: string | null;
    changedAt: string | null;
};

type ChecklistRow = {
    key: string;
    label: string;
    checked: boolean;
    notes: string | null;
    checkedBy: string | null;
    checkedAt: string | null;
};

type DocumentRow = {
    id: number;
    requirement: string;
    documentType: string;
    fileName: string;
    fileSize: number | null;
    status: string;
    notes: string | null;
    verifiedBy: string | null;
    verifiedAt: string | null;
    previewUrl: string;
};

type EligibilityCheck = {
    key: string;
    label: string;
    required: boolean;
    passed: boolean;
    actual: string;
    expected: string;
    message: string | null;
};

type Application = {
    id: number;
    number: string;
    status: string;
    student: string;
    nim: string;
    studyProgram: string;
    faculty: string;
    academicPeriod: string;
    batch: string;
    batchDate: string | null;
    graduationPeriod: string;
    thesisTitle: string | null;
    reason: string | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    attachmentName: string | null;
    submittedAt: string | null;
    updatedAt: string | null;
    reviewedBy: string | null;
    reviewedAt: string | null;
    approvedBy: string | null;
    approvedAt: string | null;
    finalizedBy: string | null;
    finalizedAt: string | null;
    graduationDate: string | null;
    approval: { status: string; waitingMessage: string | null } | null;
    histories: History[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean };
    application: Application;
    checklist: ChecklistRow[];
    checklistComplete: boolean;
    requiredDocumentsVerified: boolean;
    requiredDocumentCount: number;
    documents: DocumentRow[];
    eligibility: {
        policyName: string | null;
        policyScope: string | null;
        checks: EligibilityCheck[];
        rows: { key: string; label: string; value: string }[];
    };
    nextAction: { tone: string; title: string; body: string };
    urls: {
        index: string;
        markUnderReview: string;
        saveChecklist: string;
        verifyDocument: string;
        approve: string;
        requestRevision: string;
        reject: string;
        finalize: string;
    };
};

type ActionKey = 'under_review' | 'approve' | 'revision' | 'reject' | 'finalize';

const REVIEWABLE = ['submitted', 'in_approval', 'under_review', 'revision_requested'];

const ACTION_COPY: Record<ActionKey, { title: string; message: string; confirm: string; danger: boolean }> = {
    under_review: {
        title: 'Tandai Sedang Direview',
        message: 'Pengajuan yudisium akan ditandai sedang ditinjau petugas.',
        confirm: 'Tandai review',
        danger: false,
    },
    approve: {
        title: 'Setujui Pengajuan Yudisium',
        message: 'Pastikan checklist review lengkap dan seluruh dokumen wajib sudah terverifikasi sebelum menyetujui.',
        confirm: 'Setujui pengajuan',
        danger: false,
    },
    revision: {
        title: 'Minta Perbaikan',
        message: 'Mahasiswa akan menerima permintaan perbaikan dan dapat mengirim ulang pengajuan yudisium.',
        confirm: 'Kirim permintaan',
        danger: false,
    },
    reject: {
        title: 'Tolak Pengajuan Yudisium',
        message: 'Pengajuan yudisium akan ditolak. Tuliskan alasan penolakan agar mahasiswa memahami keputusan.',
        confirm: 'Tolak pengajuan',
        danger: true,
    },
    finalize: {
        title: 'Finalisasi Kelulusan',
        message: 'Status mahasiswa akan berubah menjadi Lulus dan profil akademik dinonaktifkan. Tindakan ini tidak bisa dibatalkan lewat workflow normal.',
        confirm: 'Finalisasi sekarang',
        danger: false,
    },
};

function formatFileSize(bytes: number | null): string {
    if (!bytes || bytes <= 0) return '-';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function checklistPayload(rows: ChecklistRow[]) {
    return Object.fromEntries(
        rows.map((row) => [row.key, { checked: row.checked, notes: row.notes ?? '' }]),
    );
}

export default function GraduationApplicationShow({
    shell,
    can,
    application,
    checklist,
    checklistComplete,
    requiredDocumentsVerified,
    requiredDocumentCount,
    documents,
    eligibility,
    nextAction,
    urls,
}: Props) {
    const [action, setAction] = useState<ActionKey | null>(null);
    const [documentTarget, setDocumentTarget] = useState<DocumentRow | null>(null);
    const [rows, setRows] = useState<ChecklistRow[]>(checklist);

    const checklistForm = useForm({
        notes: application.adminNotes ?? '',
        checklist: checklistPayload(checklist),
    });

    const notesForm = useForm({ notes: '' });

    const finalizeForm = useForm({
        notes: '',
        graduation_date: application.graduationDate ?? '',
    });

    const documentForm = useForm<{ document_id: number; status: string; notes: string }>({
        document_id: 0,
        status: 'verified',
        notes: '',
    });

    const canReview = can.update && REVIEWABLE.includes(application.status);
    const canFinalize = can.update && application.status === 'approved' && Boolean(application.batchDate);
    const canReject = application.approval?.status === 'in_progress';
    const copy = action ? ACTION_COPY[action] : null;

    const allChecked = rows.every((row) => row.checked);
    const approvalReady = allChecked && requiredDocumentsVerified;

    const toggleRow = (key: string) => {
        setRows((prev) => prev.map((row) => (row.key === key ? { ...row, checked: !row.checked } : row)));
    };

    const setRowNotes = (key: string, notes: string) => {
        setRows((prev) => prev.map((row) => (row.key === key ? { ...row, notes } : row)));
    };

    const syncChecklist = () => {
        checklistForm.setData('checklist', checklistPayload(rows));
        return checklistPayload(rows);
    };

    const saveChecklist = () => {
        checklistForm.setData('checklist', checklistPayload(rows));
        checklistForm.post(urls.saveChecklist, { preserveScroll: true });
    };

    const confirmAction = () => {
        if (!action) return;

        const done = {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                notesForm.reset();
                finalizeForm.reset();
            },
        };

        if (action === 'approve') {
            syncChecklist();
            checklistForm.post(urls.approve, done);
            return;
        }

        if (action === 'finalize') {
            finalizeForm.post(urls.finalize, done);
            return;
        }

        const url = {
            under_review: urls.markUnderReview,
            revision: urls.requestRevision,
            reject: urls.reject,
        }[action];

        notesForm.post(url, done);
    };

    const openDocument = (document: DocumentRow) => {
        setDocumentTarget(document);
        documentForm.setData({
            document_id: document.id,
            status: 'verified',
            notes: document.notes ?? '',
        });
        documentForm.clearErrors();
    };

    const submitDocument = () => {
        documentForm.post(urls.verifyDocument, {
            preserveScroll: true,
            onSuccess: () => {
                setDocumentTarget(null);
                documentForm.reset();
            },
        });
    };

    const noteTone = ['info', 'warning', 'success', 'danger', 'muted'].includes(nextAction.tone)
        ? nextAction.tone
        : 'info';

    return (
        <AdminShell shell={shell}>
            <Head title={`${application.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Layanan Mahasiswa · Yudisium"
                        title={application.number}
                        description={`${application.student} · ${application.studyProgram}`}
                        badges={[statusLabel(application.status)]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>}
                    />

                    <div className={`db-note ${noteTone}`}>
                        <Info size={15} />
                        <span>
                            <b>{nextAction.title}.</b> {nextAction.body}
                        </span>
                    </div>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><GraduationCap size={16} /> Detail Pengajuan</h2>
                                    <StatusBadge status={application.status} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Mahasiswa', value: application.student },
                                            { label: 'NIM', value: application.nim },
                                            { label: 'Program Studi', value: application.studyProgram },
                                            { label: 'Fakultas', value: application.faculty },
                                            { label: 'Periode Akademik', value: application.academicPeriod },
                                            { label: 'Batch Yudisium', value: application.batch },
                                            { label: 'Periode Yudisium', value: application.graduationPeriod || '-' },
                                            { label: 'Tanggal Yudisium', value: application.batchDate ?? '-' },
                                            { label: 'Diajukan', value: application.submittedAt ?? '-' },
                                            { label: 'Diperbarui', value: application.updatedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Pencil size={16} /> Judul & Alasan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                    <div>
                                        <span className="db-label">Judul Skripsi / Tugas Akhir</span>
                                        <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{application.thesisTitle || '-'}</p>
                                    </div>
                                    <div>
                                        <span className="db-label">Alasan Pengajuan</span>
                                        <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{application.reason || '-'}</p>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ShieldCheck size={16} /> Kelayakan Akademik</h2>
                                    {eligibility.policyName && <span className="db-badge gray">{eligibility.policyName}</span>}
                                </div>
                                <div className="db-card-body">
                                    {eligibility.checks.length === 0 ? (
                                        <p className="db-hint" style={{ margin: 0 }}>Snapshot kelayakan tidak tersedia.</p>
                                    ) : (
                                        <div className="crud-table-wrap">
                                            <table className="crud-table">
                                                <thead>
                                                    <tr>
                                                        <th>Syarat</th>
                                                        <th>Aktual</th>
                                                        <th>Minimum</th>
                                                        <th>Hasil</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {eligibility.checks.map((check) => (
                                                        <tr key={check.key}>
                                                            <td>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{check.label}</b>
                                                                {!check.required && <small className="db-hint">Tidak wajib</small>}
                                                                {check.message && <small className="db-hint" style={{ display: 'block' }}>{check.message}</small>}
                                                            </td>
                                                            <td>{check.actual}</td>
                                                            <td>{check.expected}</td>
                                                            <td>
                                                                <span className={`db-badge ${check.passed ? 'green' : (check.required ? 'red' : 'gray')}`}>
                                                                    {check.passed ? 'Lolos' : (check.required ? 'Belum Lolos' : 'Diabaikan')}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ClipboardCheck size={16} /> Checklist Review</h2>
                                    <span className={`db-badge ${allChecked ? 'green' : 'amber'}`}>
                                        {rows.filter((row) => row.checked).length}/{rows.length} selesai
                                    </span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    {rows.map((row) => (
                                        <div key={row.key} style={{ display: 'grid', gap: 6, paddingBottom: 12, borderBottom: '1px solid var(--db-line)' }}>
                                            <label style={{ display: 'flex', alignItems: 'center', gap: 10, cursor: can.update ? 'pointer' : 'default' }}>
                                                <input
                                                    type="checkbox"
                                                    checked={row.checked}
                                                    disabled={!can.update}
                                                    onChange={() => toggleRow(row.key)}
                                                    style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }}
                                                />
                                                <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{row.label}</b>
                                                {row.checkedBy && (
                                                    <small className="db-hint">{row.checkedBy} · {row.checkedAt ?? '-'}</small>
                                                )}
                                            </label>
                                            {can.update && (
                                                <input
                                                    className="db-input"
                                                    placeholder="Catatan item (opsional)…"
                                                    value={row.notes ?? ''}
                                                    onChange={(event) => setRowNotes(row.key, event.target.value)}
                                                />
                                            )}
                                            {!can.update && row.notes && <small className="db-hint">{row.notes}</small>}
                                        </div>
                                    ))}

                                    {can.update && (
                                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                            <button className="db-btn ghost" type="button" onClick={saveChecklist} disabled={checklistForm.processing}>
                                                <ClipboardCheck size={14} /> {checklistForm.processing ? 'Menyimpan…' : 'Simpan Checklist'}
                                            </button>
                                        </div>
                                    )}
                                    {canReview && !checklistComplete && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Tombol setujui aktif setelah seluruh checklist dicentang dan disimpan.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileCheck2 size={16} /> Dokumen Yudisium</h2>
                                    <span className={`db-badge ${requiredDocumentCount === 0 ? 'gray' : (requiredDocumentsVerified ? 'green' : 'amber')}`}>
                                        {requiredDocumentCount === 0
                                            ? 'Tidak ada dokumen wajib'
                                            : (requiredDocumentsVerified ? 'Dokumen wajib lengkap' : 'Dokumen wajib belum lengkap')}
                                    </span>
                                </div>
                                <div className="db-card-body">
                                    {documents.length === 0 ? (
                                        <p className="db-hint" style={{ margin: 0 }}>Mahasiswa belum mengunggah dokumen yudisium.</p>
                                    ) : (
                                        <div className="crud-table-wrap">
                                            <table className="crud-table">
                                                <thead>
                                                    <tr>
                                                        <th>Dokumen</th>
                                                        <th>Berkas</th>
                                                        <th>Status</th>
                                                        <th style={{ textAlign: 'right' }}>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {documents.map((document) => (
                                                        <tr key={document.id}>
                                                            <td>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{document.requirement}</b>
                                                                <small className="db-hint">{document.documentType}</small>
                                                            </td>
                                                            <td>
                                                                <span style={{ display: 'block', fontSize: 12.5 }}>{document.fileName}</span>
                                                                <small className="db-hint">{formatFileSize(document.fileSize)}</small>
                                                            </td>
                                                            <td>
                                                                <StatusBadge status={document.status} />
                                                                {document.verifiedBy && (
                                                                    <small className="db-hint" style={{ display: 'block' }}>
                                                                        {document.verifiedBy} · {document.verifiedAt ?? '-'}
                                                                    </small>
                                                                )}
                                                            </td>
                                                            <td>
                                                                <div className="crud-row-actions" style={{ justifyContent: 'flex-end' }}>
                                                                    <a className="db-btn ghost sm" href={document.previewUrl} target="_blank" rel="noreferrer" title="Lihat berkas">
                                                                        <Eye size={13} />
                                                                    </a>
                                                                    {can.update && (
                                                                        <button className="db-btn ghost sm" type="button" onClick={() => openDocument(document)} title="Verifikasi dokumen">
                                                                            <FileCheck2 size={13} />
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {(application.studentNotes || application.adminNotes) && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Pencil size={16} /> Catatan</h2>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <NotesBlock title="Catatan Mahasiswa" notes={application.studentNotes} />
                                        <NotesBlock title="Catatan Admin" notes={application.adminNotes} />
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Riwayat Status</h2>
                                </div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={application.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CheckCircle2 size={16} /> Tindakan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <ApprovalNote approval={application.approval} />

                                    {canReview && (
                                        <>
                                            {application.status !== 'under_review' && (
                                                <button className="db-btn ghost" type="button" onClick={() => setAction('under_review')}>
                                                    <Info size={14} /> Tandai Sedang Direview
                                                </button>
                                            )}
                                            <button
                                                className="db-btn primary"
                                                type="button"
                                                onClick={() => setAction('approve')}
                                                disabled={!approvalReady}
                                                title={!approvalReady ? 'Checklist dan dokumen wajib belum lengkap' : undefined}
                                            >
                                                <CheckCircle2 size={14} /> Setujui Pengajuan
                                            </button>
                                            <button className="db-btn ghost" type="button" onClick={() => setAction('revision')}>
                                                <Send size={14} /> Minta Perbaikan
                                            </button>
                                            {canReject && (
                                                <button className="db-btn danger" type="button" onClick={() => setAction('reject')}>
                                                    <XCircle size={14} /> Tolak Pengajuan
                                                </button>
                                            )}
                                        </>
                                    )}

                                    {canFinalize && (
                                        <button className="db-btn primary" type="button" onClick={() => setAction('finalize')}>
                                            <Trophy size={14} /> Finalisasi Kelulusan
                                        </button>
                                    )}

                                    {!approvalReady && canReview && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            {!allChecked
                                                ? 'Centang seluruh checklist review terlebih dahulu.'
                                                : 'Verifikasi seluruh dokumen wajib sebelum menyetujui.'}
                                        </p>
                                    )}

                                    {!canReview && !canFinalize && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Tidak ada tindakan yang tersedia pada status ini.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Berkas & Jejak Review</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {application.attachmentUrl ? (
                                        <a className="db-btn ghost" href={application.attachmentUrl} target="_blank" rel="noreferrer">
                                            <Paperclip size={14} /> {application.attachmentName ?? 'Lampiran Pendukung'}
                                        </a>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada lampiran pendukung.</p>
                                    )}
                                    {application.reviewedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Direview oleh <b>{application.reviewedBy}</b> · {application.reviewedAt ?? '-'}
                                        </p>
                                    )}
                                    {application.approvedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Disetujui oleh <b>{application.approvedBy}</b> · {application.approvedAt ?? '-'}
                                        </p>
                                    )}
                                    {application.finalizedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Difinalisasi oleh <b>{application.finalizedBy}</b> · {application.finalizedAt ?? '-'}
                                        </p>
                                    )}
                                    {application.graduationDate && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Tanggal kelulusan: <b>{application.graduationDate}</b>
                                        </p>
                                    )}
                                </div>
                            </section>

                            {eligibility.rows.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title">Snapshot Kelayakan</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <DetailGrid
                                            items={eligibility.rows.map((row) => ({ label: row.label, value: row.value }))}
                                        />
                                    </div>
                                </section>
                            )}
                        </div>
                    </div>
                </div>

                <WorkflowModal
                    open={action !== null}
                    title={copy?.title ?? ''}
                    message={copy?.message ?? ''}
                    confirmLabel={copy?.confirm ?? 'Simpan'}
                    danger={copy?.danger}
                    processing={
                        action === 'approve'
                            ? checklistForm.processing
                            : action === 'finalize'
                                ? finalizeForm.processing
                                : notesForm.processing
                    }
                    onConfirm={confirmAction}
                    onCancel={() => setAction(null)}
                >
                    {action === 'approve' && (
                        <>
                            <div className="db-note muted">
                                <ClipboardCheck size={15} />
                                <span>
                                    Checklist: <b>{rows.filter((row) => row.checked).length}/{rows.length}</b> · Dokumen wajib:{' '}
                                    <b>{requiredDocumentCount === 0 ? 'tidak ada' : (requiredDocumentsVerified ? 'lengkap' : 'belum lengkap')}</b>
                                </span>
                            </div>
                            <TextareaField
                                label="Catatan untuk mahasiswa"
                                rows={3}
                                placeholder="Opsional…"
                                value={checklistForm.data.notes}
                                onChange={(event) => checklistForm.setData('notes', event.target.value)}
                                error={checklistForm.errors.notes}
                            />
                        </>
                    )}

                    {action === 'finalize' && (
                        <>
                            <TextField
                                label="Tanggal kelulusan"
                                type="date"
                                value={finalizeForm.data.graduation_date}
                                onChange={(event) => finalizeForm.setData('graduation_date', event.target.value)}
                                error={finalizeForm.errors.graduation_date}
                                hint="Kosongkan untuk memakai tanggal yudisium batch."
                            />
                            <TextareaField
                                label="Catatan finalisasi"
                                rows={3}
                                placeholder="Opsional…"
                                value={finalizeForm.data.notes}
                                onChange={(event) => finalizeForm.setData('notes', event.target.value)}
                                error={finalizeForm.errors.notes}
                            />
                        </>
                    )}

                    {action !== 'approve' && action !== 'finalize' && (
                        <TextareaField
                            label="Catatan"
                            rows={4}
                            placeholder="Opsional. Tuliskan catatan atau alasan keputusan…"
                            value={notesForm.data.notes}
                            onChange={(event) => notesForm.setData('notes', event.target.value)}
                            error={notesForm.errors.notes}
                        />
                    )}
                </WorkflowModal>

                <WorkflowModal
                    open={documentTarget !== null}
                    title="Verifikasi Dokumen"
                    message={documentTarget ? `${documentTarget.requirement} · ${documentTarget.fileName}` : ''}
                    confirmLabel="Simpan Verifikasi"
                    processing={documentForm.processing}
                    onConfirm={submitDocument}
                    onCancel={() => setDocumentTarget(null)}
                >
                    <SelectField
                        label="Status verifikasi"
                        required
                        value={documentForm.data.status}
                        onChange={(event) => documentForm.setData('status', event.target.value)}
                        error={documentForm.errors.status}
                    >
                        <option value="verified">Terverifikasi</option>
                        <option value="rejected">Ditolak</option>
                        <option value="pending">Menunggu Verifikasi</option>
                    </SelectField>
                    <TextareaField
                        label="Catatan verifikasi"
                        rows={3}
                        placeholder="Wajib diisi bila dokumen ditolak…"
                        value={documentForm.data.notes}
                        onChange={(event) => documentForm.setData('notes', event.target.value)}
                        error={documentForm.errors.notes}
                    />
                </WorkflowModal>
            </div>
        </AdminShell>
    );
}
