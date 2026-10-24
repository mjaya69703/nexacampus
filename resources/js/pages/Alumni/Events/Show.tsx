// Detail event alumni — informasi acara + status pendaftaran (Inertia React).
import { Head } from '@inertiajs/react';
import {
    ArrowLeft, CalendarClock, CalendarDays, CircleCheck, CircleAlert,
    Clock3, MapPin, Ticket, UserRound, UsersRound, Video,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type AlumniEvent = {
    id: number;
    title: string;
    description: string | null;
    eventType: string | null;
    eventTypeLabel: string | null;
    eventDate: string | null;
    startTime: string | null;
    endTime: string | null;
    location: string | null;
    isOnline: boolean;
    meetingUrl: string | null;
    maxParticipants: number | null;
    participantsCount: number;
    slotsLeft: number | null;
    registrationDeadline: string | null;
    isPast: boolean;
};

type Props = {
    shell: ShellProps;
    event: AlumniEvent;
    hasProfile: boolean;
    isRegistered: boolean;
    canRegister: boolean;
    registrationBlocker: string | null;
    urls: { index: string; register: string; profile: string };
};

function DetailRow({ label, value }: { label: string; value: string | null }) {
    return (
        <tr>
            <td className="db-hint" style={{ width: '42%', padding: '9px 0', borderBottom: '1px solid var(--db-line)' }}>{label}</td>
            <td style={{ padding: '9px 0', borderBottom: '1px solid var(--db-line)', color: 'var(--db-heading)', fontWeight: 600 }}>{value || '-'}</td>
        </tr>
    );
}

export default function EventShow({ shell, event, hasProfile, isRegistered, canRegister, registrationBlocker, urls }: Props) {
    const timeLabel = event.startTime
        ? `${event.startTime}${event.endTime ? ` - ${event.endTime}` : ''} WIB`
        : null;

    return (
        <AdminShell shell={shell}>
            <Head title={`${event.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow={event.eventTypeLabel ?? 'Event alumni'}
                        title={event.title}
                        description={[event.eventDate, event.location ?? (event.isOnline ? 'Online' : null)].filter(Boolean).join(' · ')}
                        badges={[event.isOnline ? 'Online' : 'Offline', event.isPast ? 'Selesai' : 'Akan datang']}
                        actions={(
                            <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar event</a>
                        )}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Ticket size={16} /> Tentang Event</h2>
                                </div>
                                <div className="db-card-body">
                                    {event.description ? (
                                        <div style={{ whiteSpace: 'pre-wrap', lineHeight: 1.8, color: 'var(--db-ink)' }}>
                                            {event.description}
                                        </div>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada deskripsi untuk event ini.</p>
                                    )}
                                </div>
                            </section>

                            {event.maxParticipants && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><UsersRound size={16} /> Okupansi Peserta</h2>
                                        <span className={`db-badge ${event.slotsLeft !== null && event.slotsLeft <= 0 ? 'red' : 'green'}`}>
                                            {event.participantsCount}/{event.maxParticipants}
                                        </span>
                                    </div>
                                    <div className="db-card-body">
                                        <div className="db-progress">
                                            <i style={{ width: `${Math.min(100, Math.round((event.participantsCount / event.maxParticipants) * 100))}%` }} />
                                        </div>
                                        <p className="db-hint" style={{ margin: '8px 0 0' }}>
                                            {event.slotsLeft !== null && event.slotsLeft <= 0
                                                ? 'Kuota event sudah penuh.'
                                                : `Tersisa ${event.slotsLeft ?? 0} slot pendaftaran.`}
                                        </p>
                                    </div>
                                </section>
                            )}
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarClock size={16} /> Detail Event</h2>
                                </div>
                                <div className="db-card-body">
                                    <table className="db-table" style={{ width: '100%' }}>
                                        <tbody>
                                            <DetailRow label="Tanggal" value={event.eventDate} />
                                            <DetailRow label="Waktu" value={timeLabel} />
                                            <DetailRow label="Lokasi" value={event.location ?? (event.isOnline ? 'Online' : null)} />
                                            <DetailRow label="Batas daftar" value={event.registrationDeadline} />
                                        </tbody>
                                    </table>

                                    {event.isOnline && event.meetingUrl && (
                                        <a className="db-btn ghost sm" href={event.meetingUrl} target="_blank" rel="noreferrer" style={{ marginTop: 12 }}>
                                            <Video size={13} /> Buka tautan meeting
                                        </a>
                                    )}

                                    <div style={{ marginTop: 14 }}>
                                        {event.isPast ? (
                                            <div className="db-note muted">
                                                <CircleAlert size={15} />
                                                <span>Event ini sudah selesai diselenggarakan.</span>
                                            </div>
                                        ) : isRegistered ? (
                                            <div className="db-note success">
                                                <CircleCheck size={15} />
                                                <span>Kamu sudah terdaftar di event ini.</span>
                                            </div>
                                        ) : !hasProfile ? (
                                            <>
                                                <div className="db-note warning">
                                                    <UserRound size={15} />
                                                    <span>Lengkapi profil alumni terlebih dahulu untuk mendaftar event.</span>
                                                </div>
                                                <a className="db-btn ghost" href={urls.profile} style={{ width: '100%', marginTop: 10 }}>
                                                    Lengkapi profil
                                                </a>
                                            </>
                                        ) : canRegister ? (
                                            <a className="db-btn primary" href={urls.register} style={{ width: '100%' }}>
                                                <Ticket size={15} /> Daftar Event
                                            </a>
                                        ) : (
                                            <div className="db-note warning">
                                                <Clock3 size={15} />
                                                <span>{registrationBlocker ?? 'Pendaftaran event belum dibuka.'}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body" style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                    <MapPin size={16} style={{ color: 'var(--db-brand)', flex: '0 0 auto', marginTop: 2 }} />
                                    <p className="db-hint" style={{ margin: 0 }}>
                                        Pastikan data kontak profil kamu aktif agar panitia dapat menghubungi kamu terkait event.
                                    </p>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
