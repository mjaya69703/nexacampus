// Dashboard alumni — portal karir & jejaring (Inertia React).
import { Head } from '@inertiajs/react';
import {
    Award, Briefcase, Building2, CalendarDays, CircleCheck, ClipboardCheck,
    GraduationCap, Info, MapPin, Send, TrendingUp, UserRound, UsersRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import '../../../css/dashboard.css';

type Alumni = {
    name: string; nim: string | null; programName: string | null; facultyName: string | null;
    gradYear: number | null; gpa: string | null; completeness: number;
};
type Career = {
    statusLabel: string; employer: string | null; title: string | null;
    industry: string | null; domicile: string | null;
};
type Job = {
    id: number; title: string; company: string; location: string | null;
    typeLabel: string | null; deadlineLabel: string; url: string;
};
type AlumniEvent = {
    id: number; title: string; typeLabel: string | null; dateLabel: string;
    placeLabel: string; url: string;
};
type Tracer = { id: number; title: string; periodLabel: string; url: string };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    alumni: Alumni;
    career: Career | null;
    stats: { eventsJoined: number; eventsAttended: number; tracerFilled: number; completeness: number };
    jobs: { total: number; items: Job[] };
    events: { total: number; items: AlumniEvent[] };
    tracerActive: Tracer[];
    tracerDone: Tracer[];
    urls: { profile: string; profileEdit: string; jobs: string; events: string; tracer: string };
};

export default function AlumniDashboard({ shell, hasProfile, alumni, career, stats, jobs, events, tracerActive, tracerDone, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <section className="db-hero">
                        <div className="db-hero-inner">
                            <div className="db-hero-copy">
                                <span className="db-hero-icon"><GraduationCap size={26} /></span>
                                <div>
                                    <small>Portal alumni</small>
                                    <h1>{alumni.name}</h1>
                                    <p>
                                        {[alumni.programName, alumni.facultyName].filter(Boolean).join(' · ') || 'Alumni NexaCampus'}
                                        {alumni.gradYear ? ` · Lulus ${alumni.gradYear}` : ''}
                                        {alumni.gpa ? ` · IPK ${alumni.gpa}` : ''}
                                    </p>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                        {alumni.nim && <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{alumni.nim}</span>}
                                        {career && <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{career.statusLabel}</span>}
                                        <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{stats.completeness}% lengkap</span>
                                    </div>
                                </div>
                            </div>
                            <div className="db-hero-actions">
                                <a className="db-btn light" href={urls.profileEdit}><UserRound size={15} /> Edit profil</a>
                                <a className="db-btn light" href={urls.jobs}><Briefcase size={15} /> Job board</a>
                            </div>
                        </div>
                    </section>

                    {!hasProfile && (
                        <div className="db-note info"><Info size={15} /><span>Profil alumni Anda belum terhubung. Hubungi bagian alumni agar data tampil di sini.</span></div>
                    )}

                    {tracerActive.length > 0 && (
                        <section className="db-card" style={{ borderColor: 'var(--db-gold-strong)' }}>
                            <div className="db-card-head">
                                <h2 className="db-card-title"><ClipboardCheck size={16} /> Tracer study menunggu diisi</h2>
                                <span className="db-badge amber">{tracerActive.length}</span>
                            </div>
                            <div className="db-card-body tight">
                                {tracerActive.map((campaign) => (
                                    <div className="db-summary" key={campaign.id}>
                                        <span className="db-summary-icon gold"><Send size={17} /></span>
                                        <div>
                                            <b>{campaign.title}</b>
                                            <small>{campaign.periodLabel}</small>
                                        </div>
                                        <a className="db-btn primary sm" href={campaign.url}>Isi survei</a>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="db-stats" aria-label="Statistik alumni">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CircleCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.completeness}%</span><span className="db-stat-label">Kelengkapan</span></div>
                        </div>
                        <a className="db-stat" href={urls.jobs}>
                            <span className="db-stat-icon"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{jobs.total}</span><span className="db-stat-label">Lowongan</span></div>
                        </a>
                        <a className="db-stat" href={urls.events}>
                            <span className="db-stat-icon gold"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{stats.eventsJoined}</span><span className="db-stat-label">Event diikuti</span></div>
                        </a>
                        <a className="db-stat" href={urls.tracer}>
                            <span className="db-stat-icon"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.tracerFilled}</span><span className="db-stat-label">Tracer diisi</span></div>
                        </a>
                    </section>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Briefcase size={16} /> Ringkasan karir</h2>
                                    <a className="db-btn ghost sm" href={urls.profile}>Profil saya</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {!career ? (
                                        <p className="db-hint" style={{ margin: 0 }}>Data karir belum diisi.</p>
                                    ) : (
                                        <>
                                            <div className="db-summary" style={{ border: 0, padding: 0 }}>
                                                <span className="db-summary-icon"><Building2 size={17} /></span>
                                                <div>
                                                    <b>{career.statusLabel}{career.employer ? ` · ${career.employer}` : ''}</b>
                                                    <small>{[career.title, career.industry].filter(Boolean).join(' · ') || '-'}</small>
                                                </div>
                                            </div>
                                            <div className="db-summary" style={{ border: 0, padding: 0 }}>
                                                <span className="db-summary-icon gold"><MapPin size={17} /></span>
                                                <div>
                                                    <b>Domisili</b>
                                                    <small>{career.domicile ?? '-'}</small>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><TrendingUp size={16} /> Kelengkapan profil</h2>
                                    <span className="db-badge gray">{stats.completeness}%</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                    <div className="db-progress"><i style={{ width: `${stats.completeness}%` }} /></div>
                                    <p className="db-hint" style={{ margin: 0 }}>
                                        {stats.completeness >= 100
                                            ? 'Profil sudah lengkap. Data yang lengkap membuka lebih banyak peluang.'
                                            : 'Lengkapi telepon, domisili, status kerja, dan LinkedIn untuk mencapai 100%.'}
                                    </p>
                                    {stats.completeness < 100 && (
                                        <a className="db-btn primary sm" style={{ justifySelf: 'start' }} href={urls.profileEdit}>Lengkapi profil</a>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={16} /> Event mendatang</h2>
                                    <a className="db-btn ghost sm" href={urls.events}>Semua event</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {events.items.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada event yang dipublikasikan.</p>
                                    )}
                                    {events.items.map((event) => (
                                        <a key={event.id} href={event.url} className="db-class" style={{ textDecoration: 'none' }}>
                                            <div className="db-class-top">
                                                <div>
                                                    <b>{event.title}</b>
                                                    <small>{event.dateLabel} · {event.placeLabel}</small>
                                                </div>
                                                <span className="db-badge">{event.typeLabel ?? 'Event'}</span>
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            </section>

                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h3 className="db-card-title"><UsersRound size={16} /> Tracer study</h3>
                                    <span className="db-badge gray">{stats.tracerFilled}</span>
                                </div>
                                <div className="db-card-body tight">
                                    {tracerDone.length === 0 && tracerActive.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada survei tracer untuk Anda.</p>
                                    )}
                                    {tracerDone.map((campaign) => (
                                        <div className="db-summary" key={campaign.id}>
                                            <span className="db-summary-icon green"><CircleCheck size={17} /></span>
                                            <div>
                                                <b>{campaign.title}</b>
                                                <small>Sudah diisi · {campaign.periodLabel}</small>
                                            </div>
                                            <a className="db-btn ghost sm" href={campaign.url}>Lihat</a>
                                        </div>
                                    ))}
                                    <a className="db-btn ghost sm" style={{ width: '100%', marginTop: tracerDone.length > 0 ? 12 : 0 }} href={urls.tracer}>
                                        Semua survei
                                    </a>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Briefcase size={16} /> Lowongan terbaru</h2>
                                    <a className="db-btn ghost sm" href={urls.jobs}>Job board</a>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    {jobs.items.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada lowongan aktif.</p>
                                    )}
                                    {jobs.items.map((job) => (
                                        <a key={job.id} href={job.url} className="db-class" style={{ textDecoration: 'none' }}>
                                            <div className="db-class-top">
                                                <div>
                                                    <b>{job.title}</b>
                                                    <small>{job.company}{job.location ? ` · ${job.location}` : ''} · s.d. {job.deadlineLabel}</small>
                                                </div>
                                                <span className="db-badge green">{job.typeLabel ?? 'Full-time'}</span>
                                            </div>
                                        </a>
                                    ))}
                                </div>
                            </section>

                            <div className="db-note info">
                                <Info size={15} />
                                <span>Jaga data karir tetap mutakhir — survei tracer dan undangan event mengikuti profil Anda.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
