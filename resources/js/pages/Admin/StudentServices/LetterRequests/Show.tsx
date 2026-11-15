// Detail pengajuan surat + aksi workflow admin.
import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    FileText,
    Info,
    MailCheck,
    Paperclip,
    Pencil,
    Send,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField } from '../../../../components/Shared/Crud/fields';
import {
    ApprovalNote,
    DetailGrid,
    HistoryTimeline,
    NotesBlock,
    StatusBadge,
} from '../../../../components/Shared/StudentServices/ServiceUI';
import { humanizeField, statusLabel } from '../../../../components/Shared/StudentServices/labels';
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

type LetterRequest = {
    id: number;
    number: string;
    status: string;
    letterType: string;
    letterTypeCode: string | null;
    fulfillmentMode: string | null;
    student: string;
    nim: string;
    studyProgram: string;
    faculty: string;
    purpose: string;
    requestData: { key: string; value: string }[];
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
    issuedBy: string | null;
    issuedAt: string | null;
    isDownloadable: boolean;
    downloadUrl: string | null;
    approval: { status: string; waitingMessage: string | null } | null;
    histories: History[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; issue: boolean };
    request: LetterRequest;
    issueMethods: { id: string; name: string }[];
    urls: {
        index: string;
        markUnderReview: string;
        approve: string;
        requestRevision: string;
        reject: string;
        issue: string;
    };
};

type ActionKey = 'under_review' | 'approve' | 'revision' | 'reject';

const ACTION_COPY: Record<ActionKey, { urlKey: 'markUnderReview' | 'approve' | 'requestRevision' | 'reject'; title: string; message: string; confirm: string; danger: boolean }> = {
    under_review: {
        urlKey: 'markUnderReview',
        title: 'Tandai Sedang Direview',
        message: 'Pengajuan akan ditandai sedang ditinjau petugas. Mahasiswa dapat melihat perubahan status ini.',
        confirm: 'Tandai review',
        danger: false,
    },
    approve: {
        urlKey: 'approve',
        title: 'Setujui Pengajuan Surat',
        message: 'Pengajuan disetujui. Setelah disetujui, surat dapat diterbitkan melalui tombol Terbitkan Surat.',
        confirm: 'Setujui pengajuan',
        danger: false,
    },
    revision: {
        urlKey: 'requestRevision',
        title: 'Minta Perbaikan',
        message: 'Mahasiswa akan menerima permintaan perbaikan dan dapat mengirim ulang pengajuan.',
        confirm: 'Kirim permintaan',
        danger: false,
    },
    reject: {
        urlKey: 'reject',
        title: 'Tolak Pengajuan',
        message: 'Pengajuan akan ditolak. Tuliskan alasan penolakan agar mahasiswa memahami keputusan.',
        confirm: 'Tolak pengajuan',
        danger: true,
    },
};

const REVIEWABLE = ['submitted', 'in_approval', 'under_review', 'revision_requested'];

export default function LetterRequestShow({ shell, can, request, issueMethods, urls }: Props) {
    const [action, setAction] = useState<ActionKey | null>(null);
    const [issueOpen, setIssueOpen] = useState(false);

    const form = useForm({ notes: '' });
    const issueForm = useForm<{ issue_method: string; final_file: File | null }>({
        issue_method: issueMethods[0]?.id ?? 'auto_generate',
        final_file: null,
    });

    const copy = action ? ACTION_COPY[action] : null;
    const canReview = can.update && REVIEWABLE.includes(request.status);

    const confirmAction = () => {
        if (!copy) return;

        form.post(urls[copy.urlKey], {
            preserveScroll: true,
            onSuccess: () => {
                setAction(null);
                form.reset();
            },
        });
    };

    const submitIssue = () => {
        issueForm.post(urls.issue, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIssueOpen(false);
                issueForm.reset();
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${request.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Layanan Mahasiswa · Pengajuan Surat"
                        title={request.number}
                        description={`${request.letterType} · ${request.student}`}
                        badges={[statusLabel(request.status)]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileText size={16} /> Detail Pengajuan</h2>
                                    <StatusBadge status={request.status} />
                                </div>
                                <div className="db-card-body">
                                    <DetailGrid
                                        items={[
                                            { label: 'Jenis Surat', value: request.letterType },
                                            { label: 'Kode Jenis', value: request.letterTypeCode ?? '-' },
                                            { label: 'Mahasiswa', value: request.student },
                                            { label: 'NIM', value: request.nim },
                                            { label: 'Program Studi', value: request.studyProgram },
                                            { label: 'Fakultas', value: request.faculty },
                                            { label: 'Diajukan', value: request.submittedAt ?? '-' },
                                            { label: 'Diperbarui', value: request.updatedAt ?? '-' },
                                        ]}
                                    />
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Pencil size={16} /> Keperluan</h2>
                                </div>
                                <div className="db-card-body">
                                    <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{request.purpose}</p>
                                </div>
                            </section>

                            {request.requestData.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Info size={16} /> Data Permohonan</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <DetailGrid
                                            items={request.requestData.map((item) => ({
                                                label: humanizeField(item.key),
                                                value: item.value,
                                            }))}
                                        />
                                    </div>
                                </section>
                            )}

                            {(request.studentNotes || request.adminNotes) && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Pencil size={16} /> Catatan</h2>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <NotesBlock title="Catatan Mahasiswa" notes={request.studentNotes} />
                                        <NotesBlock title="Catatan Admin" notes={request.adminNotes} />
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Riwayat Status</h2>
                                </div>
                                <div className="db-card-body">
                                    <HistoryTimeline histories={request.histories} />
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><MailCheck size={16} /> Tindakan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <ApprovalNote approval={request.approval} />

                                    {canReview && (
                                        <>
                                            {request.status !== 'under_review' && (
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
                                            <button className="db-btn danger" type="button" onClick={() => setAction('reject')}>
                                                <XCircle size={14} /> Tolak Pengajuan
                                            </button>
                                        </>
                                    )}

                                    {can.issue && (
                                        <button className="db-btn primary" type="button" onClick={() => setIssueOpen(true)}>
                                            <MailCheck size={14} /> Terbitkan Surat
                                        </button>
                                    )}

                                    {request.isDownloadable && request.downloadUrl && (
                                        <a className="db-btn ghost" href={request.downloadUrl}>
                                            <Download size={14} /> Unduh Surat
                                        </a>
                                    )}

                                    {!canReview && !can.issue && !request.isDownloadable && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Tidak ada tindakan yang tersedia pada status ini.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Berkas & Penerbitan</h2>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {request.attachmentUrl ? (
                                        <a className="db-btn ghost" href={request.attachmentUrl} target="_blank" rel="noreferrer">
                                            <Paperclip size={14} /> {request.attachmentName ?? 'Lampiran Pendukung'}
                                        </a>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada lampiran pendukung.</p>
                                    )}
                                    {request.reviewedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Direview oleh <b>{request.reviewedBy}</b> · {request.reviewedAt ?? '-'}
                                        </p>
                                    )}
                                    {request.approvedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Disetujui oleh <b>{request.approvedBy}</b> · {request.approvedAt ?? '-'}
                                        </p>
                                    )}
                                    {request.issuedBy && (
                                        <p className="db-hint" style={{ margin: 0 }}>
                                            Diterbitkan oleh <b>{request.issuedBy}</b> · {request.issuedAt ?? '-'}
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
                    processing={form.processing}
                    onConfirm={confirmAction}
                    onCancel={() => setAction(null)}
                >
                    <TextareaField
                        label="Catatan untuk mahasiswa"
                        rows={4}
                        placeholder="Opsional. Tuliskan catatan review atau alasan keputusan…"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        error={form.errors.notes}
                    />
                </WorkflowModal>

                <WorkflowModal
                    open={issueOpen}
                    title="Terbitkan Surat"
                    message="Pilih metode penerbitan. Surat yang diterbitkan dapat diunduh oleh mahasiswa."
                    confirmLabel="Terbitkan"
                    processing={issueForm.processing}
                    onConfirm={submitIssue}
                    onCancel={() => setIssueOpen(false)}
                >
                    <SelectField
                        label="Metode penerbitan"
                        required
                        value={issueForm.data.issue_method}
                        onChange={(event) => issueForm.setData('issue_method', event.target.value)}
                        error={issueForm.errors.issue_method}
                    >
                        {issueMethods.map((method) => (
                            <option key={method.id} value={method.id}>{method.name}</option>
                        ))}
                    </SelectField>

                    {issueForm.data.issue_method === 'manual_upload' ? (
                        <div>
                            <label className="crud-label" htmlFor="final-file">Berkas surat final <span>*</span></label>
                            <input
                                id="final-file"
                                className="db-input"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(event) => issueForm.setData('final_file', event.target.files?.[0] ?? null)}
                            />
                            {issueForm.errors.final_file && <div className="db-error">{issueForm.errors.final_file}</div>}
                            <div className="crud-hint">PDF/JPG/PNG, maksimal 5 MB.</div>
                        </div>
                    ) : (
                        <div className="db-note info">
                            <Info size={15} />
                            <span>Sistem akan mencetak surat secara otomatis menggunakan template jenis surat ini.</span>
                        </div>
                    )}
                </WorkflowModal>
            </div>
        </AdminShell>
    );
}
