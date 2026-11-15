// Portal mahasiswa — daftar event kemahasiswaan dan pendaftaran.
import { Head, router } from '@inertiajs/react';
import {
    CalendarDays, CheckCircle2, Clock3, MapPin, Search, Star, Trophy, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { Pagination } from '../../../components/Shared/Pagination';
import { StudentAffairsStats, toneClass } from '../../Admin/StudentAffairs/shared';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Registration = {
    id: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    registeredAt: string | null;
    attendedAt: string | null;
    pointsAwarded: number;
};

type EventRow = {
    id: number;
    no: number;
    title: string;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    organization: string | null;
    organizer: string | null;
    location: string | null;
    description: string | null;
    startsAt: string | null;
    endsAt: string | null;
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
    isRegistrationOpen: boolean;
    canRegister: boolean;
    canCancel: boolean;
    myRegistration: Registration | null;
    registerUrl: string;
    cancelUrl: string;
};

type Props = {
    shell: ShellProps;
    student: { name: string; nim: string | null; program: string | null };
    stats: { open: number; registered: number; attended: number; points: number };
    events: EventRow[];
    data: { currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; category: string };
    options: { categories: Record<string, string> };
    urls: { index: string; leaderboard: string };
};

export default function StudentEvents({ shell, student, stats, events, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<EventRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        const params: Record<string, string> = {};

        if (search !== '') params.q = search;
        if (filters.category !== '') params.category = filters.category;

        Object.entries(overrides).forEach(([key, value]) => {
            if (value !== '' && value !== undefined) params[key] = String(value);
        });

        router.get(urls.index, params, { preserveState: true, replace: true });
    };

    const cancelRegistration = () => {
        if (!pending) return;
        setProcessing(true);
        router.post(pending.cancelUrl, {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Event Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kemahasiswaan"
                        title="Event Mahasiswa"
                        description={`Ikuti agenda kemahasiswaan dan kumpulkan poin. ${student.name}${student.nim ? ` · ${student.nim}` : ''}`}
                        badges={[student.program || 'Program studi belum tersedia']}
                        actions={(
                            <a className="db-btn light" href={urls.leaderboard}><Trophy size={15} /> Poin & Leaderboard</a>
                        )}
                    />

                    <StudentAffairsStats
                        label="Ringkasan event mahasiswa"
                        items={[
                            { label: 'Event dibuka', value: stats.open, icon: CalendarDays },
                            { label: 'Pendaftaran aktif', value: stats.registered, icon: Clock3, tone: 'gold' },
                            { label: 'Event dihadiri', value: stats.attended, icon: CheckCircle2, tone: 'green' },
                            { label: 'Total poin', value: stats.points, icon: Star, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Cari Event</h2>
                        </div>
                        <div className="db-card-body" style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                            <div style={{ display: 'flex', gap: 8, flex: 1, minWidth: 220 }}>
                                <input
                                    className="db-input"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    onKeyDown={(event) => { if (event.key === 'Enter') visit({ page: 1 }); }}
                                    placeholder="Cari judul, penyelenggara, atau lokasi…"
                                    aria-label="Cari event"
                                />
                                <button className="db-btn primary" type="button" onClick={() => visit({ page: 1 })}>
                                    <Search size={15} /> Cari
                                </button>
                            </div>
                            <select
                                className="db-input"
                                style={{ maxWidth: 220 }}
                                value={filters.category}
                                onChange={(event) => visit({ category: event.target.value, page: 1 })}
                                aria-label="Filter kategori event"
                            >
                                <option value="">Semua kategori</option>
                                {Object.entries(options.categories).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </div>
                    </section>

                    {events.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ textAlign: 'center', padding: '36px 18px' }}>
                                <CalendarDays size={28} style={{ marginBottom: 8 }} />
                                <b style={{ display: 'block', color: 'var(--db-heading)', marginBottom: 4 }}>Belum ada event yang cocok</b>
                                <span className="db-hint">Coba ubah kata kunci atau filter kategori.</span>
                            </div>
                        </section>
                    ) : (
                        <div className="db-grid-2">
                            {events.map((event) => (
                                <article key={event.id} className="db-card" style={{ overflow: 'hidden' }}>
                                    {event.coverUrl ? (
                                        <img src={event.coverUrl} alt="" style={{ width: '100%', height: 150, objectFit: 'cover' }} />
                                    ) : (
                                        <div style={{ height: 96, display: 'grid', placeItems: 'center', background: 'var(--db-soft, rgba(104, 66, 244, 0.08))' }}>
                                            <CalendarDays size={28} />
                                        </div>
                                    )}
                                    <div className="db-card-body" style={{ display: 'grid', gap: 9 }}>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                            <span className={`db-badge ${toneClass(event.categoryTone)}`}>{event.categoryLabel}</span>
                                            <span className={`db-badge ${toneClass(event.statusTone)}`}>{event.statusLabel}</span>
                                            {event.isFeatured && <span className="db-badge gold"><Star size={11} /> Sorotan</span>}
                                        </div>
                                        <b style={{ color: 'var(--db-heading)', fontSize: 14.5, lineHeight: 1.35 }}>{event.title}</b>
                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                            <CalendarDays size={12} /> {event.startsAt || 'Jadwal belum ditentukan'}
                                        </span>
                                        {event.location && (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                <MapPin size={12} /> {event.location}
                                            </span>
                                        )}
                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                            <Users size={12} /> {event.registeredCount} peserta{event.quota ? ` · sisa ${event.seatsLeft ?? 0} dari ${event.quota}` : ''}
                                        </span>
                                        {event.registrationDeadline && (
                                            <span className="db-hint">Batas pendaftaran: {event.registrationDeadline}</span>
                                        )}
                                        {event.description && (
                                            <p className="db-hint" style={{ margin: 0 }}>{event.description}</p>
                                        )}
                                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginTop: 2 }}>
                                            <span className="db-badge gold"><Star size={11} /> {event.points} poin</span>
                                            {event.myRegistration && (
                                                <span className={`db-badge ${toneClass(event.myRegistration.statusTone)}`}>
                                                    {event.myRegistration.statusLabel}
                                                </span>
                                            )}
                                            <span style={{ marginLeft: 'auto', display: 'inline-flex', gap: 6 }}>
                                                {event.canRegister && (
                                                    <button
                                                        className="db-btn primary sm"
                                                        type="button"
                                                        onClick={() => router.post(event.registerUrl, {}, { preserveScroll: true })}
                                                    >
                                                        <CheckCircle2 size={13} /> Daftar
                                                    </button>
                                                )}
                                                {event.canCancel && (
                                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending(event)}>
                                                        <XCircle size={13} /> Batalkan
                                                    </button>
                                                )}
                                                {!event.isRegistrationOpen && !event.myRegistration && (
                                                    <span className="db-badge gray">Pendaftaran ditutup</span>
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </article>
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

                <ConfirmModal
                    open={pending !== null}
                    title="Batalkan pendaftaran?"
                    message={pending ? `Pendaftaran Anda pada "${pending.title}" akan dibatalkan.` : ''}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={cancelRegistration}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
