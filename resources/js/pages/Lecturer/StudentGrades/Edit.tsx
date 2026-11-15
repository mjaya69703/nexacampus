// Input nilai mahasiswa — komponen penilaian, bobot, dan finalisasi.
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, BookOpen, CheckCircle2, GraduationCap, Plus, Save, Trash2, User } from 'lucide-react';
import { FormEvent, useMemo } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Component = {
    id: number | null;
    name: string;
    weightPercentage: number | string | null;
    score: number | string | null;
    sortOrder: number | null;
    notes: string | null;
};

type StudentInfo = { nim: string; name: string; studyProgram: string; course: string; class: string; academicYear: string };
type Grade = {
    notes: string | null; gradeStatus: string; finalScore: number | null; letterGrade: string | null;
    gradePoint: number | null; resultStatus: string | null; isPublished: boolean;
};

type Props = {
    shell: ShellProps;
    id: number;
    studentInfo: StudentInfo;
    grade: Grade;
    components: Component[];
    allowedStatuses: string[];
    urls: { save: string; index: string; offering: string };
};

type FormData = { notes: string; gradeStatus: string; components: Component[] };

export default function StudentGradesEdit({ shell, studentInfo, grade, components, allowedStatuses, urls }: Props) {
    const form = useForm<FormData>({ notes: grade.notes ?? '', gradeStatus: grade.gradeStatus, components });

    const totalWeight = useMemo(
        () => form.data.components.reduce((total, component) => total + Number(component.weightPercentage || 0), 0),
        [form.data.components],
    );

    const setComponent = (index: number, patch: Partial<Component>) => {
        form.setData('components', form.data.components.map((component, position) => (position === index ? { ...component, ...patch } : component)));
    };

    const addComponent = () => {
        form.setData('components', [
            ...form.data.components,
            { id: null, name: '', weightPercentage: '', score: '', sortOrder: form.data.components.length + 1, notes: '' },
        ]);
    };

    const removeComponent = (index: number) => {
        form.setData('components', form.data.components.filter((_, position) => position !== index));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.save, { preserveScroll: true });
    };

    const weightWarning = totalWeight > 100
        ? 'Total bobot melebihi 100%.'
        : form.data.gradeStatus === 'Finalized' && Math.abs(totalWeight - 100) > 0.0001
            ? 'Status Finalized membutuhkan total bobot tepat 100%.'
            : null;

    return (
        <AdminShell shell={shell}>
            <Head title={`Nilai ${studentInfo.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik · Nilai Mahasiswa"
                        title={studentInfo.name}
                        description={`${studentInfo.nim} · ${studentInfo.studyProgram}`}
                        badges={[studentInfo.course, studentInfo.class, grade.gradeStatus]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar nilai</Link>
                                <Link className="db-btn light" href={urls.offering}><BookOpen size={15} /> Nilai kelas</Link>
                            </div>
                        }
                    />

                    <section className="db-stats" aria-label="Ringkasan nilai">
                        <div className="db-stat"><span className="db-stat-icon"><GraduationCap size={20} /></span><div><span className="db-stat-num">{grade.finalScore ?? '-'}</span><span className="db-stat-label">Nilai akhir</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><CheckCircle2 size={20} /></span><div><span className="db-stat-num">{grade.letterGrade ?? '-'}</span><span className="db-stat-label">Huruf</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon gold"><BookOpen size={20} /></span><div><span className="db-stat-num">{grade.gradePoint ?? '-'}</span><span className="db-stat-label">Grade point</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><User size={20} /></span><div><span className="db-stat-num">{grade.resultStatus ?? '-'}</span><span className="db-stat-label">Hasil</span></div></div>
                    </section>

                    {grade.isPublished && (
                        <div className="db-note success">
                            <CheckCircle2 size={15} />
                            <span>Nilai sudah Published dan terkunci. Koreksi hanya dapat dilakukan melalui workflow keberatan nilai.</span>
                        </div>
                    )}

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Informasi mahasiswa</h2></div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 8, fontSize: 13 }}>
                                <div><b>NIM:</b> {studentInfo.nim}</div>
                                <div><b>Nama:</b> {studentInfo.name}</div>
                                <div><b>Program studi:</b> {studentInfo.studyProgram}</div>
                                <div><b>Mata kuliah:</b> {studentInfo.course}</div>
                                <div><b>Kelas:</b> {studentInfo.class} · <b>Tahun:</b> {studentInfo.academicYear}</div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Status penilaian</h2></div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                <div>
                                    <label className="crud-label">Status nilai</label>
                                    <select
                                        className="db-input"
                                        value={form.data.gradeStatus}
                                        onChange={(event) => form.setData('gradeStatus', event.target.value)}
                                        disabled={grade.isPublished}
                                    >
                                        {allowedStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </select>
                                    {form.errors.gradeStatus && <div className="db-error">{form.errors.gradeStatus}</div>}
                                    <div className="crud-hint">Finalized menghitung snapshot dan menyinkronkan transkrip.</div>
                                </div>
                                <div>
                                    <label className="crud-label">Catatan dosen</label>
                                    <textarea
                                        className="db-input"
                                        rows={3}
                                        value={form.data.notes}
                                        onChange={(event) => form.setData('notes', event.target.value)}
                                        disabled={grade.isPublished}
                                    />
                                    {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                                </div>
                                <div>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                                        <span>Total bobot komponen</span>
                                        <b>{totalWeight}%</b>
                                    </div>
                                    <div className="db-progress"><i style={{ width: `${Math.min(100, totalWeight)}%` }} /></div>
                                    {weightWarning && <div className="db-note warning" style={{ marginTop: 8 }}><AlertTriangle size={14} /><span>{weightWarning}</span></div>}
                                    {form.errors.components && <div className="db-error">{form.errors.components}</div>}
                                </div>
                            </div>
                        </section>
                    </div>

                    <form onSubmit={submit} className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><BookOpen size={16} /> Komponen penilaian ({form.data.components.length})</h2>
                            {!grade.isPublished && (
                                <button className="db-btn ghost sm" type="button" onClick={addComponent}><Plus size={12} /> Tambah komponen</button>
                            )}
                        </div>
                        <div className="db-card-body">
                            {form.data.components.length === 0 ? (
                                <p className="db-hint" style={{ margin: 0 }}>Belum ada komponen. Tambahkan minimal satu komponen penilaian.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Nama komponen</th><th>Bobot (%)</th><th>Skor (0–100)</th><th>Urutan</th><th>Catatan</th><th /></tr>
                                        </thead>
                                        <tbody>
                                            {form.data.components.map((component, index) => (
                                                <tr key={component.id ?? `new-${index}`}>
                                                    <td>
                                                        <input
                                                            className="db-input"
                                                            value={component.name}
                                                            onChange={(event) => setComponent(index, { name: event.target.value })}
                                                            disabled={grade.isPublished}
                                                            placeholder="Tugas / UTS / UAS"
                                                        />
                                                        {form.errors[`components.${index}.name`] && <div className="db-error">{form.errors[`components.${index}.name`]}</div>}
                                                    </td>
                                                    <td>
                                                        <input
                                                            className="db-input"
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            step="0.01"
                                                            value={component.weightPercentage ?? ''}
                                                            onChange={(event) => setComponent(index, { weightPercentage: event.target.value })}
                                                            disabled={grade.isPublished}
                                                            style={{ maxWidth: 110 }}
                                                        />
                                                        {form.errors[`components.${index}.weightPercentage`] && <div className="db-error">{form.errors[`components.${index}.weightPercentage`]}</div>}
                                                    </td>
                                                    <td>
                                                        <input
                                                            className="db-input"
                                                            type="number"
                                                            min={0}
                                                            max={100}
                                                            step="0.01"
                                                            value={component.score ?? ''}
                                                            onChange={(event) => setComponent(index, { score: event.target.value })}
                                                            disabled={grade.isPublished}
                                                            style={{ maxWidth: 110 }}
                                                        />
                                                        {form.errors[`components.${index}.score`] && <div className="db-error">{form.errors[`components.${index}.score`]}</div>}
                                                    </td>
                                                    <td>
                                                        <input
                                                            className="db-input"
                                                            type="number"
                                                            min={0}
                                                            value={component.sortOrder ?? index + 1}
                                                            onChange={(event) => setComponent(index, { sortOrder: Number(event.target.value) })}
                                                            disabled={grade.isPublished}
                                                            style={{ maxWidth: 90 }}
                                                        />
                                                    </td>
                                                    <td>
                                                        <input
                                                            className="db-input"
                                                            value={component.notes ?? ''}
                                                            onChange={(event) => setComponent(index, { notes: event.target.value })}
                                                            disabled={grade.isPublished}
                                                        />
                                                    </td>
                                                    <td>
                                                        {!grade.isPublished && (
                                                            <button className="db-btn danger sm" type="button" onClick={() => removeComponent(index)} title="Hapus komponen">
                                                                <Trash2 size={12} />
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {(form.errors as Record<string, string>).locked && <div className="db-note danger" style={{ marginTop: 10 }}><AlertTriangle size={14} /><span>{(form.errors as Record<string, string>).locked}</span></div>}
                        </div>
                        <div className="db-card-body" style={{ borderTop: '1px solid var(--db-line)', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <button className="db-btn primary" type="submit" disabled={form.processing || grade.isPublished}>
                                <Save size={14} /> {form.processing ? 'Menyimpan…' : 'Simpan nilai'}
                            </button>
                            <Link className="db-btn ghost" href={urls.index}><ArrowLeft size={14} /> Kembali</Link>
                            {grade.isPublished && <span className="db-hint" style={{ marginLeft: 'auto' }}>Form terkunci karena nilai sudah dipublikasikan.</span>}
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
