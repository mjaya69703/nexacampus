// Kalender mengajar dosen — jadwal per hari dengan filter tahun dan mode.
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Clock, ExternalLink, Filter, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Schedule = {
    id: number; courseOfferingId: number | null; academicYear: string; studyProgram: string;
    courseCode: string; courseName: string; classLabel: string; classCode: string;
    day: string; dayOrder: number; startTime: string; endTime: string;
    sessionType: string; deliveryMode: string; room: string; building: string;
    meetingLink: string | null; notes: string | null;
};

type Info = { name?: string; nidn?: string; study_program?: string; faculty?: string };
type Props = {
    shell: ShellProps; hasProfile: boolean; lecturerInfo: Info; schedules: Schedule[];
    yearOptions: string[]; modeOptions: string[]; urls: { index: string };
};

const dayLabels: Record<string, string> = {
    Monday: 'Senin', Tuesday: 'Selasa', Wednesday: 'Rabu', Thursday: 'Kamis',
    Friday: 'Jumat', Saturday: 'Sabtu', Sunday: 'Minggu',
};

export default function CalendarIndex({ shell, hasProfile, lecturerInfo, schedules, yearOptions, modeOptions }: Props) {
    const [year, setYear] = useState('');
    const [mode, setMode] = useState('');

    const filtered = useMemo(
        () => schedules.filter((schedule) => (!year || schedule.academicYear === year) && (!mode || schedule.deliveryMode === mode)),
        [schedules, year, mode],
    );

    const grouped = useMemo(() => {
        const map = new Map<string, Schedule[]>();
        filtered.forEach((schedule) => {
            const day = schedule.day ?? 'Lainnya';
            map.set(day, [...(map.get(day) ?? []), schedule]);
        });
        return [...map.entries()].sort((a, b) => (a[1][0]?.dayOrder ?? 99) - (b[1][0]?.dayOrder ?? 99));
    }, [filtered]);

    return (
        <AdminShell shell={shell}>
            <Head title={`Kalender Mengajar · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Akademik"
                        title="Kalender Mengajar"
                        description={hasProfile ? `${lecturerInfo.name ?? '-'} · ${lecturerInfo.study_program ?? '-'}` : 'Profil dosen belum ditemukan.'}
                        badges={[`${schedules.length} jadwal`, `${grouped.length} hari aktif`, lecturerInfo.faculty ?? '-']}
                    />

                    {!hasProfile && <div className="db-note danger"><CalendarDays size={15} /><span>Profil dosen belum ditemukan.</span></div>}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Filter size={16} /> Filter jadwal</h2></div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <label className="crud-label">Tahun akademik</label>
                                    <select className="db-input" value={year} onChange={(event) => setYear(event.target.value)}>
                                        <option value="">Semua tahun</option>
                                        {yearOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Mode</label>
                                    <select className="db-input" value={mode} onChange={(event) => setMode(event.target.value)}>
                                        <option value="">Semua mode</option>
                                        {modeOptions.map((option) => <option key={option} value={option}>{option}</option>)}
                                    </select>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                    <button className="db-btn ghost sm" type="button" onClick={() => { setYear(''); setMode(''); }}>Reset filter</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    {grouped.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body">
                                <EmptyState icon={Clock} title="Belum ada jadwal" description="Jadwal mengajar akan muncul di sini setelah kelas disusun." />
                            </div>
                        </section>
                    ) : grouped.map(([day, items]) => (
                        <section className="db-card" key={day}>
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CalendarDays size={16} /> {dayLabels[day] ?? day}</h2>
                                <span className="db-hint">{items.length} jadwal</span>
                            </div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>Waktu</th><th>Mata kuliah</th><th>Kelas</th><th>Ruang</th><th>Mode</th><th>Jenis</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {items.map((schedule) => (
                                                <tr key={schedule.id}>
                                                    <td><b>{schedule.startTime}–{schedule.endTime}</b></td>
                                                    <td><b>{schedule.courseCode} - {schedule.courseName}</b><br /><small>{schedule.academicYear} · {schedule.studyProgram}</small></td>
                                                    <td>{schedule.classLabel}</td>
                                                    <td><MapPin size={12} /> {schedule.room} ({schedule.building})</td>
                                                    <td>{schedule.deliveryMode}</td>
                                                    <td>{schedule.sessionType}</td>
                                                    <td>
                                                        {schedule.meetingLink ? (
                                                            <a className="db-btn ghost sm" href={schedule.meetingLink} target="_blank" rel="noreferrer"><ExternalLink size={12} /> Tautan</a>
                                                        ) : schedule.courseOfferingId ? (
                                                            <Link className="db-btn ghost sm" href={`/lecturer/course-offerings/${schedule.courseOfferingId}`}>Detail</Link>
                                                        ) : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </AdminShell>
    );
}
