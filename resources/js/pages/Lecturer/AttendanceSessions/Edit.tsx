// Edit sesi absensi — ubah topik/status sesi dan status kehadiran tiap mahasiswa.
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarCheck, Check, CheckCheck, Save, Users, X } from 'lucide-react';
import { FormEvent, useMemo } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type SessionInfo = {
    offeringId: number; course: string; class: string; academicYear: string;
    meetingNo: number | null; meetingDate: string; time: string; topic: string; status: string;
};

type Student = { studentProfileId: number; nim: string; name: string; status: string; notes: string | null };
type StatusOption = { value: string; label: string };

type Props = {
    shell: ShellProps;
    sessionId: number;
    sessionInfo: SessionInfo;
    students: Student[];
    statusOptions: StatusOption[];
    urls: { save: string; back: string };
};

const tone = (status: string) => {
    if (status === 'Present') return 'green';
    if (status === 'Late') return 'amber';
    if (['Excused', 'Sick'].includes(status)) return 'gray';
    if (status === 'Absent') return 'red';
    return 'gray';
};

export default function AttendanceSessionsEdit({ shell, sessionInfo, students, statusOptions, urls }: Props) {
    const initialStatuses = useMemo(
        () => Object.fromEntries(students.map((student) => [String(student.studentProfileId), student.status])) as Record<string, string>,
        [students],
    );

    const form = useForm<{ topic: string; status: string; statuses: Record<string, string> }>({
        topic: sessionInfo.topic,
        status: sessionInfo.status,
        statuses: initialStatuses,
    });

    const counts = useMemo(() => {
        const values = Object.values(form.data.statuses);
        return {
            Present: values.filter((value) => value === 'Present').length,
            Late: values.filter((value) => value === 'Late').length,
            Excused: values.filter((value) => ['Excused', 'Sick'].includes(value)).length,
            Absent: values.filter((value) => value === 'Absent').length,
        };
    }, [form.data.statuses]);

    const setStatus = (studentId: number, value: string) => {
        form.setData('statuses', { ...form.data.statuses, [String(studentId)]: value });
    };

    const markAll = (value: string) => {
        form.setData('statuses', Object.fromEntries(students.map((student) => [String(student.studentProfileId), value])));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.save, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi pertemuan ${sessionInfo.meetingNo ?? '-'} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Akademik · Absensi"
                        title={`Pertemuan ${sessionInfo.meetingNo ?? '-'}`}
                        description={`${sessionInfo.course} · ${sessionInfo.class} · ${sessionInfo.meetingDate} · ${sessionInfo.time}`}
                        badges={[sessionInfo.status, `${students.length} mahasiswa`, sessionInfo.academicYear]}
                        actions={<Link className="db-btn light" href={urls.back}><ArrowLeft size={15} /> Kembali ke absensi</Link>}
                    />

                    <section className="db-stats" aria-label="Rekap kehadiran">
                        <div className="db-stat"><span className="db-stat-icon green"><Check size={20} /></span><div><span className="db-stat-num">{counts.Present}</span><span className="db-stat-label">Hadir</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon gold"><CalendarCheck size={20} /></span><div><span className="db-stat-num">{counts.Late}</span><span className="db-stat-label">Terlambat</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><Users size={20} /></span><div><span className="db-stat-num">{counts.Excused}</span><span className="db-stat-label">Izin/Sakit</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon red"><X size={20} /></span><div><span className="db-stat-num">{counts.Absent}</span><span className="db-stat-label">Alpa</span></div></div>
                    </section>

                    <form onSubmit={submit} className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarCheck size={16} /> Informasi sesi</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <label className="crud-label">Topik pertemuan</label>
                                    <input className="db-input" value={form.data.topic} onChange={(event) => form.setData('topic', event.target.value)} />
                                    {form.errors.topic && <div className="db-error">{form.errors.topic}</div>}
                                </div>
                                <div>
                                    <label className="crud-label">Status sesi <span>*</span></label>
                                    <select className="db-input" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                                        {['Scheduled', 'Opened', 'Closed', 'Cancelled'].map((status) => (
                                            <option key={status} value={status}>{status}</option>
                                        ))}
                                    </select>
                                    {form.errors.status && <div className="db-error">{form.errors.status}</div>}
                                </div>
                            </div>
                        </div>
                    </form>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Users size={16} /> Daftar kehadiran ({students.length})</h2>
                            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                <button className="db-btn ghost sm" type="button" onClick={() => markAll('Present')}><CheckCheck size={12} /> Semua hadir</button>
                                <button className="db-btn ghost sm" type="button" onClick={() => markAll('Absent')}>Semua alpa</button>
                            </div>
                        </div>
                        <div className="db-card-body">
                            {students.length === 0 ? (
                                <EmptyState icon={Users} title="Belum ada mahasiswa" description="Mahasiswa dengan KRS Approved akan tampil di sini." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>NIM</th><th>Nama</th><th>Status</th><th>Catatan</th></tr>
                                        </thead>
                                        <tbody>
                                            {students.map((student) => {
                                                const current = form.data.statuses[String(student.studentProfileId)] ?? student.status;
                                                return (
                                                    <tr key={student.studentProfileId}>
                                                        <td>{student.nim}</td>
                                                        <td><b>{student.name}</b></td>
                                                        <td>
                                                            <select
                                                                className="db-input"
                                                                value={current}
                                                                onChange={(event) => setStatus(student.studentProfileId, event.target.value)}
                                                                style={{ maxWidth: 150 }}
                                                            >
                                                                {statusOptions.map((option) => (
                                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                                ))}
                                                            </select>
                                                            <span className={`db-badge ${tone(current)}`} style={{ marginLeft: 8 }}>{statusOptions.find((option) => option.value === current)?.label ?? current}</span>
                                                        </td>
                                                        <td>{student.notes ?? '-'}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            {form.errors.statuses && <div className="db-error">{form.errors.statuses}</div>}
                        </div>
                        <div className="db-card-body" style={{ borderTop: '1px solid var(--db-line)', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <button className="db-btn primary" type="submit" disabled={form.processing || students.length === 0}>
                                <Save size={14} /> {form.processing ? 'Menyimpan…' : 'Simpan absensi'}
                            </button>
                            <Link className="db-btn ghost" href={urls.back}><X size={14} /> Batal</Link>
                            <span className="db-hint" style={{ marginLeft: 'auto' }}>Status manual akan ditandai verified dengan sumber manual_lecturer.</span>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
