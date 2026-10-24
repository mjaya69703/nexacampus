// Rekap absensi satu kelas (Inertia React).
// Hanya kelas yang masih aktif di KRS yang boleh dibuka.
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft, BookOpen, CalendarCheck, CalendarDays, CheckCircle2, Clock,
    Inbox, Lock, QrCode, TriangleAlert,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Course = {
    id: number;
    name: string;
    code: string;
    classLabel: string | null;
    academicYear: string | null;
    registrationStatus: string | null;
};

type Item = {
    sessionId: number;
    meetingNo: number | null;
    meetingDate: string;
    timeRange: string;
    topic: string;
    sessionStatus: string;
    attendanceStatus: string;
    attendanceTone: string;
    canRecord: boolean;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    hasAccess: boolean;
    course: Course;
    items: Item[];
    summary: { totalMeetings: number; opened: number; attended: number; notInput: number };
    urls: { schedule: string; materials: string; record: string };
};

const withId = (url: string, id: number): string => url.replace('__ID__', String(id));

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        default:
            return 'gray';
    }
};

export default function ScheduleAttendance({ shell, hasProfile, hasAccess, course, items, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi ${course.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Academic"
                        title={`Absensi ${course.name}`}
                        description={`${course.code}${course.classLabel ? ` · Kelas ${course.classLabel}` : ''} — ${course.academicYear ?? 'tahun akademik aktif'}`}
                        badges={[`${summary.totalMeetings} pertemuan`, `${summary.attended} hadir`]}
                        actions={(
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link href={urls.schedule} className="db-btn ghost"><ArrowLeft size={15} /> Jadwal</Link>
                                <Link href={`${urls.materials}?offeringId=${course.id}`} className="db-btn ghost"><BookOpen size={15} /> Materi</Link>
                            </div>
                        )}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data.</span>
                        </div>
                    )}

                    {hasProfile && !hasAccess && (
                        <div className="db-note warning">
                            <Lock size={15} />
                            <span>
                                <b>Kelas ini tidak bisa dibuka.</b> Absensi hanya tersedia untuk kelas yang masih aktif di KRS
                                yang sudah disetujui{! course.registrationStatus ? '' : ` (status registrasi: ${course.registrationStatus})`}.
                            </span>
                        </div>
                    )}

                    {hasAccess && (
                        <>
                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon"><CalendarDays size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.totalMeetings}</span>
                                        <span className="db-stat-label">Total pertemuan</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><QrCode size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.opened}</span>
                                        <span className="db-stat-label">Sesi dibuka</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.attended}</span>
                                        <span className="db-stat-label">Hadir / izin</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><Clock size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.notInput}</span>
                                        <span className="db-stat-label">Belum diinput</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarCheck size={16} /> Riwayat Pertemuan</h2>
                                    <span className="db-badge gray">{items.length} pertemuan</span>
                                </div>
                                <div className="db-card-body">
                                    {items.length === 0 ? (
                                        <EmptyState
                                            icon={Inbox}
                                            title="Belum ada pertemuan"
                                            description="Dosen belum membuat sesi absensi untuk kelas ini."
                                        />
                                    ) : (
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead>
                                                    <tr>
                                                        <th>Pertemuan</th>
                                                        <th>Tanggal</th>
                                                        <th>Jam</th>
                                                        <th>Topik</th>
                                                        <th>Status sesi</th>
                                                        <th>Kehadiran</th>
                                                        <th />
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {items.map((item) => (
                                                        <tr key={item.sessionId}>
                                                            <td style={{ fontWeight: 700 }}>{item.meetingNo ?? '-'}</td>
                                                            <td>{item.meetingDate}</td>
                                                            <td className="db-hint">{item.timeRange}</td>
                                                            <td className="db-hint">{item.topic}</td>
                                                            <td>
                                                                <span className={`db-badge ${item.sessionStatus === 'Opened' ? 'green' : 'gray'}`}>
                                                                    {item.sessionStatus === 'Opened' ? 'Dibuka' : item.sessionStatus === 'Closed' ? 'Ditutup' : item.sessionStatus}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span className={`db-badge ${toneClass(item.attendanceTone)}`}>{item.attendanceStatus}</span>
                                                            </td>
                                                            <td style={{ textAlign: 'right' }}>
                                                                <Link href={withId(urls.record, item.sessionId)} className="db-btn ghost sm">
                                                                    <QrCode size={13} /> {item.canRecord ? 'Isi Absensi' : 'Detail'}
                                                                </Link>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
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
