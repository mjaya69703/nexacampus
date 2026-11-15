import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Info, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { classTypeLabel } from '../labels';
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

export default function TransfersCreate({ shell, hasProfile, currentProgramId, currentClassType, currentFacultyId, currentSemester, faculties, studyPrograms, classTypeOptions, urls }: Props) {
    const [transferType, setTransferType] = useState<string>('study_program');
    const [targetFacultyId, setTargetFacultyId] = useState<string>('');

    const currentProgramName = studyPrograms.find((program) => program.id === currentProgramId)?.name ?? '-';

    const filteredPrograms = useMemo(() => {
        if (transferType === 'study_program') {
            return studyPrograms.filter((program) => program.facultyId === currentFacultyId && program.id !== currentProgramId);
        }
        if (transferType === 'faculty') {
            return studyPrograms.filter((program) => Number(program.facultyId) === Number(targetFacultyId));
        }
        if (transferType === 'class_type') {
            return studyPrograms.filter((program) => program.id === currentProgramId);
        }
        return studyPrograms.filter((program) => program.id !== currentProgramId);
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

    const changeType = (type: string) => {
        setTransferType(type);
        form.setData('form', {
            ...form.data.form,
            transfer_type: type,
            target_faculty_id: '',
            to_study_program_id: type === 'class_type' ? String(currentProgramId ?? '') : '',
            to_class_type: '',
        });
        setTargetFacultyId('');
    };

    const changeFaculty = (facultyId: string) => {
        setTargetFacultyId(facultyId);
        form.setData('form', { ...form.data.form, target_faculty_id: facultyId, to_study_program_id: '' });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    const classOptions = classTypeOptions.filter((option) => option.value !== currentClassType);

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowRightLeft}
                        eyebrow="Student Services · Pindah"
                        title="Ajukan Pindah Program"
                        description="Pilih jenis pindah, tujuan, dan alasan pengajuan."
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan. Hubungi admin akademik sebelum mengajukan pindah.</div>}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Formulir Pindah</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                <div>
                                    <label className="db-label">Jenis Pindah <span>*</span></label>
                                    <select className="db-input" value={transferType} onChange={(event) => changeType(event.target.value)}>
                                        <option value="study_program">Pindah Program Studi</option>
                                        <option value="faculty">Pindah Fakultas</option>
                                        <option value="class_type">Pindah Kelas</option>
                                        <option value="other">Lainnya</option>
                                    </select>
                                    {form.errors['form.transfer_type'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.transfer_type']}</div>}
                                </div>

                                {transferType === 'faculty' && (
                                    <div>
                                        <label className="db-label">Fakultas Tujuan <span>*</span></label>
                                        <select className="db-input" value={targetFacultyId} onChange={(event) => changeFaculty(event.target.value)}>
                                            <option value="">Pilih fakultas</option>
                                            {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                                        </select>
                                    </div>
                                )}

                                {transferType === 'class_type' ? (
                                    <div>
                                        <label className="db-label">Program Studi</label>
                                        <div className="db-fact">
                                            <span>Program studi saat ini</span>
                                            <b>{currentProgramName}</b>
                                        </div>
                                        <label className="db-label" style={{ marginTop: 14 }}>Kelas Tujuan <span>*</span></label>
                                        <select
                                            className="db-input"
                                            value={form.data.form.to_class_type}
                                            onChange={(event) => form.setData('form', { ...form.data.form, to_class_type: event.target.value })}
                                        >
                                            <option value="">Pilih kelas tujuan</option>
                                            {classOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                                        </select>
                                        <p className="db-hint" style={{ marginTop: 6 }}>
                                            Kelas saat ini: {classTypeLabel(currentClassType)}. Pilih kelas yang berbeda.
                                        </p>
                                        {form.errors['form.to_class_type'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.to_class_type']}</div>}
                                    </div>
                                ) : (
                                    <div>
                                        <label className="db-label">Program Studi Tujuan <span>*</span></label>
                                        <select
                                            className="db-input"
                                            value={form.data.form.to_study_program_id}
                                            onChange={(event) => form.setData('form', { ...form.data.form, to_study_program_id: event.target.value })}
                                        >
                                            <option value="">Pilih program studi</option>
                                            {filteredPrograms.map((program) => (
                                                <option key={program.id} value={program.id}>
                                                    {program.name}{program.facultyName ? ` (${program.facultyName})` : ''}
                                                </option>
                                            ))}
                                        </select>
                                        {form.errors['form.to_study_program_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.to_study_program_id']}</div>}
                                    </div>
                                )}

                                <div>
                                    <label className="db-label">Semester Rekomendasi</label>
                                    <input
                                        type="number"
                                        className="db-input"
                                        min={1}
                                        max={14}
                                        value={form.data.form.recommended_semester ?? ''}
                                        onChange={(event) => form.setData('form', { ...form.data.form, recommended_semester: event.target.value ? Number(event.target.value) : null })}
                                    />
                                </div>

                                <div>
                                    <label className="db-label">Alasan Pindah <span>*</span></label>
                                    <textarea
                                        className="db-input"
                                        rows={4}
                                        value={form.data.form.reason}
                                        onChange={(event) => form.setData('form', { ...form.data.form, reason: event.target.value })}
                                        placeholder="Jelaskan alasan perpindahan secara jelas…"
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
                                </div>

                                <div>
                                    <label className="db-label">Lampiran Pendukung</label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>Format PDF, JPG, atau PNG. Maksimal 5 MB.</p>
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                <div className="db-note info">
                                    <Info size={15} />
                                    <span>Pengajuan akan melalui evaluasi akademik, pemetaan kredit, dan persetujuan pejabat berwenang.</span>
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
