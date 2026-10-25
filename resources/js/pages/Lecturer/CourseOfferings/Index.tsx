// Daftar kelas yang diampu dosen beserta ringkasan jadwal dan mahasiswa.
import { Head, Link } from '@inertiajs/react';
import { BookOpen, CalendarDays, Filter, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = {
    id: number; courseCode: string; courseName: string; classCode: string; label: string; academicYear: string;
    studyProgram: string; status: string; lecturerRole: string; scheduleCount: number; sessionCount: number;
    capacity: number | null; enrolledCount: number; room: string; building: string; url: string;
};

type Stats = { totalOfferings: number; totalSchedules: number; totalSessions: number; totalStudents: number };
type Info = { name?: string; study_program?: string; faculty?: string; employment_status?: string };

type Props = {
    shell: ShellProps; hasProfile: boolean; lecturerInfo: Info; offerings: Offering[];
    yearOptions: string[]; statusOptions: string[]; modeOptions: string[]; stats: Stats; urls: { index: string };
};

export default function CourseOfferingsIndex({ shell, hasProfile, lecturerInfo, offerings, stats }: Props) {
    const [year, setYear] = useState('');
    const [status, setStatus] = useState('');

    const filtered = useMemo(
        () => offerings.filter((offering) => (!year || offering.academicYear === year) && (!status || offering.status === status)),
        [offerings, year, status],
    );

    const cards = [
        { label: 'Kelas', value: stats.totalOfferings, icon: <BookOpen size={20} />, tone: 'db-stat-icon' },
        { label: 'Mahasiswa', value: stats.totalStudents, icon: <Users size={20} />, tone: 'db-stat-icon green' },
        { label: 'Jadwal aktif', value: stats.totalSchedules, icon: <CalendarDays size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Sesi absensi', value: stats.totalSessions, icon: <CalendarDays size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Kelas Saya · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title="Kelas Saya"
                        description={hasProfile ? `${lecturerInfo.name ?? '-'} · ${lecturerInfo.study_program ?? '-'}` : 'Profil dosen belum ditemukan.'}
                        badges={[`${stats.totalOfferings} kelas`, `${stats.totalStudents} mahasiswa`, `${stats.totalSchedules} jadwal`]}
                    />

                    {!hasProfile && <div className="db-note danger"><BookOpen size={15} /><span>Profil dosen belum ditemukan. Hubungi administrator.</span></div>}

                    <section className="db-stats" aria-label="Ringkasan kelas">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Filter size={16} /> Filter kelas</h2></div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <label className="crud-label">Tahun akademik</label>
                                    <select className="db-input" value={year} onChange={(event) => setYear(event.target.value)}>
                                        <option value="">Semua tahun</option>
                                        {[...new Set(offerings.map((offering) => offering.academicYear))].map((option) => <option key={option} value={option}>{option}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Status</label>
                                    <select className="db-input" value={status} onChange={(event) => setStatus(event.target.value)}>
                                        <option value="">Semua status</option>
                                        {[...new Set(offerings.map((offering) => offering.status))].map((option) => <option key={option} value={option}>{option}</option>)}
                                    </select>
                                </div>
                                <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                    <button className="db-btn ghost sm" type="button" onClick={() => { setYear(''); setStatus(''); }}>Reset filter</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar kelas ({filtered.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {filtered.length === 0 ? (
                                <EmptyState icon={Users} title="Belum ada kelas" description="Kelas yang Anda ampu akan muncul di sini." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th><th>Status</th><th>Mahasiswa</th><th>Jadwal</th><th>Peran</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {filtered.map((offering) => (
                                                <tr key={offering.id}>
                                                    <td><b>{offering.courseCode} - {offering.courseName}</b><br /><small>{offering.studyProgram}</small></td>
                                                    <td>{offering.label}</td>
                                                    <td>{offering.academicYear}</td>
                                                    <td><span className="db-badge gray">{offering.status}</span></td>
                                                    <td>{offering.enrolledCount}/{offering.capacity ?? '∞'}</td>
                                                    <td>{offering.scheduleCount} jadwal · {offering.sessionCount} sesi</td>
                                                    <td>{offering.lecturerRole}</td>
                                                    <td><Link href={offering.url} className="db-btn ghost sm">Detail</Link></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
