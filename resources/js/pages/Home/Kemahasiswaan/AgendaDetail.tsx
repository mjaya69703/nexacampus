// Public student event detail (agenda kegiatan kemahasiswaan) page.
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRight, CalendarCheck, CalendarDays, CheckCircle2, Clock, MapPin, Sparkles, Star, Ticket, Users, XCircle,
} from 'lucide-react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import type { PublicEvent } from './Agenda';

type AgendaDetailProps = CommunityBaseProps & {
    event: PublicEvent;
    related: PublicEvent[];
    ctaUrl: string;
    ctaLabel: string;
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

export default function AgendaDetail({ campus, links, user, event, related, ctaUrl, ctaLabel }: AgendaDetailProps) {
    const organizer = event.organization || event.organizer || 'Kemahasiswaan';

    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Agenda" eyebrow="Agenda Kegiatan" title={event.title} description={`${event.categoryLabel} · ${organizer}`} icon={CalendarDays}>
        <Head title={`${event.title} · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><CalendarDays size={14} /> {event.statusLabel} · {event.categoryLabel}</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>
                            {event.startsAt ?? 'Jadwal menyusul'}{event.location ? ` · ${event.location}` : ''}
                        </p>
                    </div>
                    <div className="adm-hero-cta">
                        <Link className="adm-btn outline" href="/kemahasiswaan/agenda"><ArrowLeft size={15} /> Semua Agenda</Link>
                        <a className="adm-btn light" href={ctaUrl}>{event.registrationOpen ? <Ticket size={15} /> : <ArrowRight size={15} />} {ctaLabel}</a>
                    </div>
                </div>
            </div>
        </div>

        <div className="adm-layout">
            <div style={{ display: 'grid', gap: 18 }}>
                <article className="adm-card">
                    {event.coverUrl && (
                        <img
                            src={event.coverUrl}
                            alt={`Sampul ${event.title}`}
                            style={{ width: '100%', maxHeight: 320, objectFit: 'cover', borderBottom: '1px solid var(--adm-line)' }}
                        />
                    )}
                    <div className="adm-card-body" style={{ display: 'grid', gap: 14 }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                            {event.isFeatured && <span className="adm-badge amber"><Star size={10} /> Sorotan</span>}
                            <span className={`adm-badge ${badgeFor(event.categoryTone)}`.trim()}>{event.categoryLabel}</span>
                            <span className={`adm-badge ${badgeFor(event.statusTone)}`.trim()}>{event.statusLabel}</span>
                            <span className={`adm-badge ${event.registrationOpen ? 'green' : 'gray'}`}>
                                {event.registrationOpen ? <><CheckCircle2 size={11} /> Pendaftaran Dibuka</> : <><XCircle size={11} /> Pendaftaran Ditutup</>}
                            </span>
                        </div>

                        <div>
                            <h2 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 22px/1.3 'Source Serif 4', Georgia, serif" }}>
                                Tentang Kegiatan
                            </h2>
                            <p className="adm-hint" style={{ margin: '8px 0 0', whiteSpace: 'pre-line' }}>
                                {event.description || 'Belum ada deskripsi kegiatan.'}
                            </p>
                        </div>

                        <hr className="adm-divider" />

                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                            <span className="adm-badge gray"><Clock size={11} /> {event.startTime ?? '—'}{event.endTime ? `–${event.endTime}` : ''}</span>
                            {event.location && <span className="adm-badge gray"><MapPin size={11} /> {event.location}</span>}
                            {event.points > 0 && <span className="adm-badge gray"><Sparkles size={11} /> {event.points} poin</span>}
                            {event.quota !== null && <span className="adm-badge gray"><Users size={11} /> {event.registeredCount}/{event.quota} peserta</span>}
                        </div>
                    </div>
                </article>

                {related.length > 0 && (
                    <section style={{ display: 'grid', gap: 12 }}>
                        <div className="adm-section-heading">
                            <div>
                                <h2>Kegiatan Serupa</h2>
                                <p>Agenda lain dengan kategori {event.categoryLabel.toLowerCase()} yang akan datang.</p>
                            </div>
                            <span className="adm-section-count">{related.length} kegiatan</span>
                        </div>

                        <div className="adm-grid adm-cols-3">
                            {related.map((item) => (
                                <article className="adm-card" key={item.id}>
                                    <div className="adm-card-body" style={{ display: 'grid', gap: 10, alignContent: 'start' }}>
                                        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 }}>
                                            <span className={`adm-stat-icon ${iconToneFor(item.categoryTone)}`} style={{ width: 46, height: 46, borderRadius: 13, textAlign: 'center' }}>
                                                <span style={{ display: 'grid', lineHeight: 1 }}>
                                                    <b style={{ font: "700 16px/1 'Source Serif 4', Georgia, serif" }}>{item.startDay ?? '—'}</b>
                                                    <small style={{ fontSize: 9, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.06em' }}>{item.startMonth ?? ''}</small>
                                                </span>
                                            </span>
                                            <span className={`adm-badge ${badgeFor(item.categoryTone)}`.trim()}>{item.categoryLabel}</span>
                                        </div>
                                        <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 14.5px/1.35 'Source Serif 4', Georgia, serif" }}>
                                            {item.title}
                                        </h3>
                                        <small className="adm-hint">{item.organization || item.organizer || 'Kemahasiswaan'}</small>
                                        <a className="adm-btn ghost sm" href={item.detailUrl} style={{ justifySelf: 'start' }}>
                                            Lihat Detail <ArrowRight size={13} />
                                        </a>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
            </div>

            <aside style={{ display: 'grid', gap: 18 }}>
                <article className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title">Informasi Kegiatan</h2>
                        <span className={`adm-badge ${badgeFor(event.statusTone)}`.trim()}>{event.statusLabel}</span>
                    </div>
                    <div className="adm-card-body">
                        <div className="adm-list">
                            <div className="adm-list-row">
                                <small>Jadwal</small>
                                <b>{event.startsAt ?? '—'}{event.endsAt ? ` – ${event.endsAt}` : ''}</b>
                            </div>
                            <div className="adm-list-row">
                                <small>Lokasi</small>
                                <b>{event.location || 'Akan diumumkan'}</b>
                            </div>
                            <div className="adm-list-row">
                                <small>Penyelenggara</small>
                                <b>{organizer}</b>
                            </div>
                            <div className="adm-list-row">
                                <small>Kategori</small>
                                <b>{event.categoryLabel}</b>
                            </div>
                            <div className="adm-list-row">
                                <small>Batas Pendaftaran</small>
                                <b>{event.registrationDeadline || 'Tanpa batas waktu'}</b>
                            </div>
                            <div className="adm-list-row">
                                <small>Kuota Peserta</small>
                                <b>
                                    {event.quota !== null
                                        ? `${event.registeredCount} dari ${event.quota} peserta${event.seatsLeft !== null ? ` · sisa ${event.seatsLeft}` : ''}`
                                        : `${event.registeredCount} peserta · tanpa kuota`}
                                </b>
                            </div>
                            <div className="adm-list-row">
                                <small>Poin Kemahasiswaan</small>
                                <b>{event.points > 0 ? `${event.points} poin untuk peserta hadir` : 'Tidak ada poin'}</b>
                            </div>
                        </div>

                        <hr className="adm-divider" />

                        <a className="adm-btn primary block" href={ctaUrl}>
                            {event.registrationOpen ? <><Ticket size={15} /> {ctaLabel}</> : <><ArrowRight size={15} /> {ctaLabel}</>}
                        </a>

                        {event.registrationOpen ? (
                            <div className="adm-note green" style={{ marginTop: 12 }}>
                                <CalendarCheck size={15} />
                                <div>Pendaftaran masih dibuka. Masuk ke portal mahasiswa untuk mendaftar dan memantau status kehadiran.</div>
                            </div>
                        ) : (
                            <div className="adm-note gold" style={{ marginTop: 12 }}>
                                <CalendarDays size={15} />
                                <div>Pendaftaran kegiatan ini sudah ditutup. Hubungi penyelenggara untuk informasi lebih lanjut.</div>
                            </div>
                        )}
                    </div>
                </article>

                <article className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title">Butuh Bantuan?</h2>
                    </div>
                    <div className="adm-card-body" style={{ display: 'grid', gap: 10 }}>
                        <p className="adm-hint" style={{ margin: 0 }}>
                            Informasi pendaftaran, kehadiran, dan poin kemahasiswaan dapat ditanyakan langsung kepada penyelenggara kegiatan.
                        </p>
                        <Link className="adm-btn ghost sm" href="/kemahasiswaan/layanan" style={{ justifySelf: 'start' }}>
                            Layanan Mahasiswa <ArrowRight size={13} />
                        </Link>
                    </div>
                </article>
            </aside>
        </div>
    </KemahasiswaanShell>;
}
