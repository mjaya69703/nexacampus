import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Info, Paperclip, Send, ShieldCheck, TriangleAlert } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { ChecklistItem } from '../ServiceUI';
import { formatFileSize } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Batch = { id: number; label: string; academicPeriod: string | null; yudisiumDate: string | null };
type Req = { id: number; label: string; description: string | null; documentType: string; isRequired: boolean; allowedExtensions: string[]; maxSizeKb: number };
type Check = { label: string; required: boolean; passed: boolean; actual: unknown; expected: unknown; message: string };
type EligibilityReport = { checks: Record<string, Check>; can_submit: boolean; snapshot?: Record<string, unknown> };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    graduationBatches: Batch[];
    eligibilityReport: EligibilityReport;
    canSubmit: boolean;
    documentRequirements: Req[];
    urls: { index: string; store: string };
};

export default function GraduationsCreate({ shell, hasProfile, graduationBatches, eligibilityReport, canSubmit, documentRequirements, urls }: Props) {
    const form = useForm<{
        form: { graduation_batch_id: string; thesis_title: string; reason: string; student_notes: string };
        attachment: File | null;
        documentUploads: Record<string, File | null>;
    }>({
        form: {
            graduation_batch_id: graduationBatches.length === 1 ? String(graduationBatches[0].id) : '',
            thesis_title: '',
            reason: '',
            student_notes: '',
        },
        attachment: null,
        documentUploads: {},
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    const checks = eligibilityReport?.checks ? Object.values(eligibilityReport.checks) : [];
    const passed = checks.filter((check) => check.passed).length;
    const progress = checks.length > 0 ? Math.round((passed / checks.length) * 100) : 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Student Services · Yudisium"
                        title="Ajukan Yudisium"
                        description="Pastikan seluruh syarat terpenuhi, pilih batch, lalu unggah dokumen yang diminta."
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <Info size={15} />
                            <span>Profil mahasiswa belum ditemukan. Hubungi admin akademik sebelum mengajukan yudisium.</span>
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
                                    <span>Pengajuan belum bisa dikirim. Selesaikan syarat yang belum terpenuhi terlebih dahulu.</span>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Formulir Yudisium</h2></div>
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
                                    {graduationBatches.length === 0 && (
                                        <p className="db-hint" style={{ marginTop: 6 }}>Belum ada batch yudisium yang dibuka saat ini.</p>
                                    )}
                                    {form.errors['form.graduation_batch_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.graduation_batch_id']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Judul Skripsi / Tugas Akhir</label>
                                    <input
                                        className="db-input"
                                        value={form.data.form.thesis_title}
                                        onChange={(event) => form.setData('form', { ...form.data.form, thesis_title: event.target.value })}
                                        placeholder="Judul final skripsi atau tugas akhir…"
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
                                        placeholder="Keterangan tambahan terkait pengajuan yudisium…"
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
                                        placeholder="Informasi tambahan untuk petugas (opsional)…"
                                    />
                                    {form.errors['form.student_notes'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.student_notes']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Lampiran Pendukung</label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        <Paperclip size={12} /> Format PDF, JPG, atau PNG. Maksimal 5 MB.
                                    </p>
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                {documentRequirements.length > 0 && (
                                    <div className="db-form">
                                        <span className="db-label">Dokumen Yudisium</span>
                                        <div className="db-file-list">
                                            {documentRequirements.map((requirement) => (
                                                <div className="db-file" key={requirement.id} style={{ display: 'block' }}>
                                                    <div>
                                                        <b>{requirement.label} {requirement.isRequired ? <span className="db-badge amber">Wajib</span> : <span className="db-badge gray">Opsional</span>}</b>
                                                        {requirement.description && <small>{requirement.description}</small>}
                                                        <small>
                                                            Format: {requirement.allowedExtensions.map((extension) => extension.toUpperCase()).join(', ')}
                                                            {' '}· Maksimal {formatFileSize(requirement.maxSizeKb)}
                                                        </small>
                                                    </div>
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
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="db-note info">
                                    <Info size={15} />
                                    <span>Setelah dikirim, pengajuan akan diperiksa admin akademik. Kamu bisa memantau status dan verifikasi dokumen di halaman detail.</span>
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing || !canSubmit || !hasProfile}>
                                        <Send size={14} /> {form.processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                                    </button>
                                    <Link href={urls.index} className="db-btn ghost">Batal</Link>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
