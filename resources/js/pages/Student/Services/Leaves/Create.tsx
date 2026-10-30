import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus, Info, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { reasonLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Year = { id: number; name: string };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    academicYears: Year[];
    currentSemester: number;
    urls: { index: string; store: string };
};

const REASON_OPTIONS = ['personal', 'medical', 'financial', 'family', 'work', 'other'];

export default function LeavesCreate({ shell, hasProfile, academicYears, currentSemester, urls }: Props) {
    const form = useForm<{
        form: {
            academic_year_id: string;
            semester: number;
            duration_semesters: number;
            reason_category: string;
            reason: string;
            student_notes: string;
        };
        attachment: File | null;
    }>({
        form: {
            academic_year_id: '',
            semester: currentSemester,
            duration_semesters: 1,
            reason_category: 'personal',
            reason: '',
            student_notes: '',
        },
        attachment: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Student Services · Cuti"
                        title="Ajukan Cuti Akademik"
                        description="Lengkapi data cuti, alasan, dan dokumen pendukung."
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan. Hubungi admin akademik sebelum mengajukan cuti.</div>}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Formulir Cuti</h2></div>
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
                                        {form.errors['form.semester'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.semester']}</div>}
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
                                        {form.errors['form.duration_semesters'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.duration_semesters']}</div>}
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
                                        placeholder="Jelaskan alasan pengajuan cuti secara jelas…"
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
                                        placeholder="Informasi tambahan yang perlu diketahui petugas (opsional)…"
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
                                    <p className="db-hint" style={{ marginTop: 6 }}>Format PDF, JPG, atau PNG. Maksimal 5 MB. Contoh: surat keterangan dokter.</p>
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                <div className="db-note info">
                                    <Info size={15} />
                                    <span>Pastikan data sudah benar. Pengajuan akan diteruskan ke admin akademik untuk ditinjau.</span>
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing || !hasProfile}>
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
