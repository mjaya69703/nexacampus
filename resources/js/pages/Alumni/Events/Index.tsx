// Daftar event alumni — filter tipe & waktu, kartu agenda (Inertia React).
import { Head, router } from '@inertiajs/react';
import {
    CalendarClock, CalendarDays, CalendarX, MapPin, RotateCcw, Ticket, UsersRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { Pagination } from '../../../components/Shared/Pagination';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number;
    title: string;
    eventType: string | null;
    eventTypeLabel: string | null;
    dateLabel: string | null;
    dayLabel: string | null;
    monthLabel: string | null;
    timeLabel: string | null;
    location: string | null;
    isOnline: boolean;
    isPast: boolean;
    maxParticipants: number | null;
    participantsCount: number;
    slotsLeft: number | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { event_type: string; period: string };
    eventTypeOptions: Record<string, string>;
    urls: { index: string };
};

export default function EventIndex({ shell, data, filters, eventTypeOptions, urls }: Props) {
    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params: Record<string, string | number> = { ...filters, ...overrides };
        router.get(
            urls.index,
            Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== undefined)),
            { preserveState: true, replace: true },
        );
    };

    const reset = () => router.get(urls.index, {}, { preserveState: true, replace: true });

    return (
        <AdminShell shell={shell}>
            <Head title={`Event Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Portal alumni"
                        title="Event Alumni"
                        description="Ikuti webinar, reuni, career fair, dan forum networking untuk memperluas jaringan."
                        badges={[`${data.total} event`]}
                    />

                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'center' }}>
                            <select
                                className="db-input"
                                style={{ width: 'auto', minWidth: 180, flex: '1 1 180px' }}
                                value={filters.event_type}
                                onChange={(event) => visit({ event_type: event.target.value, page: 1 })}
                                aria-label="Filter tipe event"
                            >
                                <option value="">Semua tipe</option>
                                {Object.entries(eventTypeOptions).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <select
                                className="db-input"
                                style={{ width: 'auto', minWidth: 180, flex: '1 1 180px' }}
                                value={filters.period}
                                onChange={(event) => visit({ period: event.target.value, page: 1 })}
                                aria-label="Filter waktu event"
                            >
                                <option value="">Semua waktu</option>
                                <option value="upcoming">Mendatang</option>
                                <option value="past">Selesai</option>
                            </select>
                            <button type="button" className="db-btn ghost" onClick={reset}>
                                <RotateCcw size={14} /> Reset
                            </button>
                        </div>
                    </section>

                    {data.rows.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', justifyItems: 'center', gap: 8, padding: '44px 24px', textAlign: 'center' }}>
                                <span className="db-stat-icon red"><CalendarX size={20} /></span>
                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 17 }}>Tidak ada event ditemukan</h2>
                                <p className="db-hint" style={{ margin: 0 }}>Coba ubah filter tipe atau waktu event.</p>
                            </div>
                        </section>
                    ) : (
                        <div className="db-stack">
                            {data.rows.map((row) => (
                                <a key={row.id} href={row.url} style={{ textDecoration: 'none', color: 'inherit' }}>
                                    <section className="db-card">
                                        <div className="db-card-body" style={{ display: 'flex', flexWrap: 'wrap', gap: 18, alignItems: 'center' }}>
                                            <div
                                                style={{
                                                    width: 78, minWidth: 78, padding: '12px 8px', borderRadius: 14, textAlign: 'center',
                                                    background: 'linear-gradient(135deg, var(--db-brand-deep), var(--db-brand))', color: '#fff',
                                                }}
                                            >
                                                <div style={{ fontSize: 24, fontWeight: 800, lineHeight: 1.1 }}>{row.dayLabel ?? '-'}</div>
                                                <div style={{ fontSize: 11, opacity: 0.85, textTransform: 'uppercase', letterSpacing: '.08em' }}>{row.monthLabel ?? ''}</div>
                                            </div>

                                            <div style={{ flex: '1 1 260px', minWidth: 200 }}>
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 6 }}>
                                                    {row.eventTypeLabel && <span className="db-badge">{row.eventTypeLabel}</span>}
                                                    <span className={`db-badge ${row.isOnline ? 'green' : 'gray'}`}>
                                                        {row.isOnline ? 'Online' : 'Offline'}
                                                    </span>
                                                    {row.isPast && <span className="db-badge gray">Selesai</span>}
                                                </div>
                                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 16, lineHeight: 1.4 }}>{row.title}</h2>
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 6 }}>
                                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                        <CalendarClock size={12} /> {row.timeLabel ?? '-'} WIB
                                                    </span>
                                                    {row.location && (
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <MapPin size={12} /> {row.location}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div style={{ display: 'grid', gap: 6, justifyItems: 'end', minWidth: 150 }}>
                                                {row.maxParticipants ? (
                                                    <>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <UsersRound size={12} /> {row.participantsCount}/{row.maxParticipants} peserta
                                                        </span>
                                                        {row.slotsLeft !== null && row.slotsLeft <= 0 ? (
                                                            <span className="db-badge red">Penuh</span>
                                                        ) : row.slotsLeft !== null && row.slotsLeft <= 10 ? (
                                                            <span className="db-badge amber">{row.slotsLeft} slot tersisa</span>
                                                        ) : (
                                                            <span className="db-badge green">{row.slotsLeft} slot tersisa</span>
                                                        )}
                                                    </>
                                                ) : (
                                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                        <Ticket size={12} /> {row.participantsCount} peserta
                                                    </span>
                                                )}
                                                {!row.isPast && <span className="db-btn ghost sm">Lihat detail</span>}
                                            </div>
                                        </div>
                                    </section>
                                </a>
                            ))}
                        </div>
                    )}

                    <Pagination
                        page={data.currentPage}
                        totalPages={data.lastPage}
                        total={data.total}
                        unit="event"
                        onPrev={() => visit({ page: Math.max(1, data.currentPage - 1) })}
                        onNext={() => visit({ page: Math.min(data.lastPage, data.currentPage + 1) })}
                    />
                </div>
            </div>
        </AdminShell>
    );
}
