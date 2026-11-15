import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus, Info, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { reasonLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Year = { id: number; name: string };
type App = {
    id: number;
    academicYearId: number | null;
    semester: number;
    duration: number;
    reasonCategory: string;
    reason: string;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
};

type Props = { shell: ShellProps; application: App; academicYears: Year[]; urls: { index: string; show: string; update: string } };

const REASON_OPTIONS = ['personal', 'medical', 'financial', 'family', 'work', 'other'];

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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Student Services · Cuti"
                        title="Perbaiki Pengajuan Cuti"
                        description={`Pengajuan #${application.id}`}
                        actions={<Link href={urls.show} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {application.adminNotes && (
                        <div className="db-note warning">
                            <Info size={15} />
                            <span><b>Catatan admin:</b> {application.adminNotes}</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Formulir Perbaikan</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                <div>
                                    <label className="db-label">Tahun Akademik <span>*</span></label>
                                    <select
                                        className="db-input"
                                        value={form.data.form.academic_year_id}
                                        onChange={(event) => form.setData('form', { ...form.data.form, academic_year_id: event.target.value })}
                                    >
                                        <option value="">Pilih tahun akademik</option>
                                        {academicYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </select>
                                    {form.errors['form.academic_year_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.academic_year_id']}</div>}
                                </div>

                                <div className="db-form-row">
                                    <div>
                                        <label className="db-label">Semester <span>*</span></label>
                                        <input
                                            type="number"
                                            className="db-input"
                                            min={1}
                                            max={14}
                                            value={form.data.form.semester}
                                            onChange={(event) => form.setData('form', { ...form.data.form, semester: Number(event.target.value) })}
                                        />
                                    </div>
                                    <div>
                                        <label className="db-label">Durasi Cuti <span>*</span></label>
                                        <select
                                            className="db-input"
                                            value={form.data.form.duration_semesters}
                                            onChange={(event) => form.setData('form', { ...form.data.form, duration_semesters: Number(event.target.value) })}
                                        >
                                            <option value={1}>1 semester</option>
                                            <option value={2}>2 semester</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="db-label">Kategori Alasan <span>*</span></label>
                                    <select
                                        className="db-input"
                                        value={form.data.form.reason_category}
                                        onChange={(event) => form.setData('form', { ...form.data.form, reason_category: event.target.value })}
                                    >
                                        {REASON_OPTIONS.map((category) => <option key={category} value={category}>{reasonLabel(category)}</option>)}
                                    </select>
                                </div>

                                <div>
                                    <label className="db-label">Alasan Cuti <span>*</span></label>
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
                                </div>

                                <div>
                                    <label className="db-label">Lampiran Pengganti</label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>Kosongkan jika lampiran sebelumnya masih valid. Format PDF/JPG/PNG, maksimal 5 MB.</p>
                                    {application.attachmentUrl && (
                                        <a href={application.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm" style={{ marginTop: 8 }}>
                                            Lihat lampiran saat ini
                                        </a>
                                    )}
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing}>
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
