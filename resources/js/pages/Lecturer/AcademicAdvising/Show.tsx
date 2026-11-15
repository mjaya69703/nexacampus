// Detail bimbingan akademik — progres mahasiswa, persetujuan KRS, dan catatan advisori.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle, ArrowLeft, BookOpen, Check, CheckCircle2, Clock, CreditCard, FileText,
    GraduationCap, MessageSquarePlus, Plus, TrendingUp, Users, X, XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Assignment = {
    studentName?: string; nim?: string; studyProgram?: string; faculty?: string; semester?: number | string;
    academicStatus?: string; academicYear?: string; startDate?: string; endDate?: string;
};

type Risk = { key: string; label: string; level: string; reason: string };
type FailedCourse = { code: string; name: string; semester: number | string | null; credits: number; grade: string };

type Progress = {
    credits?: { target: number; passed: number; remaining: number; progress: number; current: number; current_status: string };
    gpa?: { semester: number | null; cumulative: number | null; latest_semester: number | string | null };
    attendance?: { total: number; attended: number; excused: number; absent: number; rate: number | null; level: string };
    financial?: { outstanding: number; overdue_count: number; active_holds: number; level: string };
    risks?: Risk[];
    failed_courses?: FailedCourse[];
    recommendations?: string[];
};

type Plan = { id: number; year: string; semester: number | string; courses: number; credits: number; submittedAt: string };
type Note = {
    id: number; topic: string; notes: string; recommendation: string | null; followUpAt: string | null;
    status: string; visibleToStudent: boolean; createdAt: string;
};

type Props = {
    shell: ShellProps;
    assignmentId: number;
    assignment: Assignment | [];
    progress: Progress | [];
    pendingPlans: Plan[];
    notes: Note[];
    urls: { index: string; approve: string; reject: string; note: string; noteDone: string };
};

const levelTone = (level: string) => (level === 'danger' ? 'red' : level === 'warning' ? 'amber' : level === 'success' ? 'green' : 'gray');

function PlanDecision({ plan, urls }: { plan: Plan; urls: Props['urls'] }) {
    const [mode, setMode] = useState<'approve' | 'reject' | null>(null);
    const form = useForm<{ planId: number; notes: string }>({ planId: plan.id, notes: '' });

    const submit = (event: FormEvent, decision: 'approve' | 'reject') => {
        event.preventDefault();
        form.post(decision === 'approve' ? urls.approve : urls.reject, {
            preserveScroll: true,
            onSuccess: () => setMode(null),
        });
    };

    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>KRS {plan.year} · Semester {plan.semester}</b>
                    <small>{plan.courses} mata kuliah · {plan.credits} SKS · diajukan {plan.submittedAt}</small>
                </div>
                <span className="db-badge amber">Menunggu persetujuan</span>
            </div>
            <div className="db-class-actions">
                <button className="db-btn primary sm" type="button" onClick={() => setMode(mode === 'approve' ? null : 'approve')}><Check size={12} /> Setujui</button>
                <button className="db-btn danger sm" type="button" onClick={() => setMode(mode === 'reject' ? null : 'reject')}><X size={12} /> Tolak</button>
            </div>
            {mode && (
                <form onSubmit={(event) => submit(event, mode)} style={{ display: 'grid', gap: 10, marginTop: 10, borderTop: '1px solid var(--db-line)', paddingTop: 10 }}>
                    <div>
                        <label className="crud-label">Catatan {mode === 'approve' ? 'persetujuan' : 'penolakan'}</label>
                        <textarea
                            className="db-input"
                            rows={3}
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            placeholder={mode === 'approve' ? 'Opsional: catatan untuk mahasiswa.' : 'Jelaskan alasan penolakan agar mahasiswa dapat memperbaiki KRS.'}
                        />
                        {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                        {form.errors.planId && <div className="db-error">{form.errors.planId}</div>}
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className={`db-btn sm ${mode === 'approve' ? 'primary' : 'danger'}`} type="submit" disabled={form.processing}>
                            {mode === 'approve' ? <Check size={12} /> : <X size={12} />} {form.processing ? 'Menyimpan…' : `Konfirmasi ${mode === 'approve' ? 'setujui' : 'tolak'}`}
                        </button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setMode(null)}>Batal</button>
                    </div>
                </form>
            )}
        </article>
    );
}

export default function AcademicAdvisingShow({ shell, assignment: rawAssignment, progress: rawProgress, pendingPlans, notes, urls }: Props) {
    const assignment = Array.isArray(rawAssignment) ? {} : rawAssignment;
    const progress = Array.isArray(rawProgress) ? {} : rawProgress;
    const [showNoteForm, setShowNoteForm] = useState(false);
    const noteForm = useForm<{ topic: string; notes: string; recommendation: string; followUpAt: string; visibleToStudent: boolean }>({
        topic: '', notes: '', recommendation: '', followUpAt: '', visibleToStudent: true,
    });

    const submitNote = (event: FormEvent) => {
        event.preventDefault();
        noteForm.post(urls.note, {
            preserveScroll: true,
            onSuccess: () => {
                noteForm.reset();
                setShowNoteForm(false);
            },
        });
    };

    const completeNote = (noteId: number) => {
        router.post(urls.noteDone.replace('__NOTE__', String(noteId)), {}, { preserveScroll: true });
    };

    const risks = progress.risks ?? [];
    const credits = progress.credits;
    const gpa = progress.gpa;
    const attendance = progress.attendance;
    const financial = progress.financial;

    return (
        <AdminShell shell={shell}>
            <Head title={`Bimbingan ${assignment.studentName ?? ''} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Akademik · Bimbingan"
                        title={assignment.studentName ?? 'Detail Bimbingan'}
                        description={assignment.nim ? `${assignment.nim} · ${assignment.studyProgram ?? '-'}` : 'Data mahasiswa bimbingan'}
                        badges={[assignment.academicStatus ?? '-', `Semester ${assignment.semester ?? '-'}`, assignment.academicYear ?? '-']}
                        actions={<Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar bimbingan</Link>}
                    />

                    {!assignment.studentName ? (
                        <EmptyState icon={Users} title="Data tidak ditemukan" description="Penugasan bimbingan tidak aktif atau bukan milik Anda." />
                    ) : (
                        <>
                            <section className="db-stats" aria-label="Ringkasan akademik">
                                <div className="db-stat"><span className="db-stat-icon gold"><GraduationCap size={20} /></span><div><span className="db-stat-num">{gpa?.cumulative ?? '-'}</span><span className="db-stat-label">IPK kumulatif</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon"><BookOpen size={20} /></span><div><span className="db-stat-num">{credits ? `${credits.passed}/${credits.target}` : '-'}</span><span className="db-stat-label">SKS lulus</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon green"><TrendingUp size={20} /></span><div><span className="db-stat-num">{attendance?.rate !== null && attendance?.rate !== undefined ? `${attendance.rate}%` : '-'}</span><span className="db-stat-label">Kehadiran</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon red"><CreditCard size={20} /></span><div><span className="db-stat-num">{financial ? `Rp ${financial.outstanding.toLocaleString('id-ID')}` : '-'}</span><span className="db-stat-label">Tagihan aktif</span></div></div>
                            </section>

                            <div className="cx-cards-2">
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title">Progres studi</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                        <div>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                                                <span>Kredit lulus</span><b>{credits ? `${credits.passed} / ${credits.target} SKS` : '-'}</b>
                                            </div>
                                            <div className="db-progress"><i style={{ width: `${credits?.progress ?? 0}%` }} /></div>
                                            <div className="crud-hint">Sisa {credits?.remaining ?? '-'} SKS menuju target kelulusan.</div>
                                        </div>
                                        <div className="db-summary"><div><b>Beban semester berjalan</b><small>Status KRS: {credits?.current_status ?? '-'}</small></div><span className="db-badge gray">{credits?.current ?? 0} SKS</span></div>
                                        <div className="db-summary"><div><b>IPS semester lalu</b><small>Semester {gpa?.latest_semester ?? '-'}</small></div><span className="db-badge amber">{gpa?.semester ?? '-'}</span></div>
                                        <div className="db-summary"><div><b>Kehadiran</b><small>{attendance?.attended ?? 0} hadir · {attendance?.excused ?? 0} izin · {attendance?.absent ?? 0} alpa</small></div><span className={`db-badge ${levelTone(attendance?.level ?? 'neutral')}`}>{attendance?.rate !== null && attendance?.rate !== undefined ? `${attendance.rate}%` : '-'}</span></div>
                                        <div className="db-summary"><div><b>Keuangan</b><small>{financial?.overdue_count ?? 0} tagihan lewat jatuh tempo · {financial?.active_holds ?? 0} hold</small></div><span className={`db-badge ${levelTone(financial?.level ?? 'neutral')}`}>{financial?.overdue_count ? 'Perlu perhatian' : 'Aman'}</span></div>
                                    </div>
                                </section>

                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><AlertTriangle size={16} /> Analisis risiko</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                                        {risks.length === 0 ? (
                                            <p className="db-hint" style={{ margin: 0 }}>Belum ada analisis risiko.</p>
                                        ) : risks.map((risk) => (
                                            <div className="db-summary" key={risk.key}>
                                                <div><b>{risk.label}</b><small>{risk.reason}</small></div>
                                                <span className={`db-badge ${levelTone(risk.level)}`}>{risk.level}</span>
                                            </div>
                                        ))}
                                        {(progress.recommendations ?? []).length > 0 && (
                                            <div className="db-note info" style={{ marginTop: 4 }}>
                                                <TrendingUp size={14} />
                                                <span>
                                                    <b>Rekomendasi:</b>
                                                    <ul style={{ margin: '4px 0 0 16px', padding: 0 }}>
                                                        {(progress.recommendations ?? []).map((item, index) => <li key={index}>{item}</li>)}
                                                    </ul>
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            </div>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><FileText size={16} /> KRS menunggu persetujuan ({pendingPlans.length})</h2></div>
                                <div className="db-card-body">
                                    {pendingPlans.length === 0 ? (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada KRS yang menunggu persetujuan.</p>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {pendingPlans.map((plan) => <PlanDecision key={plan.id} plan={plan} urls={urls} />)}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {(progress.failed_courses ?? []).length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><XCircle size={16} /> Mata kuliah perlu ulang</h2></div>
                                    <div className="db-card-body">
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead><tr><th>Kode</th><th>Mata kuliah</th><th>Semester</th><th>SKS</th><th>Nilai</th></tr></thead>
                                                <tbody>
                                                    {(progress.failed_courses ?? []).map((course) => (
                                                        <tr key={`${course.code}-${course.semester}`}>
                                                            <td>{course.code}</td><td><b>{course.name}</b></td><td>{course.semester ?? '-'}</td><td>{course.credits}</td>
                                                            <td><span className="db-badge red">{course.grade}</span></td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><MessageSquarePlus size={16} /> Catatan bimbingan ({notes.length})</h2>
                                    <button className="db-btn ghost sm" type="button" onClick={() => setShowNoteForm((value) => !value)}>
                                        <Plus size={12} /> {showNoteForm ? 'Tutup' : 'Tambah catatan'}
                                    </button>
                                </div>
                                {showNoteForm && (
                                    <div className="db-card-body" style={{ borderBottom: '1px solid var(--db-line)' }}>
                                        <form onSubmit={submitNote} style={{ display: 'grid', gap: 12 }}>
                                            <div className="crud-grid">
                                                <div>
                                                    <label className="crud-label">Topik <span>*</span></label>
                                                    <input className="db-input" value={noteForm.data.topic} onChange={(event) => noteForm.setData('topic', event.target.value)} />
                                                    {noteForm.errors.topic && <div className="db-error">{noteForm.errors.topic}</div>}
                                                </div>
                                                <div>
                                                    <label className="crud-label">Tindak lanjut</label>
                                                    <input type="date" className="db-input" value={noteForm.data.followUpAt} onChange={(event) => noteForm.setData('followUpAt', event.target.value)} />
                                                    {noteForm.errors.followUpAt && <div className="db-error">{noteForm.errors.followUpAt}</div>}
                                                </div>
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <label className="crud-label">Catatan <span>*</span></label>
                                                    <textarea className="db-input" rows={3} value={noteForm.data.notes} onChange={(event) => noteForm.setData('notes', event.target.value)} />
                                                    {noteForm.errors.notes && <div className="db-error">{noteForm.errors.notes}</div>}
                                                </div>
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <label className="crud-label">Rekomendasi</label>
                                                    <textarea className="db-input" rows={2} value={noteForm.data.recommendation} onChange={(event) => noteForm.setData('recommendation', event.target.value)} />
                                                </div>
                                                <label className="db-summary" style={{ cursor: 'pointer' }}>
                                                    <div><b>Tampilkan ke mahasiswa</b><small>Catatan terlihat di portal mahasiswa.</small></div>
                                                    <input type="checkbox" checked={noteForm.data.visibleToStudent} onChange={(event) => noteForm.setData('visibleToStudent', event.target.checked)} />
                                                </label>
                                            </div>
                                            <div style={{ display: 'flex', gap: 8 }}>
                                                <button className="db-btn primary sm" type="submit" disabled={noteForm.processing}><CheckCircle2 size={12} /> {noteForm.processing ? 'Menyimpan…' : 'Simpan catatan'}</button>
                                                <button className="db-btn ghost sm" type="button" onClick={() => setShowNoteForm(false)}>Batal</button>
                                            </div>
                                        </form>
                                    </div>
                                )}
                                <div className="db-card-body">
                                    {notes.length === 0 ? (
                                        <EmptyState icon={MessageSquarePlus} title="Belum ada catatan" description="Tambahkan catatan bimbingan pertama untuk mahasiswa ini." />
                                    ) : (
                                        <div className="db-timeline">
                                            {notes.map((note) => (
                                                <div className="db-timeline-item" key={note.id}>
                                                    <span className={`db-timeline-dot ${note.status === 'Done' ? 'green' : 'gold'}`} />
                                                    <div className="db-timeline-main">
                                                        <b>{note.topic} {note.visibleToStudent ? '· terlihat mahasiswa' : ''}</b>
                                                        <small>{note.notes}</small>
                                                        {note.recommendation && <small>Rekomendasi: {note.recommendation}</small>}
                                                    </div>
                                                    <div className="db-timeline-when">
                                                        <small><Clock size={12} /> {note.createdAt}</small>
                                                        <small>Tindak lanjut: {note.followUpAt ?? '-'} · {note.status}</small>
                                                        {note.status !== 'Done' && (
                                                            <button className="db-btn ghost sm" type="button" onClick={() => completeNote(note.id)}><Check size={11} /> Selesai</button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
