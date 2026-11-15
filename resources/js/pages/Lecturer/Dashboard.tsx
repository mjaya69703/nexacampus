// Dashboard dosen — konsep "pagi ini sebagai dosen" (Inertia React).
// Blok: perlu tindakan, 7 hari ke depan, beban & progres, info.
import { Head } from '@inertiajs/react';
import {
    Award, BookOpen, CalendarDays, ChevronRight, CircleCheck, FileText,
    FlaskConical, GraduationCap, Info, ListChecks, Megaphone, Pin, Presentation, Sprout,
    UserRound, UsersRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import '../../../css/dashboard.css';

type InboxItem = { label: string; url: string };
type InboxRow = {
    key: string; tone: string; title: string; count: number;
    items: InboxItem[]; moreUrl: string; moreLabel: string;
};
type WeekItem = {
    kind: string; dateLabel: string; timeLabel: string;
    title: string; subtitle: string; url: string;
};
type ClassRow = {
    id: number; code: string; name: string; label: string | null; yearName: string | null;
    students: number; graded: number; totalGrades: number;
    attendanceUrl: string; gradesUrl: string; materialsUrl: string;
};
type AnnouncementItem = {
    id: number; title: string; creator: string; publishedLabel: string;
    isRead: boolean; isPinned: boolean; priority: string; url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    lecturer: { name: string; programName?: string | null; facultyName?: string | null; activeYearName?: string | null };
    inbox: InboxRow[];
    summary: {
        classes: number; sks: number; students: number; sessionsWeek: number;
        advisees: number; yearName: string | null;
        classesUrl: string; calendarUrl: string; advisingUrl: string;
    };
    week: WeekItem[];
    classes: ClassRow[];
    classesUrl: string;
    gradesUrl: string;
    bkd: { periodName: string | null; status: string | null; statusLabel: string; url: string } | null;
    tridharma: { toSubmit: number; inProgress: number; url: string } | null;
    announcements: { unread: number; allUrl: string; items: AnnouncementItem[] };
};

const priorityTone = (priority: string): string => {
    const value = priority.toLowerCase();
    if (value.includes('urgent') || value.includes('penting')) return 'red';
    if (value.includes('important') || value.includes('tinggi')) return 'amber';
    return 'gray';
};

const weekDot = (kind: string): string => (kind === 'deadline' ? 'gold' : kind === 'consultation' ? 'green' : '');

export default function LecturerDashboard({ shell, hasProfile, lecturer, inbox, summary, week, classes, classesUrl, gradesUrl, bkd, tridharma, announcements }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <section className="db-hero">
                        <div className="db-hero-inner">
                            <div className="db-hero-copy">
                                <span className="db-hero-icon"><Presentation size={26} /></span>
                                <div>
                                    <small>Ruang kerja dosen</small>
                                    <h1>Selamat datang, {lecturer.name}</h1>
                                    <p>
                                        {[lecturer.programName, lecturer.facultyName].filter(Boolean).join(' · ') || 'Dosen'}
                                        {lecturer.activeYearName ? ` · Tahun ${lecturer.activeYearName}` : ''}
                                    </p>
                                </div>
                            </div>
                            <div className="db-hero-actions">
                                <a className="db-btn light" href={classesUrl}><BookOpen size={15} /> Kelas saya</a>
                                <a className="db-btn light" href={gradesUrl}><GraduationCap size={15} /> Input nilai</a>
                            </div>
                        </div>
                    </section>

                    {!hasProfile && (
                        <div className="db-note info"><Info size={15} /><span>Profil dosen Anda belum dilengkapi. Hubungi bagian akademik agar data mengajar tampil di sini.</span></div>
                    )}

                    <section className="db-stats" aria-label={`Ringkasan ${summary.yearName ?? 'semester ini'}`}>
                        <a className="db-stat" href={summary.classesUrl}>
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{summary.classes}</span><span className="db-stat-label">Kelas</span></div>
                        </a>
                        <a className="db-stat" href={summary.classesUrl}>
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{summary.sks}</span><span className="db-stat-label">SKS</span></div>
                        </a>
                        <a className="db-stat" href={summary.classesUrl}>
                            <span className="db-stat-icon green"><UsersRound size={20} /></span>
                            <div><span className="db-stat-num">{summary.students}</span><span className="db-stat-label">Mahasiswa</span></div>
                        </a>
                        <a className="db-stat" href={summary.calendarUrl}>
                            <span className="db-stat-icon"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{summary.sessionsWeek}</span><span className="db-stat-label">Sesi 7 hari</span></div>
                        </a>
                        {summary.advisees > 0 && (
                            <a className="db-stat" href={summary.advisingUrl}>
                                <span className="db-stat-icon gold"><UserRound size={20} /></span>
                                <div><span className="db-stat-num">{summary.advisees}</span><span className="db-stat-label">Bimbingan</span></div>
                            </a>
                        )}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ListChecks size={16} /> Perlu tindakan</h2>
                            {inbox.length > 0 && <span className="db-badge red">{inbox.reduce((sum, row) => sum + row.count, 0)}</span>}
                        </div>
                        <div className="db-card-body tight">
                            {inbox.length === 0 && (
                                <div className="db-empty-calm">
                                    <CircleCheck size={22} />
                                    <div><b>Tidak ada yang menunggu.</b><small>Semua sesi, nilai, dan pengajuan sudah beres. Nikmati harimu.</small></div>
                                </div>
                            )}
                            {inbox.map((row) => (
                                <div className="db-inbox-row" key={row.key}>
                                    <div className="db-inbox-main">
                                        <b>{row.title}</b>
                                        {row.items.length > 0 && (
                                            <div className="db-inbox-items">
                                                {row.items.map((item) => (
                                                    <a key={`${row.key}-${item.label}`} href={item.url}>
                                                        <ChevronRight size={13} /><span>{item.label}</span>
                                                    </a>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    <span className="db-inbox-count">{row.count}</span>
                                    <a className="db-btn ghost sm" href={row.moreUrl}>{row.moreLabel}</a>
                                </div>
                            ))}
                        </div>
                    </section>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={16} /> 7 hari ke depan</h2>
                                    {week.length > 0 && <span className="db-badge gray">{week.length}</span>}
                                </div>
                                <div className="db-card-body tight">
                                    {week.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada jadwal, tenggat, atau konsultasi dalam 7 hari ke depan.</p>
                                    )}
                                    {week.length > 0 && (
                                        <div className="db-timeline">
                                            {week.map((item, index) => (
                                                <div className="db-timeline-item" key={`${item.kind}-${item.title}-${index}`}>
                                                    <span className={`db-timeline-dot ${weekDot(item.kind)}`} />
                                                    <div className="db-timeline-main">
                                                        <div style={{ minWidth: 0 }}>
                                                            <b>{item.title}</b>
                                                            {item.subtitle && <small>{item.subtitle}</small>}
                                                        </div>
                                                        <div className="db-timeline-when">
                                                            <b>{item.dateLabel}</b>
                                                            <small>{item.timeLabel}</small>
                                                        </div>
                                                    </div>
                                                    <a href={item.url} style={{ color: 'var(--db-brand)', fontSize: 12, fontWeight: 700, textDecoration: 'none' }}>
                                                        Buka <ChevronRight size={12} style={{ verticalAlign: -1 }} />
                                                    </a>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><BookOpen size={16} /> Kelas semester ini</h2>
                                    <a className="db-btn ghost sm" href={classesUrl}>Semua kelas</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {classes.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada kelas di tahun akademik aktif.</p>
                                    )}
                                    {classes.map((kelas) => {
                                        const percent = kelas.totalGrades > 0 ? Math.round((kelas.graded / kelas.totalGrades) * 100) : 0;
                                        return (
                                            <div className="db-class" key={kelas.id}>
                                                <div className="db-class-top">
                                                    <div>
                                                        <b>{kelas.code} · {kelas.name}</b>
                                                        <small>{[kelas.label, kelas.yearName, `${kelas.students} mahasiswa`].filter(Boolean).join(' · ')}</small>
                                                    </div>
                                                    <span className="db-badge gray">{percent}% nilai</span>
                                                </div>
                                                <div className="db-progress"><i style={{ width: `${percent}%` }} /></div>
                                                <div className="db-class-actions">
                                                    <a className="db-btn ghost sm" href={kelas.attendanceUrl}>Absensi</a>
                                                    <a className="db-btn ghost sm" href={kelas.gradesUrl}>Nilai</a>
                                                    <a className="db-btn ghost sm" href={kelas.materialsUrl}>Materi</a>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            {(bkd || tridharma) && (
                            <section className="db-card">
                                <div className="db-card-head"><h3 className="db-card-title"><Award size={16} /> Beban & tridharma</h3></div>
                                <div className="db-card-body tight">
                                        {bkd && (
                                            <div className="db-summary">
                                                <span className="db-summary-icon gold"><FileText size={17} /></span>
                                                <div>
                                                    <b>BKD {bkd.periodName ? `· ${bkd.periodName}` : ''}</b>
                                                    <small>{bkd.statusLabel}</small>
                                                </div>
                                                <a className="db-btn ghost sm" href={bkd.url}>Buka</a>
                                            </div>
                                        )}
                                        {tridharma && (
                                            <div className="db-summary">
                                                <span className="db-summary-icon green"><FlaskConical size={17} /></span>
                                                <div>
                                                    <b>Tridharma</b>
                                                    <small>
                                                        {tridharma.toSubmit > 0 ? `${tridharma.toSubmit} perlu diajukan` : 'Tidak ada draft'}
                                                        {tridharma.inProgress > 0 ? ` · ${tridharma.inProgress} berjalan` : ''}
                                                    </small>
                                                </div>
                                                <a className="db-btn ghost sm" href={tridharma.url}>Buka</a>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h3 className="db-card-title"><Megaphone size={16} /> Pengumuman</h3>
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                        {announcements.unread > 0 && <span className="db-badge red">{announcements.unread} baru</span>}
                                        <a className="db-btn ghost sm" href={announcements.allUrl}>Semua</a>
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
                                <span>Butuh bantuan? Buka materi, tugas, dan konsultasi dari menu Mengajar, Pembelajaran, dan Bimbingan di sidebar.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
