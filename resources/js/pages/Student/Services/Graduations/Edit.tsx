import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Batch = { id: number; label: string; academicPeriod: string | null; yudisiumDate: string | null };
type Req = { id: number; label: string; description: string | null; documentType: string; isRequired: boolean; allowedExtensions: string[]; maxSizeKb: number };
type Check = { label: string; required: boolean; passed: boolean; actual: any; expected: any; message: string };
type EligibilityReport = { checks: Record<string, Check>; can_submit: boolean };
type App = { id: number; number: string; graduationBatch: number | null; thesisTitle: string | null; reason: string | null; studentNotes: string | null; adminNotes: string | null; attachmentUrl: string | null };
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
            graduation_batch_id: String(application.graduationBatch ?? ''),
            thesis_title: application.thesisTitle ?? '',
            reason: application.reason ?? '',
            student_notes: application.studentNotes ?? '',
        },
        attachment: null,
        documentUploads: {},
    });

    const submit = (e: FormEvent) => { e.preventDefault(); form.put(urls.update, { forceFormData: true, preserveScroll: true }); };

    const checks = eligibilityReport?.checks ? Object.values(eligibilityReport.checks) : [];
    const docMap = new Map(existingDocuments.map((d) => [d.requirementId, d]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={GraduationCap} eyebrow="Student Services" title="Perbaiki Pengajuan Yudisium" description={application.number} />
                    <Link href={urls.show} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    {application.adminNotes && <div className="db-note warning">{application.adminNotes}</div>}
                    {!canSubmit && <div className="db-note warning">Pengajuan belum bisa dikirim ulang.</div>}
                    {checks.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', gap: 8, gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))' }}>
                                {checks.map((c, i) => (
                                    <div key={i} className="db-note" style={{ background: c.passed ? '#ecfdf5' : '#fffbeb' }}>
                                        <b>{c.label}</b><div style={{ fontSize: 12 }}>{c.message}</div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Batch Yudisium</label>
                                <select className="db-input" value={form.data.form.graduation_batch_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, graduation_batch_id: e.target.value })}>
                                    <option value="">Pilih batch</option>
                                    {graduationBatches.map((b) => <option key={b.id} value={b.id}>{b.label}</option>)}
                                </select>

                                <label className="db-label">Judul Skripsi / Tugas Akhir</label>
                                <input className="db-input" value={form.data.form.thesis_title}
                                    onChange={(e) => form.setData('form', { ...form.data.form, thesis_title: e.target.value })} />

                                <label className="db-label">Lampiran Pendukung</label>
                                <input type="file" className="db-input" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />

                                {documentRequirements.length > 0 && (
                                    <div style={{ display: 'grid', gap: 12 }}>
                                        <b>Dokumen Yudisium</b>
                                        {documentRequirements.map((r) => {
                                            const existing = docMap.get(r.id);
                                            return (
                                                <div key={r.id} style={{ border: '1px solid #eee', padding: 10, borderRadius: 8 }}>
                                                    <div><b>{r.label}</b> {r.isRequired ? '(wajib)' : '(opsional)'}</div>
                                                    {existing && <div style={{ fontSize: 12 }}>Sudah ada: {existing.fileName} ({existing.verificationStatus})</div>}
                                                    <input type="file" className="db-input" accept={r.allowedExtensions.map((e) => '.' + e).join(',')}
                                                        onChange={(e) => form.setData('documentUploads', { ...form.data.documentUploads, [r.id]: e.target.files?.[0] ?? null })} />
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                <label className="db-label">Catatan / Keterangan</label>
                                <textarea className="db-input" rows={4} value={form.data.form.reason}
                                    onChange={(e) => form.setData('form', { ...form.data.form, reason: e.target.value })} />

                                <label className="db-label">Catatan Tambahan</label>
                                <textarea className="db-input" rows={3} value={form.data.form.student_notes}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_notes: e.target.value })} />

                                <button type="submit" className="db-btn primary" disabled={form.processing || !canSubmit}><Send size={14} /> Kirim Ulang</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
