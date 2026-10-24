import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Faculty = { id: number; name: string };
type Program = { id: number; name: string; facultyId: number; facultyName: string | null };
type ClassOption = { value: string; label: string };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    currentProgramId: number | null;
    currentClassType: string | null;
    currentFacultyId: number | null;
    currentSemester: number | null;
    faculties: Faculty[];
    studyPrograms: Program[];
    classTypeOptions: ClassOption[];
    urls: { index: string; store: string };
};

export default function TransfersCreate({ shell, currentProgramId, currentClassType, currentFacultyId, currentSemester, faculties, studyPrograms, classTypeOptions, urls }: Props) {
    const [transferType, setTransferType] = useState<string>('study_program');
    const [targetFacultyId, setTargetFacultyId] = useState<string>('');

    const filteredPrograms = useMemo(() => {
        if (transferType === 'study_program') {
            return studyPrograms.filter((p) => p.facultyId === currentFacultyId && p.id !== currentProgramId);
        }
        if (transferType === 'faculty') {
            return studyPrograms.filter((p) => Number(p.facultyId) === Number(targetFacultyId));
        }
        if (transferType === 'class_type') {
            return studyPrograms.filter((p) => p.id === currentProgramId);
        }
        return studyPrograms.filter((p) => p.id !== currentProgramId);
    }, [transferType, targetFacultyId, studyPrograms, currentFacultyId, currentProgramId]);

    const form = useForm<{
        form: {
            to_study_program_id: string;
            target_faculty_id: string;
            to_class_type: string;
            recommended_semester: number | null;
            transfer_type: string;
            reason: string;
            student_notes: string;
        };
        attachment: File | null;
    }>({
        form: {
            to_study_program_id: '',
            target_faculty_id: '',
            to_class_type: '',
            recommended_semester: currentSemester,
            transfer_type: 'study_program',
            reason: '',
            student_notes: '',
        },
        attachment: null,
    });

    const changeType = (t: string) => {
        setTransferType(t);
        form.setData('form', {
            ...form.data.form,
            transfer_type: t,
            target_faculty_id: '',
            to_study_program_id: t === 'class_type' ? String(currentProgramId ?? '') : '',
            to_class_type: '',
        });
    };

    const changeFaculty = (fid: string) => {
        setTargetFacultyId(fid);
        form.setData('form', { ...form.data.form, target_faculty_id: fid, to_study_program_id: '' });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ArrowRightLeft} eyebrow="Student Services" title="Ajukan Pindah Program" description="Pilih jenis pindah, tujuan, dan alasan." />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Jenis Pindah</label>
                                <select className="db-input" value={transferType} onChange={(e) => changeType(e.target.value)}>
                                    <option value="study_program">Pindah Program Studi</option>
                                    <option value="faculty">Pindah Fakultas</option>
                                    <option value="class_type">Pindah Kelas</option>
                                    <option value="other">Lainnya</option>
                                </select>
                                <input type="hidden" value={form.data.form.transfer_type} />

                                {transferType === 'faculty' && (
                                    <>
                                        <label className="db-label">Fakultas Tujuan</label>
                                        <select className="db-input" value={targetFacultyId} onChange={(e) => changeFaculty(e.target.value)}>
                                            <option value="">Pilih fakultas</option>
                                            {faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                        </select>
                                    </>
                                )}

                                <label className="db-label">Program Studi Tujuan</label>
                                <select className="db-input" value={form.data.form.to_study_program_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, to_study_program_id: e.target.value })}>
                                    <option value="">Pilih program studi</option>
                                    {filteredPrograms.map((p) => <option key={p.id} value={p.id}>{p.name}{p.facultyName ? ` (${p.facultyName})` : ''}</option>)}
                                </select>
                                {form.errors['form.to_study_program_id'] && <div className="db-note danger">{form.errors['form.to_study_program_id']}</div>}

                                {transferType === 'class_type' && (
                                    <>
                                        <label className="db-label">Kelas Tujuan</label>
                                        <select className="db-input" value={form.data.form.to_class_type}
                                            onChange={(e) => form.setData('form', { ...form.data.form, to_class_type: e.target.value })}>
                                            <option value="">Pilih kelas</option>
                                            {classTypeOptions.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                        </select>
                                    </>
                                )}

                                <label className="db-label">Semester Rekomendasi</label>
                                <input type="number" className="db-input" min={1} max={14} value={form.data.form.recommended_semester ?? ''}
                                    onChange={(e) => form.setData('form', { ...form.data.form, recommended_semester: e.target.value ? Number(e.target.value) : null })} />

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
