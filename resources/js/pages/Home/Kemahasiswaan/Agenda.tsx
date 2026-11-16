// Public student events agenda (agenda kegiatan kemahasiswaan) page.
import { Head } from '@inertiajs/react';
import {
    ArrowRight, CalendarDays, CalendarCheck, Clock, HeartPulse, MapPin, Sparkles, Star, Ticket, Trophy, Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

export type PublicEvent = {
    id: number;
    title: string;
    slug: string;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    description: string;
    organization: string | null;
    organizer: string | null;
    location: string | null;
    startsAt: string | null;
    startDay: string | null;
    startMonth: string | null;
    startYear: string | null;
    startTime: string | null;
    endsAt: string | null;
    endTime: string | null;
    registrationDeadline: string | null;
    quota: number | null;
    registeredCount: number;
    seatsLeft: number | null;
    points: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    isFeatured: boolean;
    coverUrl: string | null;
    registrationOpen: boolean;
    detailUrl: string;
};

type AgendaProps = CommunityBaseProps & {
    events: PublicEvent[];
    stats: { events: number; upcoming: number; participants: number; organizations: number };
    categories: Record<string, string>;
};

const badgeFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
        case 'amber':
        case 'gray':
            return tone;
        default:
            return '';
    }
};

const iconToneFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
            return tone;
        case 'amber':
            return 'gold';
        default:
            return '';
    }
};

export default function Agenda({ campus, links, user, events, stats, categories }: AgendaProps) {
    const [activeCategory, setActiveCategory] = useState('all');

    const categoryEntries = Object.entries(categories);

    const filtered = useMemo(
        () => (activeCategory === 'all' ? events : events.filter((event) => event.category === activeCategory)),
        [activeCategory, events],
    );

    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Agenda" eyebrow="Agenda Kegiatan" title="Agenda kegiatan mahasiswa." description="Ikuti seminar, workshop, kompetisi, dan kegiatan sosial yang diselenggarakan organisasi mahasiswa sepanjang tahun." icon={CalendarDays}>
        <Head title={`Agenda Kegiatan · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><CalendarDays size={14} /> {stats.events} Kegiatan Terjadwal</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>
                            Dari ruang seminar hingga lapangan olahraga — temukan kegiatan yang cocok, daftar lebih awal, dan kumpulkan poin kemahasiswaan.
                        </p>
                    </div>
                    <div className="adm-hero-cta">
                        <a className="adm-btn outline" href="/kemahasiswaan/organisasi"><Users size={15} /> Organisasi Mahasiswa</a>
                        <a className="adm-btn light" href="/kemahasiswaan/prestasi"><Trophy size={15} /> Galeri Prestasi</a>
                    </div>
                </div>
            </div>
        </div>

        <StatStrip
            items={[
                { icon: CalendarDays, value: stats.events, label: 'Kegiatan Terjadwal' },
                { icon: CalendarCheck, value: stats.upcoming, label: 'Akan Datang', tone: 'green' },
                { icon: Ticket, value: stats.participants, label: 'Peserta Terdaftar', tone: 'gold' },
                { icon: Users, value: stats.organizations, label: 'Penyelenggara' },
            ]}
        />

        {events.length === 0 ? (
            <EmptyState
                icon={CalendarDays}
                iconSize={28}
                title="Belum Ada Agenda Kegiatan"
                description="Agenda kegiatan mahasiswa akan tampil di sini setelah admin mempublikasikan event organisasi."
            />
        ) : (
            <>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    <button
                        type="button"
                        className={`adm-btn ${activeCategory === 'all' ? '' : 'ghost'} sm`}
                        onClick={() => setActiveCategory('all')}
                    >
                        Semua <span className="adm-badge gray">{events.length}</span>
                    </button>
                    {categoryEntries.map(([value, label]) => {
                        const count = events.filter((event) => event.category === value).length;

                        if (count === 0) return null;

                        return (
                            <button
                                key={value}
                                type="button"
                                className={`adm-btn ${activeCategory === value ? '' : 'ghost'} sm`}
                                onClick={() => setActiveCategory(value)}
                            >
                                {label} <span className="adm-badge gray">{count}</span>
                            </button>
                        );
                    })}
                </div>

                {filtered.length === 0 ? (
                    <EmptyState
                        icon={CalendarDays}
                        iconSize={24}
                        title="Belum Ada Kegiatan pada Kategori Ini"
                        description="Pilih kategori lain untuk melihat agenda kegiatan yang tersedia."
                    />
                ) : (
                    <div className="adm-grid adm-cols-3">
                        {filtered.map((event) => (
                            <article className="adm-card" key={event.id} style={{ display: 'flex', flexDirection: 'column' }}>
                                {event.coverUrl ? (
                                    <img
                                        src={event.coverUrl}
                                        alt={`Sampul ${event.title}`}
                                        style={{ width: '100%', height: 148, objectFit: 'cover', borderBottom: '1px solid var(--adm-line)' }}
                                    />
                                ) : (
                                    <div style={{ display: 'grid', placeItems: 'center', height: 148, color: 'var(--adm-brand)', background: 'var(--adm-brand-soft)', borderBottom: '1px solid var(--adm-line)' }}>
                                        <CalendarDays size={34} />
                                    </div>
                                )}

                                <div className="adm-card-body" style={{ display: 'grid', gap: 11, alignContent: 'start', flex: 1 }}>
                                    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
                                        <span className={`adm-stat-icon ${iconToneFor(event.categoryTone)}`} style={{ width: 52, height: 52, borderRadius: 14, textAlign: 'center' }}>
                                            <span style={{ display: 'grid', lineHeight: 1 }}>
                                                <b style={{ font: "700 18px/1 'Source Serif 4', Georgia, serif" }}>{event.startDay ?? '—'}</b>
                                                <small style={{ fontSize: 9.5, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.06em' }}>{event.startMonth ?? ''}</small>
                                            </span>
                                        </span>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'flex-end', gap: 5 }}>
                                            {event.isFeatured && <span className="adm-badge amber"><Star size={10} /> Sorotan</span>}
                                            <span className={`adm-badge ${badgeFor(event.categoryTone)}`.trim()}>{event.categoryLabel}</span>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 15px/1.35 'Source Serif 4', Georgia, serif" }}>
                                            {event.title}
                                        </h3>
                                        <small className="adm-hint">
                                            {event.organization || event.organizer || 'Kemahasiswaan'}
                                        </small>
                                    </div>

                                    <p className="adm-hint" style={{ margin: 0 }}>
                                        {event.description || 'Belum ada deskripsi kegiatan.'}
                                    </p>

                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                        <span className="adm-badge gray">
                                            <Clock size={11} /> {event.startTime ?? '—'}{event.endTime ? `–${event.endTime}` : ''}
                                        </span>
                                        {event.location && <span className="adm-badge gray"><MapPin size={11} /> {event.location}</span>}
                                        {event.points > 0 && <span className="adm-badge gray"><Sparkles size={11} /> {event.points} poin</span>}
                                    </div>

                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginTop: 'auto' }}>
                                        <span className={`adm-badge ${event.registrationOpen ? 'green' : 'gray'}`}>
                                            {event.registrationOpen ? 'Pendaftaran Dibuka' : event.statusLabel}
                                        </span>
                                        <a className="adm-btn ghost sm" href={event.detailUrl}>
                                            Detail <ArrowRight size={13} />
                                        </a>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </>
        )}

        <div className="adm-note green">
            <HeartPulse size={16} />
            <div>
                <b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Terbuka untuk seluruh mahasiswa.</b>
                Sebagian kegiatan membuka pendaftaran umum, sementara kegiatan ormawa tertentu dapat mensyaratkan keanggotaan aktif. Kehadiran kegiatan terverifikasi akan dicatat sebagai poin kemahasiswaan.
            </div>
        </div>
    </KemahasiswaanShell>;
}
