// Jadwal kuliah mahasiswa (Inertia React).
// Jadwal dibangun dari course_schedules; sesi absensi minggu ini digabung
// ke baris jadwal yang cocok agar tidak duplikat.
import { Head, Link } from '@inertiajs/react';
import {
    BookOpen, CalendarDays, CalendarRange, CheckCircle2, Clock, MapPin,
    MonitorPlay, QrCode, TriangleAlert, UserCheck,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type State = {
    hasProfile: boolean;
    hasAcademicYear: boolean;
    registrationStatus: string | null;
    academicStatus: string | null;
    studyPlanStatus: string | null;
    hasApprovedRegistration: boolean;
    isAcademicallyActive: boolean;
    hasStudyPlan: boolean;
    hasApprovedStudyPlan: boolean;
    hasSchedules: boolean;
};

type Student = {
    name: string;
    nim: string;
    program: string;
    semester: number | null;
    academicYear: string | null;
};

type Item = {
    key: string;
    dayKey: string;
    startTime: string;
    endTime: string;
    courseName: string;
    courseCode: string;
    classLabel: string | null;
    room: string;
    building: string;
    deliveryMode: string;
    sessionType: string;
    meetingNo: number | null;
    meetingDate: string | null;
    sessionId: number | null;
    sessionStatus: string | null;
    offeringId: number;
    attendanceStatus: string;
    attendanceTone: string;
    canRecord: boolean;
};

type Day = { key: string; label: string; order: number; items: Item[] };

type Props = {
    shell: ShellProps;
    state: State;
    student: Student;
    weekLabel: string | null;
    days: Day[];
    stats: {
        weeklySessions: number;
        attended: number;
        opened: number;
        courses: number;
        credits: number;
        weeklyClasses: number;
    };
    urls: { index: string; attendance: string; record: string; materials: string };
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

const modeLabel = (mode: string): string => {
    switch (mode) {
        case 'Online':
            return 'Daring';
        case 'Hybrid':
            return 'Hybrid';
        case 'Offline':
            return 'Luring';
        default:
            return mode;
    }
};

function ScheduleItem({ item, urls }: { item: Item; urls: Props['urls'] }) {
    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>{item.courseName}</b>
                    <small>
                        {item.courseCode}
                        {item.classLabel ? ` · Kelas ${item.classLabel}` : ''}
                        {item.sessionType && item.sessionType !== '-' ? ` · ${item.sessionType}` : ''}
                    </small>
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                    <span className="db-badge">{item.startTime} – {item.endTime}</span>
                    <span className={`db-badge ${toneClass(item.attendanceTone)}`}>{item.attendanceStatus}</span>
                </div>
            </div>

            <div className="db-hint" style={{ marginTop: 8, display: 'flex', gap: 12, flexWrap: 'wrap' }}>
                <span><MapPin size={11} /> {item.room}{item.building !== '-' ? ` · ${item.building}` : ''}</span>
                <span><MonitorPlay size={11} /> {modeLabel(item.deliveryMode)}</span>
                {item.meetingNo !== null && (
                    <span><CalendarDays size={11} /> Pertemuan {item.meetingNo}{item.meetingDate ? ` · ${item.meetingDate}` : ''}</span>
                )}
                {item.sessionStatus === 'Opened' && <span className="db-badge green">Sesi QR dibuka</span>}
            </div>

            <div className="db-class-actions">
                <Link href={withId(urls.attendance, item.offeringId)} className="db-btn ghost sm">
                    <UserCheck size={13} /> Rekap Absensi
                </Link>
                {item.sessionId !== null && (
                    <Link href={withId(urls.record, item.sessionId)} className="db-btn ghost sm">
                        <QrCode size={13} /> Isi Absensi
                    </Link>
                )}
                <Link href={`${urls.materials}?offeringId=${item.offeringId}`} className="db-btn ghost sm">
                    <BookOpen size={13} /> Materi
                </Link>
            </div>
        </article>
    );
}

export default function ScheduleIndex({ shell, state, student, weekLabel, days, stats, urls }: Props) {
    const hasAnyItem = days.some((day) => day.items.length > 0);

    const blockedMessage = (): { tone: string; title: string; body: string } | null => {
        if (!state.hasProfile) {
            return {
                tone: 'danger',
                title: 'Profil mahasiswa belum ditemukan',
                body: 'Hubungi admin akademik untuk sinkronisasi data mahasiswa.',
            };
        }

        if (!state.hasAcademicYear) {
            return {
                tone: 'warning',
                title: 'Tahun akademik aktif belum ditetapkan',
                body: 'Jadwal kuliah akan muncul setelah admin membuka tahun akademik.',
            };
        }

        if (!state.hasApprovedRegistration) {
            return {
                tone: 'warning',
                title: 'Registrasi semester belum disetujui',
                body: `Status registrasi: ${state.registrationStatus ?? 'belum ada'}. Selesaikan registrasi untuk melihat jadwal.`,
            };
        }

        if (!state.isAcademicallyActive) {
            return {
                tone: 'danger',
                title: 'Status akademik tidak aktif',
                body: `Status akademik saat ini: ${state.academicStatus ?? '-'}. Hubungi admin akademik bila ini keliru.`,
            };
        }

        if (!state.hasStudyPlan) {
            return {
                tone: 'warning',
                title: 'KRS semester ini belum dibuat',
                body: 'Susun KRS terlebih dahulu agar jadwal kuliah bisa ditampilkan.',
            };
        }

        if (!state.hasApprovedStudyPlan) {
            return {
                tone: 'warning',
                title: 'KRS belum disetujui',
                body: `Status KRS: ${state.studyPlanStatus ?? '-'}. Jadwal muncul setelah KRS disetujui dosen pembimbing.`,
            };
        }

        return null;
    };

    const blocked = blockedMessage();

    return (
        <AdminShell shell={shell}>
            <Head title={`Jadwal Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarRange}
                        eyebrow="Academic"
                        title="Jadwal Kuliah"
                        description={`Jadwal mingguan dari kelas yang sudah disetujui di KRS — ${student.program}.`}
                        badges={[
                            `NIM ${student.nim}`,
                            `Semester ${student.semester ?? '-'}`,
                            ...(weekLabel ? [weekLabel] : []),
                        ]}
                    />

                    {blocked && (
                        <div className={`db-note ${blocked.tone}`}>
                            <TriangleAlert size={15} />
                            <span><b>{blocked.title}.</b> {blocked.body}</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.courses}</span>
                                <span className="db-stat-label">Mata kuliah</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarRange size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.weeklyClasses}</span>
                                <span className="db-stat-label">Kelas minggu ini</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.attended}</span>
                                <span className="db-stat-label">Sudah diabsen</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><QrCode size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.opened}</span>
                                <span className="db-stat-label">Sesi QR dibuka</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Jadwal Mingguan</h2>
                            <span className="db-badge gray">{stats.credits} SKS · {weekLabel ?? 'minggu ini'}</span>
                        </div>
                        <div className="db-card-body">
                            {!hasAnyItem ? (
                                <EmptyState
                                    icon={CalendarDays}
                                    title="Belum ada jadwal"
                                    description={blocked
                                        ? 'Penuhi prasyarat di atas untuk menampilkan jadwal kuliah.'
                                        : 'Belum ada jadwal aktif untuk kelas yang kamu ambil pada semester ini.'}
                                />
                            ) : (
                                <div className="db-grid-2" style={{ alignItems: 'start' }}>
                                    {days.map((day) => (
                                        <div key={day.key}>
                                            <div className="crud-label" style={{ marginBottom: 8 }}>{day.label}</div>
                                            {day.items.length === 0 ? (
                                                <div className="db-empty-calm">
                                                    <Clock size={20} />
                                                    <div><b>Tidak ada kelas</b><small>Hari {day.label} kosong.</small></div>
                                                </div>
                                            ) : (
                                                <div style={{ display: 'grid', gap: 10 }}>
                                                    {day.items.map((item) => (
                                                        <ScheduleItem key={item.key} item={item} urls={urls} />
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="db-note muted">
                        <TriangleAlert size={15} />
                        <span>
                            Absensi hanya bisa diisi saat dosen membuka sesi QR. Scan QR dari perangkatmu pada halaman isi absensi;
                            jangan membagikan tautan QR ke mahasiswa lain.
                        </span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
