import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Year = { id: number; name: string };
type App = { id: number; academicYearId: number | null; semester: number; duration: number; reasonCategory: string; reason: string; studentNotes: string | null; adminNotes: string | null; attachmentUrl: string | null };

type Props = { shell: ShellProps; application: App; academicYears: Year[]; urls: { index: string; show: string; update: string } };

export default function LeavesEdit({ shell, application, academicYears, urls }: Props) {
    const form = useForm<{
        form: { academic_year_id: string; semester: number; duration_semesters: number; reason_category: string; reason: string; student_notes: string };
        attachment: File | null;
    }>({
        form: {
            academic_year_id: String(application.academicYearId ?? ''),
            semester: application.semester,
            duration_semesters: application.duration,
            reason_category: application.reasonCategory,
            reason: application.reason ?? '',
            student_notes: application.studentNotes ?? '',
        },
        attachment: null,
    });

    const submit = (e: FormEvent) => { e.preventDefault(); form.put(urls.update, { forceFormData: true, preserveScroll: true }); };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarMinus} eyebrow="Student Services" title="Perbaiki Pengajuan Cuti" description={application.id ? `#${application.id}` : ''} />
                    <Link href={urls.show} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    {application.adminNotes && <div className="db-note warning">{application.adminNotes}</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Tahun Akademik</label>
                                <select className="db-input" value={form.data.form.academic_year_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, academic_year_id: e.target.value })}>
                                    {academicYears.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                </select>
                                <label className="db-label">Semester</label>
                                <input type="number" className="db-input" min={1} max={14} value={form.data.form.semester}
                                    onChange={(e) => form.setData('form', { ...form.data.form, semester: Number(e.target.value) })} />
                                <label className="db-label">Durasi (semester)</label>
                                <input type="number" className="db-input" min={1} max={2} value={form.data.form.duration_semesters}
                                    onChange={(e) => form.setData('form', { ...form.data.form, duration_semesters: Number(e.target.value) })} />
                                <label className="db-label">Kategori Alasan</label>
                                <select className="db-input" value={form.data.form.reason_category}
                                    onChange={(e) => form.setData('form', { ...form.data.form, reason_category: e.target.value })}>
                                    {['personal', 'medical', 'financial', 'family', 'work', 'other'].map((c) => <option key={c} value={c}>{c}</option>)}
                                </select>
                                <label className="db-label">Alasan</label>
                                <textarea className="db-input" rows={4} value={form.data.form.reason}
                                    onChange={(e) => form.setData('form', { ...form.data.form, reason: e.target.value })} />
                                <label className="db-label">Catatan Mahasiswa</label>
                                <textarea className="db-input" rows={2} value={form.data.form.student_notes}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_notes: e.target.value })} />
                                <label className="db-label">Lampiran (opsional)</label>
                                <input type="file" className="db-input" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />
                                <button type="submit" className="db-btn primary" disabled={form.processing}><Send size={14} /> Kirim Ulang</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
