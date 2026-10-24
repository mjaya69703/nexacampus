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
type Req = { id: number; number: string; toProgramId: number | null; transferType: string; fromClassType: string | null; toClassType: string | null; currentSemester: number | null; recommendedSemester: number | null; reason: string; studentNotes: string | null; adminNotes: string | null; attachmentUrl: string | null };

type Props = { shell: ShellProps; request: Req; targetFacultyId: number | null; faculties: Faculty[]; studyPrograms: Program[]; classTypeOptions: ClassOption[]; urls: { index: string; show: string; update: string } };

export default function TransfersEdit({ shell, request, targetFacultyId, faculties, studyPrograms, classTypeOptions, urls }: Props) {
    const [transferType, setTransferType] = useState<string>(request.transferType);
    const [tf, setTf] = useState<string>(targetFacultyId ? String(targetFacultyId) : '');

    const filteredPrograms = useMemo(() => {
        if (transferType === 'study_program') {
            // all except current program; approximate: filter by same faculty not available; pass through
            return studyPrograms.filter((p) => p.id !== (request as any).currentProgramId);
        }
        if (transferType === 'faculty') {
            return studyPrograms.filter((p) => Number(p.facultyId) === Number(tf));
        }
        if (transferType === 'class_type') {
            return studyPrograms.filter((p) => p.id === request.toProgramId);
        }
        return studyPrograms.filter((p) => p.id !== request.toProgramId);
    }, [transferType, tf, studyPrograms, request]);

    const form = useForm<{
        form: { to_study_program_id: string; target_faculty_id: string; to_class_type: string; recommended_semester: number | null; transfer_type: string; reason: string; student_notes: string };
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

    const submit = (e: FormEvent) => { e.preventDefault(); form.put(urls.update, { forceFormData: true, preserveScroll: true }); };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ArrowRightLeft} eyebrow="Student Services" title="Perbaiki Pengajuan Pindah" description={request.number} />
                    <Link href={urls.show} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    {request.adminNotes && <div className="db-note warning">{request.adminNotes}</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Program Studi Tujuan</label>
                                <select className="db-input" value={form.data.form.to_study_program_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, to_study_program_id: e.target.value })}>
                                    {filteredPrograms.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                                <label className="db-label">Kelas Tujuan</label>
                                <select className="db-input" value={form.data.form.to_class_type}
                                    onChange={(e) => form.setData('form', { ...form.data.form, to_class_type: e.target.value })}>
                                    <option value="">-</option>
                                    {classTypeOptions.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                                </select>
                                <label className="db-label">Semester Rekomendasi</label>
                                <input type="number" className="db-input" min={1} max={14} value={form.data.form.recommended_semester ?? ''}
                                    onChange={(e) => form.setData('form', { ...form.data.form, recommended_semester: e.target.value ? Number(e.target.value) : null })} />
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
