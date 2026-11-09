// Detail pengajuan cuti + aksi workflow admin.
import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarMinus,
    CheckCircle2,
    Coins,
    Info,
    Paperclip,
    Pencil,
    ReceiptText,
    RotateCcw,
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
import { formatRupiah, statusLabel } from '../../../../components/Shared/StudentServices/labels';
import { WorkflowModal, leaveReasonLabel } from '../shared';
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

type LeaveApplication = {
    id: number;
    number: string;
    status: string;
    student: string;
    nim: string;
    studyProgram: string;
    faculty: string;
    academicYear: string;
    semester: number | null;
    durationSemesters: number;
    reasonCategory: string;
    reason: string;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    attachmentName: string | null;
    submittedAt: string | null;
    updatedAt: string | null;
    leaveFeeAmount: number;
    leaveFeeDueDate: string | null;
    leaveFeeInvoice: { number: string; status: string; total: number } | null;
    reviewedBy: string | null;
    reviewedAt: string | null;
    approvedBy: string | null;
    approvedAt: string | null;
    activatedBy: string | null;
    activatedAt: string | null;
    returnedBy: string | null;
    returnedAt: string | null;
    approval: { status: string; waitingMessage: string | null } | null;
    histories: History[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean };
    application: LeaveApplication;
    urls: {
        index: string;
        markUnderReview: string;
        approve: string;
        requestRevision: string;
        reject: string;
        activate: string;
        returnToActive: string;
    };
};

type ActionKey = 'under_review' | 'approve' | 'revision' | 'reject' | 'activate' | 'return';

const REVIEWABLE = ['submitted', 'in_approval', 'under_review', 'revision_requested'];

const ACTION_COPY: Record<ActionKey, { title: string; message: string; confirm: string; danger: boolean }> = {
    under_review: {
        title: 'Tandai Sedang Direview',
        message: 'Pengajuan cuti akan ditandai sedang ditinjau petugas.',
        confirm: 'Tandai review',
        danger: false,
    },
    approve: {
        title: 'Setujui Pengajuan Cuti',
        message: 'Tentukan biaya cuti. Jika biaya lebih dari nol, invoice akan diterbitkan otomatis setelah persetujuan.',
        confirm: 'Setujui cuti',
        danger: false,
    },
    revision: {
        title: 'Minta Perbaikan',
        message: 'Mahasiswa akan menerima permintaan perbaikan dan dapat mengirim ulang pengajuan cuti.',
        confirm: 'Kirim permintaan',
        danger: false,
    },
    reject: {
        title: 'Tolak Pengajuan Cuti',
        message: 'Pengajuan cuti akan ditolak. Tuliskan alasan penolakan untuk mahasiswa.',
        confirm: 'Tolak pengajuan',
        danger: true,
    },
    activate: {
        title: 'Aktifkan Cuti',
        message: 'Status akademik mahasiswa akan diubah menjadi Cuti dan profil dinonaktifkan sementara. Pastikan biaya cuti (jika ada) sudah lunas.',
        confirm: 'Aktifkan cuti',
        danger: false,
    },
    return: {
        title: 'Kembalikan ke Status Aktif',
        message: 'Mahasiswa akan dikembalikan ke status akademik Aktif dan profil diaktifkan kembali.',
        confirm: 'Kembalikan aktif',
        danger: false,
    },
};

export default function LeaveApplicationShow({ shell, can, application, urls }: Props) {
    const [action, setAction] = useState<ActionKey | null>(null);

    const notesForm = useForm({ notes: '' });
    const approveForm = useForm({
        notes: '',
        leave_fee_amount: application.leaveFeeAmount ?? 0,
        leave_fee_due_date: application.leaveFeeDueDate ?? '',
    });

    const canReview = can.update && REVIEWABLE.includes(application.status);
    const canActivate = can.update && ['approved', 'approved_pending_payment'].includes(application.status);
    const canReturn = can.update && application.status === 'activated';
    const canReject = application.approval?.status === 'in_progress';

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
            activate: urls.activate,
            return: urls.returnToActive,
        }[action];

        notesForm.post(url, done);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${application.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Layanan Mahasiswa · Cuti Akademik"
                        title={application.number}
                        description={`${application.student} · ${application.studyProgram}`}
                        badges={[statusLabel(application.status)]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarMinus size={16} /> Detail Pengajuan</h2>
                                    <StatusBadge status={application.status} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Mahasiswa', value: application.student },
                                            { label: 'NIM', value: application.nim },
                                            { label: 'Program Studi', value: application.studyProgram },
                                            { label: 'Fakultas', value: application.faculty },
                                            { label: 'Tahun Akademik', value: application.academicYear },
                                            { label: 'Semester', value: application.semester ?? '-' },
                                            { label: 'Durasi Cuti', value: `${application.durationSemesters} semester` },
                                            { label: 'Kategori Alasan', value: leaveReasonLabel(application.reasonCategory) },
                                            { label: 'Diajukan', value: application.submittedAt ?? '-' },
                                            { label: 'Diperbarui', value: application.updatedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Alasan Pengajuan</h2>
                                </div>
                                <div className="db-card-body">
                                    <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{application.reason}</p>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ReceiptText size={16} /> Biaya Cuti</h2>
                                    {application.leaveFeeInvoice && (
                                        <StatusBadge status={application.leaveFeeInvoice.status} />
                                    )}
                                </div>
                                <div className="db-card-body">
                                    {application.leaveFeeInvoice ? (
                                        <div className="db-invoice">
                                            <div>
                                                <b>{application.leaveFeeInvoice.number}</b>
                                                <small>Total tagihan</small>
                                            </div>
                                            <div>
                                                <b>{formatRupiah(application.leaveFeeInvoice.total)}</b>
                                                <small>Status: {statusLabel(application.leaveFeeInvoice.status)}</small>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="db-invoice">
                                            <div>
                                                <b>{application.leaveFeeAmount > 0 ? formatRupiah(application.leaveFeeAmount) : 'Tanpa biaya'}</b>
                                                <small>
                                                    {application.leaveFeeAmount > 0
                                                        ? `Jatuh tempo ${application.leaveFeeDueDate ?? '-'}`
                                                        : 'Pengajuan ini tidak dikenakan biaya cuti.'}
                                                </small>
                                            </div>
                                            <span className={`db-badge ${application.leaveFeeAmount > 0 ? 'amber' : 'green'}`}>
                                                {application.leaveFeeAmount > 0 ? 'Menunggu Tagihan' : 'Tanpa Biaya'}
                                            </span>
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
                                    <h2 className="db-card-title"><UserCheck size={16} /> Tindakan</h2>
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

                                    {canActivate && (
                                        <button className="db-btn primary" type="button" onClick={() => setAction('activate')}>
                                            <CheckCircle2 size={14} /> Aktifkan Cuti
                                        </button>
                                    )}

                                    {canReturn && (
                                        <button className="db-btn primary" type="button" onClick={() => setAction('return')}>
                                            <RotateCcw size={14} /> Kembalikan ke Aktif
                                        </button>
                                    )}

                                    {!canReview && !canActivate && !canReturn && (
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
                                    {application.leaveFeeAmount > 0 && (
                                        <div className="db-note muted">
                                            <Coins size={15} />
                                            <span>Biaya cuti: <b>{formatRupiah(application.leaveFeeAmount)}</b></span>
                                        </div>
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
                                    {application.activatedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Diaktifkan oleh <b>{application.activatedBy}</b> · {application.activatedAt ?? '-'}
                                        </p>
                                    )}
                                    {application.returnedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Dikembalikan oleh <b>{application.returnedBy}</b> · {application.returnedAt ?? '-'}
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
                                label="Biaya cuti (Rp)"
                                type="number"
                                min={0}
                                value={approveForm.data.leave_fee_amount}
                                onChange={(event) => approveForm.setData('leave_fee_amount', Number(event.target.value))}
                                error={approveForm.errors.leave_fee_amount}
                                hint="Isi 0 bila pengajuan cuti tidak dikenakan biaya."
                            />
                            <TextField
                                label="Jatuh tempo biaya"
                                type="date"
                                value={approveForm.data.leave_fee_due_date}
                                onChange={(event) => approveForm.setData('leave_fee_due_date', event.target.value)}
                                error={approveForm.errors.leave_fee_due_date}
                                hint="Wajib diisi bila biaya cuti lebih dari nol."
                            />
                            <TextareaField
                                label="Catatan untuk mahasiswa"
                                rows={3}
                                placeholder="Opsional…"
                                value={approveForm.data.notes}
                                onChange={(event) => approveForm.setData('notes', event.target.value)}
                                error={approveForm.errors.notes}
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
