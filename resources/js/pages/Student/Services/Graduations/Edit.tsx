import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Info, Paperclip, Send, ShieldCheck, TriangleAlert } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { ChecklistItem, StatusBadge } from '../ServiceUI';
import { formatFileSize } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Batch = { id: number; label: string; academicPeriod: string | null; yudisiumDate: string | null };
type Req = { id: number; label: string; description: string | null; documentType: string; isRequired: boolean; allowedExtensions: string[]; maxSizeKb: number };
type Check = { label: string; required: boolean; passed: boolean; actual: unknown; expected: unknown; message: string };
type EligibilityReport = { checks: Record<string, Check>; can_submit: boolean };
type App = {
    id: number;
    number: string;
    graduationBatchId: number | null;
    thesisTitle: string | null;
    reason: string | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
};
type ExistingDoc = { id: number; requirementId: number; fileName: string; verificationStatus: string; verificationNotes: string | null; previewUrl: string };

type Props = {
    shell: ShellProps;
    application: App;
    graduationBatches: Batch[];
    eligibilityReport: EligibilityReport;
    canSubmit: boolean;
    documentRequirements: Req[];
    existingDocuments: ExistingDoc[];
    urls: { index: string; show: string; update: string };
};

export default function GraduationsEdit({ shell, application, graduationBatches, eligibilityReport, canSubmit, documentRequirements, existingDocuments, urls }: Props) {
    const form = useForm<{
        form: { graduation_batch_id: string; thesis_title: string; reason: string; student_notes: string };
        attachment: File | null;
        documentUploads: Record<string, File | null>;
    }>({
        form: {
            graduation_batch_id: String(application.graduationBatchId ?? ''),
            thesis_title: application.thesisTitle ?? '',
            reason: application.reason ?? '',
            student_notes: application.studentNotes ?? '',
        },
        attachment: null,
        documentUploads: {},
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { forceFormData: true, preserveScroll: true });
    };

    const checks = eligibilityReport?.checks ? Object.values(eligibilityReport.checks) : [];
    const passed = checks.filter((check) => check.passed).length;
    const progress = checks.length > 0 ? Math.round((passed / checks.length) * 100) : 0;
    const docMap = new Map(existingDocuments.map((document) => [document.requirementId, document]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Student Services · Yudisium"
                        title="Perbaiki Pengajuan Yudisium"
                        description={application.number}
                        actions={<Link href={urls.show} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {application.adminNotes && (
                        <div className="db-note warning">
                            <Info size={15} />
                            <span><b>Catatan admin:</b> {application.adminNotes}</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ShieldCheck size={16} /> Kelayakan Yudisium</h2>
                            <span className="db-badge gray">{passed}/{checks.length} terpenuhi</span>
                        </div>
                        <div className="db-card-body">
                            {checks.length === 0 ? (
                                <p className="db-hint">Belum ada data kelayakan yang bisa ditampilkan.</p>
                            ) : (
                                <>
                                    <div className="db-progress" aria-hidden="true"><i style={{ width: `${progress}%` }} /></div>
                                    <div className="db-checklist" style={{ marginTop: 10 }}>
                                        {checks.map((check, index) => (
                                            <ChecklistItem
                                                key={`${check.label}-${index}`}
                                                checked={check.passed}
                                                label={check.label}
                                                meta={check.message}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                            {!canSubmit && (
                                <div className="db-note warning" style={{ marginTop: 12 }}>
                                    <TriangleAlert size={15} />
                                    <span>Pengajuan belum bisa dikirim ulang. Selesaikan syarat yang belum terpenuhi terlebih dahulu.</span>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Formulir Perbaikan</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                <div>
                                    <label className="db-label">Batch Yudisium <span>*</span></label>
                                    <select
                                        className="db-input"
                                        value={form.data.form.graduation_batch_id}
                                        onChange={(event) => form.setData('form', { ...form.data.form, graduation_batch_id: event.target.value })}
                                    >
                                        <option value="">Pilih batch yudisium</option>
                                        {graduationBatches.map((batch) => <option key={batch.id} value={batch.id}>{batch.label}</option>)}
                                    </select>
                                    {form.errors['form.graduation_batch_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.graduation_batch_id']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Judul Skripsi / Tugas Akhir</label>
                                    <input
                                        className="db-input"
                                        value={form.data.form.thesis_title}
                                        onChange={(event) => form.setData('form', { ...form.data.form, thesis_title: event.target.value })}
                                    />
                                    {form.errors['form.thesis_title'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.thesis_title']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Catatan / Keterangan</label>
                                    <textarea
                                        className="db-input"
                                        rows={4}
                                        value={form.data.form.reason}
                                        onChange={(event) => form.setData('form', { ...form.data.form, reason: event.target.value })}
                                    />
                                    {form.errors['form.reason'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.reason']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Catatan Mahasiswa</label>
                                    <textarea
                                        className="db-input"
                                        rows={3}
                                        value={form.data.form.student_notes}
                                        onChange={(event) => form.setData('form', { ...form.data.form, student_notes: event.target.value })}
                                    />
                                    {form.errors['form.student_notes'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.student_notes']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Lampiran Pengganti</label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        <Paperclip size={12} /> Kosongkan jika lampiran sebelumnya masih valid. Format PDF/JPG/PNG, maksimal 5 MB.
                                    </p>
                                    {application.attachmentUrl && (
                                        <a href={application.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm" style={{ marginTop: 8 }}>
                                            <Paperclip size={13} /> Lihat lampiran saat ini
                                        </a>
                                    )}
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                {documentRequirements.length > 0 && (
                                    <div className="db-form">
                                        <span className="db-label">Dokumen Yudisium</span>
                                        <div className="db-file-list">
                                            {documentRequirements.map((requirement) => {
                                                const existing = docMap.get(requirement.id);
                                                const rejected = existing?.verificationStatus === 'rejected';
                                                const hasValidExisting = Boolean(existing) && !rejected;

                                                return (
                                                    <div className="db-file" key={requirement.id} style={{ display: 'block' }}>
                                                        <div>
                                                            <b>
                                                                {requirement.label}{' '}
                                                                {requirement.isRequired ? <span className="db-badge amber">Wajib</span> : <span className="db-badge gray">Opsional</span>}
                                                            </b>
                                                            {requirement.description && <small>{requirement.description}</small>}
                                                            <small>
                                                                Format: {requirement.allowedExtensions.map((extension) => extension.toUpperCase()).join(', ')}
                                                                {' '}· Maksimal {formatFileSize(requirement.maxSizeKb)}
                                                            </small>
                                                        </div>

                                                        {existing && (
                                                            <div className="db-file" style={{ marginTop: 8 }}>
                                                                <div>
                                                                    <b>{existing.fileName}</b>
                                                                    <small>
                                                                        {hasValidExisting
                                                                            ? 'Dokumen sudah diunggah dan tidak perlu diganti.'
                                                                            : 'Dokumen ditolak. Unggah berkas pengganti.'}
                                                                    </small>
                                                                    {existing.verificationNotes && <small>{existing.verificationNotes}</small>}
                                                                </div>
                                                                <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                                                                    <StatusBadge status={existing.verificationStatus} />
                                                                    <a href={existing.previewUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm">Lihat</a>
                                                                </div>
                                                            </div>
                                                        )}

                                                        <input
                                                            type="file"
                                                            className="db-input"
                                                            style={{ marginTop: 8 }}
                                                            accept={requirement.allowedExtensions.map((extension) => `.${extension}`).join(',')}
                                                            onChange={(event) => form.setData('documentUploads', {
                                                                ...form.data.documentUploads,
                                                                [requirement.id]: event.target.files?.[0] ?? null,
                                                            })}
                                                        />
                                                        {form.errors[`documentUploads.${requirement.id}`] && (
                                                            <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors[`documentUploads.${requirement.id}`]}</div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                <div className="db-note info">
                                    <Info size={15} />
                                    <span>Pastikan data dan dokumen sudah benar. Setelah dikirim ulang, pengajuan akan diperiksa kembali oleh admin akademik.</span>
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing || !canSubmit}>
                                        <Send size={14} /> {form.processing ? 'Mengirim…' : 'Kirim Ulang'}
                                    </button>
                                    <Link href={urls.show} className="db-btn ghost">Batal</Link>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
