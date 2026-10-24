import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
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

export default function LeavesCreate({ shell, academicYears, currentSemester, urls }: Props) {
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

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarMinus} eyebrow="Student Services" title="Ajukan Cuti Akademik" description="Isi semester, durasi, alasan, dan lampiran." />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Tahun Akademik</label>
                                <select className="db-input" value={form.data.form.academic_year_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, academic_year_id: e.target.value })}>
                                    <option value="">Pilih tahun akademik</option>
                                    {academicYears.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                </select>
                                {form.errors['form.academic_year_id'] && <div className="db-note danger">{form.errors['form.academic_year_id']}</div>}

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
                                {form.errors['form.reason'] && <div className="db-note danger">{form.errors['form.reason']}</div>}

                                <label className="db-label">Catatan Mahasiswa</label>
                                <textarea className="db-input" rows={2} value={form.data.form.student_notes}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_notes: e.target.value })} />

                                <label className="db-label">Lampiran (opsional)</label>
                                <input type="file" className="db-input" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />

                                <button type="submit" className="db-btn primary" disabled={form.processing}><Send size={14} /> Kirim</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
