// Dashboard mahasiswa — ringkasan akademik & keuangan (Inertia React).
import { Head } from '@inertiajs/react';
import {
    Award, BookOpen, CalendarDays, Clock3, FileText,
    GraduationCap, Info, MapPin, Megaphone, Pin, TrendingUp, Wallet,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import '../../../css/dashboard.css';

type Tile = { label: string; value: string | number | null };
type Invoice = {
    id: number; number: string; typeLabel: string; dueLabel: string | null;
    outstanding: number; outstandingLabel: string; status: string; url: string;
};
type Grade = {
    id: number; courseName: string; finalScore: string | null;
    letter: string | null; gradePoint: string | null; gradedLabel: string | null;
};
type Schedule = {
    courseName: string; hari: string; tanggal: string; timeLabel: string;
    roomLabel: string; mode: string | null;
};
type AnnouncementItem = {
    id: number; title: string; creator: string; publishedLabel: string;
    isRead: boolean; isPinned: boolean; priority: string; url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: {
        name: string; nim: string | null; programName: string; facultyName: string;
        academicStatus: string | null; semester: number | null; lastLogin: string | null;
    };
    tiles: Tile[];
    stats: { courses: number; credits: number; publishedGrades: number; passedCourses: number };
    academic: { ips: string; ipk: string; programName: string; activeYear: string };
    attendance: { total: number; attended: number; absent: number; rate: number | null };
    grades: Grade[];
    schedules: Schedule[];
    finance: {
        total: number; outstanding: number; outstandingLabel: string;
        overdue: number; paid: number; invoices: Invoice[];
    };
    announcements: { unread: number; items: AnnouncementItem[] };
    urls: {
        registration: string; studyPlan: string; grades: string; schedule: string;
        transcript: string; invoices: string; announcements: string;
    };
};

const invoiceTone = (status: string): string => {
    if (status === 'paid') return 'green';
    if (status === 'partially_paid') return 'amber';
    if (status === 'overdue') return 'red';
    return 'gray';
};

const invoiceLabel = (status: string): string => {
    const map: Record<string, string> = {
        paid: 'Lunas', partially_paid: 'Sebagian', overdue: 'Terlambat',
        issued: 'Terbit', cancelled: 'Batal', draft: 'Draft',
    };
    return map[status] ?? status.replace(/_/g, ' ');
};

const priorityTone = (priority: string): string => {
    const value = priority.toLowerCase();
    if (value.includes('urgent')) return 'red';
    if (value.includes('important')) return 'amber';
    return 'gray';
};

export default function StudentDashboard({
    shell, hasProfile, student, tiles, stats, academic, attendance,
    grades, schedules, finance, announcements, urls,
}: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <section className="db-hero">
                        <div className="db-hero-inner">
                            <div className="db-hero-copy">
                                <span className="db-hero-icon"><GraduationCap size={26} /></span>
                                <div>
                                    <small>Ruang akademik mahasiswa</small>
                                    <h1>{student.name}</h1>
                                    <p>{student.programName} · {student.facultyName}</p>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                        {student.nim && <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{student.nim}</span>}
                                        {student.semester !== null && <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>Semester {student.semester}</span>}
                                        {student.academicStatus && <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{student.academicStatus}</span>}
                                    </div>
                                </div>
                            </div>
                            <div className="db-hero-actions">
                                <a className="db-btn light" href={urls.studyPlan}><BookOpen size={15} /> KRS saya</a>
                                <a className="db-btn light" href={urls.grades}><Award size={15} /> Nilai</a>
                            </div>
                        </div>
                    </section>

                    {!hasProfile && (
                        <div className="db-note info"><Info size={15} /><span>Profil mahasiswa Anda belum terhubung. Hubungi bagian akademik agar data tampil di sini.</span></div>
                    )}

                    <section className="db-stats" aria-label="Status akademik">
                        {tiles.map((tile) => (
                            <div className="db-stat" key={tile.label}>
                                <div><span className="db-stat-num" style={{ fontSize: 17 }}>{tile.value ?? '-'}</span><span className="db-stat-label">{tile.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-stats" aria-label="Statistik akademik">
                        <a className="db-stat" href={urls.studyPlan}>
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.courses}</span><span className="db-stat-label">MK aktif · {stats.credits} SKS</span></div>
                        </a>
                        <a className="db-stat" href={urls.grades}>
                            <span className="db-stat-icon gold"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.publishedGrades}</span><span className="db-stat-label">Nilai terbit</span></div>
                        </a>
                        <a className="db-stat" href={urls.transcript}>
                            <span className="db-stat-icon green"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{stats.passedCourses}</span><span className="db-stat-label">MK lulus</span></div>
                        </a>
                        <a className="db-stat" href={urls.invoices}>
                            <span className="db-stat-icon red"><Wallet size={20} /></span>
                            <div><span className="db-stat-num" style={{ fontSize: 17 }}>{finance.total === 0 ? '–' : finance.outstandingLabel}</span><span className="db-stat-label">Sisa tagihan</span></div>
                        </a>
                    </section>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><TrendingUp size={16} /> Ringkasan akademik</h2>
                                    <a className="db-btn ghost sm" href={urls.transcript}>Transkrip</a>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-grid-2">
                                        <div className="db-summary" style={{ border: 0, padding: 0 }}>
                                            <div>
                                                <small className="db-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>IPS</small>
                                                <b style={{ fontSize: 22, fontFamily: "'Source Serif 4', Georgia, serif" }}>{academic.ips}</b>
                                            </div>
                                        </div>
                                        <div className="db-summary" style={{ border: 0, padding: 0 }}>
                                            <div>
                                                <small className="db-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>IPK</small>
                                                <b style={{ fontSize: 22, fontFamily: "'Source Serif 4', Georgia, serif" }}>{academic.ipk}</b>
                                            </div>
                                        </div>
                                    </div>
                                    <div style={{ display: 'grid', marginTop: 6 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0', borderBottom: '1px solid var(--db-line)' }}>
                                            <small className="db-hint">Program studi</small><b style={{ fontSize: 13 }}>{academic.programName}</b>
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '9px 0' }}>
                                            <small className="db-hint">Tahun aktif</small><b style={{ fontSize: 13 }}>{academic.activeYear}</b>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Clock3 size={16} /> Kehadiran</h2>
                                    <a className="db-btn ghost sm" href={urls.schedule}>Jadwal</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
                                    <span
                                        aria-label={`Kehadiran ${attendance.rate ?? '-'} persen`}
                                        style={{
                                            display: 'grid', placeItems: 'center', width: 92, height: 92, flex: '0 0 auto',
                                            borderRadius: '50%',
                                            background: `conic-gradient(var(--db-brand) ${attendance.rate ?? 0}%, var(--db-line) 0)`,
                                        }}
                                    >
                                        <span style={{ display: 'grid', placeItems: 'center', width: 70, height: 70, borderRadius: '50%', background: 'var(--db-card)', color: 'var(--db-heading)', fontWeight: 800, fontSize: 17 }}>
                                            {attendance.rate !== null ? `${attendance.rate}%` : '-'}
                                        </span>
                                    </span>
                                    <div style={{ display: 'grid', gap: 4 }}>
                                        <small className="db-hint">{attendance.attended} hadir dari {attendance.total} presensi</small>
                                        <small className="db-hint">{attendance.absent} tidak hadir</small>
                                    </div>
                                </div>
                            </section>

                            {grades.length > 0 && (
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Award size={16} /> Nilai terbaru</h2>
                                    <a className="db-btn ghost sm" href={urls.grades}>Semua nilai</a>
                                </div>
                                <div className="db-card-body tight">
                                    {grades.map((grade) => (
                                        <div className="db-summary" key={grade.id}>
                                            <div>
                                                <b>{grade.courseName}</b>
                                                <small>
                                                    Nilai akhir {grade.finalScore ?? '-'} · Huruf {grade.letter ?? '-'}
                                                    {grade.gradedLabel ? ` · ${grade.gradedLabel}` : ''}
                                                </small>
                                            </div>
                                            <span className="db-badge">GP {grade.gradePoint ?? '-'}</span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                            )}
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={16} /> Jadwal kuliah</h2>
                                    <a className="db-btn ghost sm" href={urls.schedule}>Buka jadwal</a>
                                </div>
                                <div className="db-card-body tight">
                                    {schedules.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada jadwal pada KRS berjalan.</p>
                                    )}
                                    {schedules.map((schedule, index) => (
                                        <div className="db-summary" key={`${schedule.courseName}-${index}`}>
                                            <div>
                                                <b>{schedule.courseName}</b>
                                                <small>{schedule.hari}, {schedule.tanggal} · {schedule.timeLabel}</small>
                                            </div>
                                            <div style={{ textAlign: 'right' }}>
                                                <span className={`db-badge ${schedule.mode === 'Online' ? '' : 'green'}`}>{schedule.mode ?? '-'}</span>
                                                <small className="db-hint" style={{ display: 'flex', alignItems: 'center', gap: 4, justifyContent: 'flex-end', marginTop: 4 }}>
                                                    <MapPin size={11} /> {schedule.roomLabel}
                                                </small>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Wallet size={16} /> Keuangan</h2>
                                    <a className="db-btn ghost sm" href={urls.invoices}>Tagihan</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    {finance.total === 0 ? (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada tagihan yang diterbitkan untuk Anda.</p>
                                    ) : (
                                        <>
                                            <div className="db-grid-2">
                                                <div>
                                                    <small className="db-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>Sisa</small>
                                                    <b style={{ fontSize: 20, fontFamily: "'Source Serif 4', Georgia, serif", color: finance.outstanding > 0 ? 'var(--db-red)' : 'var(--db-green)' }}>
                                                        {finance.outstandingLabel}
                                                    </b>
                                                </div>
                                                <div>
                                                    <small className="db-hint" style={{ fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em' }}>Status</small>
                                                    <div style={{ display: 'flex', gap: 6, marginTop: 4, flexWrap: 'wrap' }}>
                                                        <span className="db-badge red">{finance.overdue} terlambat</span>
                                                        <span className="db-badge green">{finance.paid} lunas</span>
                                                    </div>
                                                </div>
                                            </div>
                                            {finance.outstanding === 0 && (
                                                <p className="db-hint" style={{ margin: 0 }}><b style={{ color: 'var(--db-green)' }}>Semua tagihan lunas.</b> Riwayat di bawah untuk arsip.</p>
                                            )}
                                            {finance.invoices.map((invoice) => (
                                                <a key={invoice.id} href={invoice.url} className="db-class" style={{ textDecoration: 'none' }}>
                                                    <div className="db-class-top">
                                                        <div>
                                                            <b>{invoice.number}</b>
                                                            <small>{invoice.typeLabel}{invoice.dueLabel ? ` · jatuh tempo ${invoice.dueLabel}` : ''}</small>
                                                        </div>
                                                        <div style={{ textAlign: 'right' }}>
                                                            <b style={{ display: 'block', color: invoice.outstanding > 0 ? 'var(--db-red)' : 'var(--db-green)', fontSize: 13 }}>{invoice.outstandingLabel}</b>
                                                            <span className={`db-badge ${invoiceTone(invoice.status)}`}>{invoiceLabel(invoice.status)}</span>
                                                        </div>
                                                    </div>
                                                </a>
                                            ))}
                                        </>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h3 className="db-card-title"><Megaphone size={16} /> Pengumuman</h3>
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                        {announcements.unread > 0 && <span className="db-badge red">{announcements.unread} baru</span>}
                                        <a className="db-btn ghost sm" href={urls.announcements}>Semua</a>
                                    </div>
                                </div>
                                <div className="db-card-body tight">
                                    {announcements.items.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada pengumuman untuk Anda.</p>
                                    )}
                                    {announcements.items.map((item) => (
                                        <a key={item.id} href={item.url} className={`db-ann${item.isRead ? '' : ' unread'}`}>
                                            <span className="db-ann-dot" />
                                            <div>
                                                <b>{item.isPinned ? <Pin size={12} style={{ verticalAlign: -1 }} /> : null}{item.isPinned ? ' ' : ''}{item.title}</b>
                                                <small>{item.creator} · {item.publishedLabel}</small>
                                            </div>
                                            <span className={`db-badge ${priorityTone(item.priority)}`}>{item.priority}</span>
                                        </a>
                                    ))}
                                </div>
                            </section>

                            <div className="db-note info">
                                <Info size={15} />
                                <span>IPK dihitung dari nilai yang sudah terbit. Nilai yang belum keluar tidak memengaruhi perhitungan.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
