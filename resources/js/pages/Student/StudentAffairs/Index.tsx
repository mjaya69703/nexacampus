// Portal mahasiswa — organisasi yang diikuti dan prestasi pribadi.
import { Head } from '@inertiajs/react';
import {
    Award, Building2, CalendarDays, CheckCircle2, Clock3, FileText, GraduationCap,
    Info, Star, Trophy, UserCheck, Users,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StudentAffairsStats, initialsFor, toneClass } from '../../Admin/StudentAffairs/shared';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Membership = {
    id: number;
    organizationId: number;
    organizationName: string;
    organizationType: string | null;
    organizationTypeShort: string | null;
    organizationTypeTone: string;
    faculty: string | null;
    studyProgram: string | null;
    advisor: string | null;
    position: string;
    positionLabel: string;
    positionTone: string;
    period: string;
    joinedAt: string | null;
    leftAt: string | null;
    isActive: boolean;
    notes: string | null;
};

type Achievement = {
    id: number;
    title: string;
    organization: string | null;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    level: string;
    levelLabel: string;
    levelTone: string;
    rankLabel: string;
    rankTone: string;
    achievedAt: string | null;
    organizer: string | null;
    description: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    rejectionReason: string | null;
    isFeatured: boolean;
    certificateUrl: string | null;
    certificateName: string | null;
};

type Organization = {
    id: number;
    name: string;
    typeLabel: string;
    typeShort: string;
    typeTone: string;
    description: string | null;
    activeMembersCount: number;
    logoUrl: string | null;
};

type Props = {
    shell: ShellProps;
    student: { name: string; nim: string | null; program: string | null; semester: number | null };
    stats: {
        activeMemberships: number;
        memberships: number;
        achievements: number;
        verified: number;
        organizations: number;
        points: number;
        registeredEvents: number;
        activeBookings: number;
    };
    memberships: Membership[];
    achievements: Achievement[];
    organizations: Organization[];
    options: {
        categories: Record<string, string>;
        levels: Record<string, string>;
        ranks: Record<string, string>;
        statuses: Record<string, string>;
        positions: Record<string, string>;
        organizationTypes: Record<string, string>;
    };
    urls: { publicOrganizations: string; publicAchievements: string; events: string; leaderboard: string; bookings: string };
};

export default function StudentAffairsIndex({ shell, student, stats, memberships, achievements, organizations, urls }: Props) {
    const activeMemberships = memberships.filter((membership) => membership.isActive);

    return (
        <AdminShell shell={shell}>
            <Head title={`Organisasi & Prestasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Kemahasiswaan"
                        title="Organisasi & Prestasi Saya"
                        description={`Rekam jejak keorganisasian dan capaian ${student.name}${student.nim ? ` (${student.nim})` : ''}.`}
                        badges={[
                            student.program || 'Program studi belum tersedia',
                            student.semester ? `Semester ${student.semester}` : 'Semester aktif',
                        ]}
                        actions={(
                            <>
                                <a className="db-btn ghost" href={urls.publicOrganizations}><Users size={15} /> Organisasi Kampus</a>
                                <a className="db-btn light" href={urls.publicAchievements}><Trophy size={15} /> Galeri Prestasi</a>
                            </>
                        )}
                    />

                    <StudentAffairsStats
                        label="Ringkasan kemahasiswaan"
                        items={[
                            { label: 'Organisasi aktif', value: stats.activeMemberships, icon: UserCheck, tone: 'green' },
                            { label: 'Riwayat keanggotaan', value: stats.memberships, icon: Users },
                            { label: 'Prestasi tercatat', value: stats.achievements, icon: Trophy, tone: 'gold' },
                            { label: 'Prestasi terverifikasi', value: stats.verified, icon: CheckCircle2, tone: 'green' },
                            { label: 'Poin saya', value: stats.points, icon: Star, tone: 'gold' },
                            { label: 'Event terdaftar', value: stats.registeredEvents, icon: CalendarDays },
                        ]}
                    />

                    <div className="db-grid-3">
                        <a className="db-fact wide" href={urls.events} style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <span className="db-stat-icon blue"><CalendarDays size={18} /></span>
                            <span>
                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>Event Mahasiswa</b>
                                <small className="db-hint">Ikuti agenda dan kumpulkan poin</small>
                            </span>
                        </a>
                        <a className="db-fact wide" href={urls.leaderboard} style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <span className="db-stat-icon gold"><Star size={18} /></span>
                            <span>
                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>Poin & Leaderboard</b>
                                <small className="db-hint">Lihat peringkat dan riwayat poin</small>
                            </span>
                        </a>
                        <a className="db-fact wide" href={urls.bookings} style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 12 }}>
                            <span className="db-stat-icon green"><Building2 size={18} /></span>
                            <span>
                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>Booking Fasilitas</b>
                                <small className="db-hint">{stats.activeBookings} booking aktif</small>
                            </span>
                        </a>
                    </div>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={15} /> Organisasi Saya</h2>
                                    <span className="db-badge gray">{activeMemberships.length} aktif</span>
                                </div>
                                <div className="db-card-body">
                                    {memberships.length === 0 ? (
                                        <EmptyState
                                            icon={Users}
                                            iconSize={24}
                                            title="Belum bergabung dengan organisasi"
                                            description="Keanggotaan organisasi dicatat oleh admin kemahasiswaan. Hubungi pengurus ormawa untuk didaftarkan."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 12 }}>
                                            {memberships.map((membership) => (
                                                <article key={membership.id} className="db-fact wide" style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                            <span className={`db-stat-icon ${membership.organizationTypeTone === 'green' ? 'green' : membership.organizationTypeTone === 'red' ? 'red' : membership.organizationTypeTone === 'amber' ? 'gold' : ''}`.trim()} style={{ width: 38, height: 38, borderRadius: 10 }}>
                                                                <Users size={17} />
                                                            </span>
                                                            <span>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13.5 }}>{membership.organizationName}</b>
                                                                <small className="db-hint">
                                                                    {membership.organizationType || 'Organisasi'} · {membership.period}
                                                                </small>
                                                            </span>
                                                        </div>
                                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                            <span className={`db-badge ${toneClass(membership.positionTone)}`}>{membership.positionLabel}</span>
                                                            <span className={`db-badge ${membership.isActive ? 'green' : 'gray'}`}>
                                                                {membership.isActive ? 'Aktif' : 'Selesai'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14 }}>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                            <GraduationCap size={12} /> {membership.studyProgram || membership.faculty || 'Lintas prodi'}
                                                        </span>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                            <CalendarDays size={12} />
                                                            {membership.joinedAt ? `Bergabung ${membership.joinedAt}` : 'Tanggal bergabung belum dicatat'}
                                                            {membership.leftAt ? ` · Selesai ${membership.leftAt}` : ''}
                                                        </span>
                                                        {membership.advisor && (
                                                            <span className="db-hint">Pembina: {membership.advisor}</span>
                                                        )}
                                                    </div>
                                                    {membership.notes && <span className="db-hint">{membership.notes}</span>}
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={15} /> Rekomendasi Organisasi</h2>
                                    <span className="db-badge gray">{stats.organizations} ormawa aktif</span>
                                </div>
                                <div className="db-card-body">
                                    {organizations.length === 0 ? (
                                        <span className="db-hint">Belum ada organisasi aktif yang terdaftar.</span>
                                    ) : (
                                        <div className="db-grid-2">
                                            {organizations.map((organization) => (
                                                <article key={organization.id} className="db-fact" style={{ display: 'grid', gap: 7 }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
                                                        {organization.logoUrl ? (
                                                            <img
                                                                src={organization.logoUrl}
                                                                alt=""
                                                                style={{ width: 34, height: 34, objectFit: 'cover', borderRadius: 9, border: '1px solid var(--db-line)' }}
                                                            />
                                                        ) : (
                                                            <span className="db-stat-icon" style={{ width: 34, height: 34, borderRadius: 9, fontSize: 10, fontWeight: 800 }}>
                                                                {initialsFor(organization.name)}
                                                            </span>
                                                        )}
                                                        <div style={{ minWidth: 0 }}>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5, lineHeight: 1.35 }}>{organization.name}</b>
                                                            <small className="db-hint">{organization.typeLabel}</small>
                                                        </div>
                                                    </div>
                                                    <p className="db-hint" style={{ margin: 0 }}>
                                                        {organization.description || 'Belum ada deskripsi organisasi.'}
                                                    </p>
                                                    <span className={`db-badge ${toneClass(organization.typeTone)}`} style={{ justifySelf: 'start' }}>
                                                        {organization.activeMembersCount} anggota aktif
                                                    </span>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Trophy size={15} /> Prestasi Saya</h2>
                                    <span className="db-badge gold">{stats.verified} terverifikasi</span>
                                </div>
                                <div className="db-card-body">
                                    {achievements.length === 0 ? (
                                        <EmptyState
                                            icon={Award}
                                            iconSize={24}
                                            title="Belum ada prestasi tercatat"
                                            description="Prestasi yang telah diverifikasi admin akan muncul di sini dan di galeri publik kampus."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 12 }}>
                                            {achievements.map((achievement) => (
                                                <article key={achievement.id} className="db-fact wide" style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{achievement.title}</b>
                                                        <span className={`db-badge ${toneClass(achievement.statusTone)}`}>{achievement.statusLabel}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                        <span className={`db-badge ${toneClass(achievement.categoryTone)}`}>{achievement.categoryLabel}</span>
                                                        <span className={`db-badge ${toneClass(achievement.levelTone)}`}>{achievement.levelLabel}</span>
                                                        <span className={`db-badge ${toneClass(achievement.rankTone)}`}>{achievement.rankLabel}</span>
                                                        {achievement.isFeatured && <span className="db-badge gold"><Star size={11} /> Sorotan</span>}
                                                    </div>
                                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                        <CalendarDays size={12} /> {achievement.achievedAt || 'Tanggal belum dicatat'}
                                                        {achievement.organizer ? ` · ${achievement.organizer}` : ''}
                                                        {achievement.organization ? ` · ${achievement.organization}` : ''}
                                                    </span>
                                                    {achievement.description && <span className="db-hint">{achievement.description}</span>}
                                                    {achievement.status === 'rejected' && achievement.rejectionReason && (
                                                        <span className="db-hint" style={{ color: 'var(--db-red)' }}>
                                                            Alasan penolakan: {achievement.rejectionReason}
                                                        </span>
                                                    )}
                                                    {achievement.certificateUrl && (
                                                        <a className="db-btn ghost sm" href={achievement.certificateUrl} style={{ justifySelf: 'start' }}>
                                                            <FileText size={13} /> Unduh Sertifikat
                                                        </a>
                                                    )}
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <div className="adm-note gold" style={{ margin: 0 }}>
                                <Info size={16} />
                                <div>
                                    <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Alur verifikasi prestasi.</b>
                                    Prestasi yang Anda raih dicatat dan diverifikasi oleh admin kemahasiswaan. Hanya prestasi terverifikasi yang tampil di galeri publik.
                                </div>
                            </div>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><Clock3 size={15} /> Catatan</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                                    <span className="db-hint">Data keanggotaan dikelola per periode kepengurusan oleh admin.</span>
                                    <span className="db-hint">Hubungi admin kemahasiswaan bila ada data organisasi atau prestasi yang perlu diperbarui.</span>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
