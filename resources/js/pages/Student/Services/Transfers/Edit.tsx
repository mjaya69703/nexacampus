import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Info, Paperclip, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { classTypeLabel, transferTypeLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Faculty = { id: number; name: string };
type Program = { id: number; name: string; facultyId: number; facultyName: string | null };
type ClassOption = { value: string; label: string };
type Req = {
    id: number;
    number: string;
    fromProgram: string;
    toProgram: string;
    fromProgramId: number | null;
    toProgramId: number | null;
    transferType: string;
    fromClassType: string | null;
    toClassType: string | null;
    currentSemester: number | null;
    recommendedSemester: number | null;
    reason: string;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
};

type Props = {
    shell: ShellProps;
    request: Req;
    targetFacultyId: number | null;
    faculties: Faculty[];
    studyPrograms: Program[];
    classTypeOptions: ClassOption[];
    urls: { index: string; show: string; update: string };
};

export default function TransfersEdit({ shell, request, targetFacultyId, faculties, studyPrograms, classTypeOptions, urls }: Props) {
    const [transferType, setTransferType] = useState<string>(request.transferType);
    const [tf, setTf] = useState<string>(targetFacultyId ? String(targetFacultyId) : '');

    const currentProgram = studyPrograms.find((program) => program.id === request.fromProgramId) ?? null;

    const filteredPrograms = useMemo(() => {
        if (transferType === 'study_program') {
            return studyPrograms.filter(
                (program) => program.id !== request.fromProgramId
                    && (!currentProgram || program.facultyId === currentProgram.facultyId),
            );
        }
        if (transferType === 'faculty') {
            return studyPrograms.filter((program) => Number(program.facultyId) === Number(tf));
        }
        if (transferType === 'class_type') {
            return studyPrograms.filter((program) => program.id === request.fromProgramId);
        }
        return studyPrograms.filter((program) => program.id !== request.fromProgramId);
    }, [transferType, tf, studyPrograms, request.fromProgramId, currentProgram]);

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
            to_study_program_id: String(request.toProgramId ?? ''),
            target_faculty_id: tf,
            to_class_type: request.toClassType ?? '',
            recommended_semester: request.recommendedSemester,
            transfer_type: request.transferType,
            reason: request.reason ?? '',
            student_notes: request.studentNotes ?? '',
        },
        attachment: null,
    });

    const changeType = (type: string) => {
        setTransferType(type);
        form.setData('form', {
            ...form.data.form,
            transfer_type: type,
            target_faculty_id: '',
            to_study_program_id: type === 'class_type' ? String(request.fromProgramId ?? request.toProgramId ?? '') : '',
            to_class_type: '',
        });
        setTf('');
    };

    const changeFaculty = (facultyId: string) => {
        setTf(facultyId);
        form.setData('form', { ...form.data.form, target_faculty_id: facultyId, to_study_program_id: '' });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { forceFormData: true, preserveScroll: true });
    };

    const classOptions = classTypeOptions.filter((option) => option.value !== request.fromClassType);

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowRightLeft}
                        eyebrow="Student Services · Pindah"
                        title="Perbaiki Pengajuan Pindah"
                        description={`${request.number} · ${transferTypeLabel(request.transferType)}`}
                        actions={<Link href={urls.show} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {request.adminNotes && (
                        <div className="db-note warning">
                            <Info size={15} />
                            <span><b>Catatan admin:</b> {request.adminNotes}</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Formulir Perbaikan</h2></div>
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
                                        <select className="db-input" value={tf} onChange={(event) => changeFaculty(event.target.value)}>
                                            <option value="">Pilih fakultas</option>
                                            {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}
                                        </select>
                                        {form.errors['form.target_faculty_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.target_faculty_id']}</div>}
                                    </div>
                                )}

                                {transferType === 'class_type' ? (
                                    <div>
                                        <label className="db-label">Program Studi</label>
                                        <div className="db-fact">
                                            <span>Program studi saat ini</span>
                                            <b>{request.fromProgram}</b>
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
                                            Kelas saat ini: {classTypeLabel(request.fromClassType)}. Pilih kelas yang berbeda.
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
                                    {form.errors['form.recommended_semester'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.recommended_semester']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Alasan Pindah <span>*</span></label>
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
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        Kosongkan jika lampiran sebelumnya masih valid. Format PDF/JPG/PNG, maksimal 5 MB.
                                    </p>
                                    {request.attachmentUrl && (
                                        <a href={request.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm" style={{ marginTop: 8 }}>
                                            <Paperclip size={13} /> Lihat lampiran saat ini
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
