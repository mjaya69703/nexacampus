// Detail kelas dosen — ringkasan, jadwal, sesi absensi, materi, dan tugas.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, CalendarDays, ClipboardList, FileText, GraduationCap, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = {
    id: number; courseCode: string; courseName: string; label: string; classCode: string;
    academicYear: string; studyProgram: string; semesterNo: number | null; credits: number;
    capacity: number | null; enrolledCount: number; deliveryMode: string; status: string;
    lecturerRole: string; classStartDate: string; classEndDate: string;
};

type Stats = { students: number; sessions: number; materials: number; assignments: number; completedSessions: number };
type Schedule = { id: number; day: string; time: string; sessionType: string; deliveryMode: string; room: string; building: string };
type SessionRow = { id: number; meetingNo: number | null; date: string; time: string; topic: string; status: string; recordsCount: number; editUrl: string };
type MaterialRow = { id: number; title: string; category: string; meetingNumber: number | null; isPublished: boolean; filesCount: number; url: string };
type AssignmentRow = { id: number; title: string; dueAt: string; isPublished: boolean; submitted: number; graded: number; url: string };

type Props = {
    shell: ShellProps;
    offering: Offering;
    stats: Stats;
    schedules: Schedule[];
    sessions: SessionRow[];
    materials: MaterialRow[];
    assignments: AssignmentRow[];
    urls: {
        index: string; students: string; attendance: string; grades: string;
        materials: string; assignments: string; createAssignment: string;
    };
};

const statusTone = (status: string) => {
    if (['Closed', 'Finalized', 'Published', 'Aktif', 'Active'].includes(status)) return 'green';
    if (['Opened'].includes(status)) return 'amber';
    if (['Cancelled'].includes(status)) return 'red';
    return 'gray';
};

export default function CourseOfferingsShow({ shell, offering, stats, schedules, sessions, materials, assignments, urls }: Props) {
    const statCards = [
        { label: 'Mahasiswa', value: stats.students, icon: <Users size={20} />, tone: 'db-stat-icon' },
        { label: 'Sesi absensi', value: stats.sessions, icon: <CalendarDays size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Materi', value: stats.materials, icon: <FileText size={20} />, tone: 'db-stat-icon green' },
        { label: 'Tugas', value: stats.assignments, icon: <ClipboardList size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`${offering.courseCode} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik · Kelas saya"
                        title={`${offering.courseCode} — ${offering.courseName}`}
                        description={`${offering.label} · ${offering.academicYear} · ${offering.studyProgram}`}
                        badges={[offering.status, `${stats.students} mahasiswa`, `${stats.completedSessions}/${stats.sessions} sesi selesai`]}
                        actions={<Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Semua kelas</Link>}
                    />

                    <section className="db-stats" aria-label="Ringkasan kelas">
                        {statCards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div>
                                    <span className="db-stat-num">{card.value}</span>
                                    <span className="db-stat-label">{card.label}</span>
                                </div>
                            </div>
                        ))}
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Informasi kelas</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 8, fontSize: 13 }}>
                                <div><b>Kode kelas:</b> {offering.classCode}</div>
                                <div><b>Tahun akademik:</b> {offering.academicYear}</div>
                                <div><b>Program studi:</b> {offering.studyProgram}</div>
                                <div><b>Semester:</b> {offering.semesterNo ?? '-'} · <b>SKS:</b> {offering.credits}</div>
                                <div><b>Kapasitas:</b> {offering.enrolledCount}/{offering.capacity ?? '∞'}</div>
                                <div><b>Mode:</b> {offering.deliveryMode} · <b>Peran:</b> {offering.lecturerRole}</div>
                                <div><b>Periode:</b> {offering.classStartDate} — {offering.classEndDate}</div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Akses cepat</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link href={urls.students} className="db-btn ghost"><Users size={14} /> Mahasiswa</Link>
                                <Link href={urls.attendance} className="db-btn ghost"><CalendarDays size={14} /> Absensi</Link>
                                <Link href={urls.grades} className="db-btn ghost"><GraduationCap size={14} /> Nilai</Link>
                                <Link href={urls.materials} className="db-btn ghost"><FileText size={14} /> Materi</Link>
                                <Link href={urls.assignments} className="db-btn ghost"><ClipboardList size={14} /> Semua tugas</Link>
                                <Link href={urls.createAssignment} className="db-btn primary"><ClipboardList size={14} /> Buat tugas</Link>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Jadwal mengajar</h2>
                        </div>
                        <div className="db-card-body">
                            {schedules.length === 0 ? (
                                <p className="db-hint">Belum ada jadwal aktif untuk kelas ini.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Hari</th><th>Waktu</th><th>Jenis</th><th>Mode</th><th>Ruang</th><th>Gedung</th></tr>
                                        </thead>
                                        <tbody>
                                            {schedules.map((schedule) => (
                                                <tr key={schedule.id}>
                                                    <td>{schedule.day}</td>
                                                    <td>{schedule.time}</td>
                                                    <td>{schedule.sessionType}</td>
                                                    <td>{schedule.deliveryMode}</td>
                                                    <td>{schedule.room}</td>
                                                    <td>{schedule.building}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CalendarDays size={16} /> Sesi terbaru</h2>
                                <Link className="db-btn ghost sm" href={urls.attendance}>Semua sesi</Link>
                            </div>
                            <div className="db-card-body tight">
                                {sessions.length === 0 && <p className="db-hint">Belum ada sesi absensi.</p>}
                                {sessions.slice().reverse().slice(0, 5).map((session) => (
                                    <div className="db-summary" key={session.id}>
                                        <div>
                                            <b>Pertemuan {session.meetingNo ?? '-'} · {session.topic}</b>
                                            <small>{session.date} · {session.time} · {session.recordsCount} tercatat</small>
                                        </div>
                                        <span className={`db-badge ${statusTone(session.status)}`}>{session.status}</span>
                                        <a className="db-btn ghost sm" href={session.editUrl}>Absensi</a>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><FileText size={16} /> Materi terbaru</h2>
                                <Link className="db-btn ghost sm" href={urls.materials}>Kelola</Link>
                            </div>
                            <div className="db-card-body tight">
                                {materials.length === 0 && <p className="db-hint">Belum ada materi yang diunggah.</p>}
                                {materials.map((material) => (
                                    <div className="db-summary" key={material.id}>
                                        <div>
                                            <b>{material.title}</b>
                                            <small>Pertemuan {material.meetingNumber ?? '-'} · {material.filesCount} file</small>
                                        </div>
                                        <span className={`db-badge ${material.isPublished ? 'green' : 'gray'}`}>{material.isPublished ? 'Published' : 'Draft'}</span>
                                        <a className="db-btn ghost sm" href={material.url}>Buka</a>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Tugas terbaru</h2>
                            <Link className="db-btn ghost sm" href={urls.assignments}>Semua tugas</Link>
                        </div>
                        <div className="db-card-body">
                            {assignments.length === 0 ? (
                                <p className="db-hint">Belum ada tugas untuk kelas ini.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Judul</th><th>Deadline</th><th>Status</th><th>Dikumpulkan</th><th>Dinilai</th><th>Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            {assignments.map((assignment) => (
                                                <tr key={assignment.id}>
                                                    <td><b>{assignment.title}</b></td>
                                                    <td>{assignment.dueAt}</td>
                                                    <td><span className={`db-badge ${assignment.isPublished ? 'green' : 'gray'}`}>{assignment.isPublished ? 'Published' : 'Draft'}</span></td>
                                                    <td>{assignment.submitted}</td>
                                                    <td>{assignment.graded}</td>
                                                    <td><a className="db-btn ghost sm" href={assignment.url}>Review</a></td>
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
