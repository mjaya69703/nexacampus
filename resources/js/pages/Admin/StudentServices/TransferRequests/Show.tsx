// Detail pengajuan pindah + aksi workflow admin.
import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    CheckCircle2,
    Coins,
    Info,
    Paperclip,
    Pencil,
    ReceiptText,
    Send,
    UserCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import {
    ApprovalNote,
    DetailGrid,
    HistoryTimeline,
    NotesBlock,
    StatusBadge,
} from '../../../../components/Shared/StudentServices/ServiceUI';
import { classTypeLabel, formatRupiah, statusLabel, transferTypeLabel } from '../../../../components/Shared/StudentServices/labels';
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

type TransferRequest = {
    id: number;
    number: string;
    status: string;
    transferType: string;
    student: string;
    nim: string;
    studentProgram: string;
    faculty: string;
    fromProgram: string;
    fromFaculty: string;
    toProgram: string;
    toFaculty: string;
    fromClassType: string | null;
    toClassType: string | null;
    currentSemester: number | null;
    recommendedSemester: number | null;
    reason: string;
    studentNotes: string | null;
    adminNotes: string | null;
    academicEvaluationNotes: string | null;
    creditMappingNotes: string | null;
    financeNotes: string | null;
    attachmentUrl: string | null;
    attachmentName: string | null;
    submittedAt: string | null;
    updatedAt: string | null;
    transferFeeAmount: number;
    transferFeeDueDate: string | null;
    transferFeeInvoice: { number: string; status: string; total: number } | null;
    reviewedBy: string | null;
    reviewedAt: string | null;
    approvedBy: string | null;
    approvedAt: string | null;
    appliedBy: string | null;
    appliedAt: string | null;
    approval: { status: string; waitingMessage: string | null } | null;
    histories: History[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean };
    transfer: TransferRequest;
    urls: {
        index: string;
        markUnderReview: string;
        approve: string;
        requestRevision: string;
        reject: string;
        applyTransfer: string;
    };
};

type ActionKey = 'under_review' | 'approve' | 'revision' | 'reject' | 'apply';

const REVIEWABLE = ['submitted', 'in_approval', 'under_review', 'revision_requested'];

const ACTION_COPY: Record<ActionKey, { title: string; message: string; confirm: string; danger: boolean }> = {
    under_review: {
        title: 'Tandai Sedang Direview',
        message: 'Pengajuan pindah akan ditandai sedang ditinjau petugas.',
        confirm: 'Tandai review',
        danger: false,
    },
    approve: {
        title: 'Setujui Pengajuan Pindah',
        message: 'Lengkapi evaluasi akademik dan biaya transfer. Invoice dibuat otomatis bila biaya lebih dari nol.',
        confirm: 'Setujui pindah',
        danger: false,
    },
    revision: {
        title: 'Minta Perbaikan',
        message: 'Mahasiswa akan menerima permintaan perbaikan dan dapat mengirim ulang pengajuan.',
        confirm: 'Kirim permintaan',
        danger: false,
    },
    reject: {
        title: 'Tolak Pengajuan Pindah',
        message: 'Pengajuan pindah akan ditolak. Tuliskan alasan penolakan untuk mahasiswa.',
        confirm: 'Tolak pengajuan',
        danger: true,
    },
    apply: {
        title: 'Terapkan Transfer',
        message: 'Perubahan program studi/kelas akan diterapkan ke profil akademik mahasiswa. Pastikan biaya transfer (jika ada) sudah lunas.',
        confirm: 'Terapkan transfer',
        danger: false,
    },
};

export default function TransferRequestShow({ shell, can, transfer, urls }: Props) {
    const [action, setAction] = useState<ActionKey | null>(null);

    const notesForm = useForm({ notes: '' });
    const approveForm = useForm({
        admin_notes: transfer.adminNotes ?? '',
        academic_evaluation_notes: transfer.academicEvaluationNotes ?? '',
        credit_mapping_notes: transfer.creditMappingNotes ?? '',
        finance_notes: transfer.financeNotes ?? '',
        recommended_semester: transfer.recommendedSemester ?? '',
        transfer_fee_amount: transfer.transferFeeAmount ?? 0,
        transfer_fee_due_date: transfer.transferFeeDueDate ?? '',
    });

    const canReview = can.update && REVIEWABLE.includes(transfer.status);
    const canApply = can.update && ['approved', 'approved_pending_payment'].includes(transfer.status);
    const canReject = transfer.approval?.status === 'in_progress';
    const copy = action ? ACTION_COPY[action] : null;

    const confirmAction = () => {
        if (!action) return;

        const done = {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                notesForm.reset();
                approveForm.reset();
            },
        };

        if (action === 'approve') {
            approveForm.post(urls.approve, done);
            return;
        }

        const url = {
            under_review: urls.markUnderReview,
            revision: urls.requestRevision,
            reject: urls.reject,
            apply: urls.applyTransfer,
        }[action];

        notesForm.post(url, done);
    };

    const isClassTransfer = transfer.transferType === 'class_type';

    return (
        <AdminShell shell={shell}>
            <Head title={`${transfer.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowLeftRight}
                        eyebrow="Layanan Mahasiswa · Pindah"
                        title={transfer.number}
                        description={`${transfer.student} · ${transferTypeLabel(transfer.transferType)}`}
                        badges={[statusLabel(transfer.status)]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ArrowLeftRight size={16} /> Detail Pengajuan</h2>
                                    <StatusBadge status={transfer.status} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Mahasiswa', value: transfer.student },
                                            { label: 'NIM', value: transfer.nim },
                                            { label: 'Program Studi Saat Ini', value: transfer.studentProgram },
                                            { label: 'Fakultas', value: transfer.faculty },
                                            { label: 'Jenis Pindah', value: transferTypeLabel(transfer.transferType) },
                                            { label: 'Semester Saat Ini', value: transfer.currentSemester ?? '-' },
                                            { label: 'Semester Disarankan', value: transfer.recommendedSemester ?? '-' },
                                            { label: 'Diajukan', value: transfer.submittedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Asal → Tujuan</h2>
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Asal', value: isClassTransfer ? classTypeLabel(transfer.fromClassType) : transfer.fromProgram },
                                            { label: 'Fakultas Asal', value: isClassTransfer ? transfer.faculty : transfer.fromFaculty },
                                            { label: 'Tujuan', value: isClassTransfer ? classTypeLabel(transfer.toClassType) : transfer.toProgram },
                                            { label: 'Fakultas Tujuan', value: isClassTransfer ? transfer.faculty : transfer.toFaculty },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Alasan Pengajuan</h2>
                                </div>
                                <div className="db-card-body">
                                    <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{transfer.reason}</p>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ReceiptText size={16} /> Biaya Transfer</h2>
                                    {transfer.transferFeeInvoice && (
                                        <StatusBadge status={transfer.transferFeeInvoice.status} />
                                    )}
                                </div>
                                <div className="db-card-body">
                                    {transfer.transferFeeInvoice ? (
                                        <div className="db-invoice">
                                            <div>
                                                <b>{transfer.transferFeeInvoice.number}</b>
                                                <small>Total tagihan</small>
                                            </div>
                                            <div>
                                                <b>{formatRupiah(transfer.transferFeeInvoice.total)}</b>
                                                <small>Status: {statusLabel(transfer.transferFeeInvoice.status)}</small>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="db-invoice">
                                            <div>
                                                <b>{transfer.transferFeeAmount > 0 ? formatRupiah(transfer.transferFeeAmount) : 'Tanpa biaya'}</b>
                                                <small>
                                                    {transfer.transferFeeAmount > 0
                                                        ? `Jatuh tempo ${transfer.transferFeeDueDate ?? '-'}`
                                                        : 'Pengajuan ini tidak dikenakan biaya transfer.'}
                                                </small>
                                            </div>
                                            <span className={`db-badge ${transfer.transferFeeAmount > 0 ? 'amber' : 'green'}`}>
                                                {transfer.transferFeeAmount > 0 ? 'Menunggu Tagihan' : 'Tanpa Biaya'}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {(transfer.studentNotes || transfer.adminNotes || transfer.academicEvaluationNotes || transfer.creditMappingNotes || transfer.financeNotes) && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Pencil size={16} /> Catatan</h2>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <NotesBlock title="Catatan Mahasiswa" notes={transfer.studentNotes} />
                                        <NotesBlock title="Catatan Admin" notes={transfer.adminNotes} />
                                        <NotesBlock title="Evaluasi Akademik" notes={transfer.academicEvaluationNotes} />
                                        <NotesBlock title="Pemetaan SKS" notes={transfer.creditMappingNotes} />
                                        <NotesBlock title="Catatan Keuangan" notes={transfer.financeNotes} />
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Riwayat Status</h2>
                                </div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={transfer.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserCheck size={16} /> Tindakan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <ApprovalNote approval={transfer.approval} />

                                    {canReview && (
                                        <>
                                            {transfer.status !== 'under_review' && (
                                                <button className="db-btn ghost" type="button" onClick={() => setAction('under_review')}>
                                                    <Info size={14} /> Tandai Sedang Direview
                                                </button>
                                            )}
                                            <button className="db-btn primary" type="button" onClick={() => setAction('approve')}>
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

                                    {canApply && (
                                        <button className="db-btn primary" type="button" onClick={() => setAction('apply')}>
                                            <CheckCircle2 size={14} /> Terapkan Transfer
                                        </button>
                                    )}

                                    {!canReview && !canApply && (
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
                                    {transfer.attachmentUrl ? (
                                        <a className="db-btn ghost" href={transfer.attachmentUrl} target="_blank" rel="noreferrer">
                                            <Paperclip size={14} /> {transfer.attachmentName ?? 'Lampiran Pendukung'}
                                        </a>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada lampiran pendukung.</p>
                                    )}
                                    {transfer.transferFeeAmount > 0 && (
                                        <div className="db-note muted">
                                            <Coins size={15} />
                                            <span>Biaya transfer: <b>{formatRupiah(transfer.transferFeeAmount)}</b></span>
                                        </div>
                                    )}
                                    {transfer.reviewedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Direview oleh <b>{transfer.reviewedBy}</b> · {transfer.reviewedAt ?? '-'}
                                        </p>
                                    )}
                                    {transfer.approvedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Disetujui oleh <b>{transfer.approvedBy}</b> · {transfer.approvedAt ?? '-'}
                                        </p>
                                    )}
                                    {transfer.appliedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Diterapkan oleh <b>{transfer.appliedBy}</b> · {transfer.appliedAt ?? '-'}
                                        </p>
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <WorkflowModal
                    open={action !== null}
                    title={copy?.title ?? ''}
                    message={copy?.message ?? ''}
                    confirmLabel={copy?.confirm ?? 'Simpan'}
                    danger={copy?.danger}
                    processing={action === 'approve' ? approveForm.processing : notesForm.processing}
                    onConfirm={confirmAction}
                    onCancel={() => setAction(null)}
                >
                    {action === 'approve' ? (
                        <>
                            <TextField
                                label="Semester disarankan"
                                type="number"
                                min={1}
                                max={14}
                                value={approveForm.data.recommended_semester}
                                onChange={(event) => approveForm.setData('recommended_semester', event.target.value)}
                                error={approveForm.errors.recommended_semester}
                                hint="Opsional. Akan diterapkan ke semester mahasiswa saat transfer dijalankan."
                            />
                            <TextField
                                label="Biaya transfer (Rp)"
                                type="number"
                                min={0}
                                value={approveForm.data.transfer_fee_amount}
                                onChange={(event) => approveForm.setData('transfer_fee_amount', Number(event.target.value))}
                                error={approveForm.errors.transfer_fee_amount}
                                hint="Isi 0 bila tidak dikenakan biaya."
                            />
                            <TextField
                                label="Jatuh tempo biaya"
                                type="date"
                                value={approveForm.data.transfer_fee_due_date}
                                onChange={(event) => approveForm.setData('transfer_fee_due_date', event.target.value)}
                                error={approveForm.errors.transfer_fee_due_date}
                                hint="Wajib diisi bila biaya transfer lebih dari nol."
                            />
                            <TextareaField
                                label="Evaluasi akademik"
                                rows={2}
                                value={approveForm.data.academic_evaluation_notes}
                                onChange={(event) => approveForm.setData('academic_evaluation_notes', event.target.value)}
                                error={approveForm.errors.academic_evaluation_notes}
                            />
                            <TextareaField
                                label="Pemetaan SKS"
                                rows={2}
                                value={approveForm.data.credit_mapping_notes}
                                onChange={(event) => approveForm.setData('credit_mapping_notes', event.target.value)}
                                error={approveForm.errors.credit_mapping_notes}
                            />
                            <TextareaField
                                label="Catatan keuangan"
                                rows={2}
                                value={approveForm.data.finance_notes}
                                onChange={(event) => approveForm.setData('finance_notes', event.target.value)}
                                error={approveForm.errors.finance_notes}
                            />
                            <TextareaField
                                label="Catatan admin"
                                rows={2}
                                value={approveForm.data.admin_notes}
                                onChange={(event) => approveForm.setData('admin_notes', event.target.value)}
                                error={approveForm.errors.admin_notes}
                            />
                        </>
                    ) : (
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
            </div>
        </AdminShell>
    );
}
