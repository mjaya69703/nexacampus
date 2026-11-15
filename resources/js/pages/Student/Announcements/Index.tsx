// Pengumuman portal mahasiswa (Inertia React).
// Hanya pengumuman untuk kelas yang masih aktif di KRS yang tampil.
import { Head, Link, router } from '@inertiajs/react';
import {
    BellRing, CalendarDays, FilterX, Inbox, Megaphone, Paperclip,
    Pin, Search, SlidersHorizontal, TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { Pagination } from '../../../components/Shared/Pagination';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number;
    title: string;
    excerpt: string;
    priority: string | null;
    priorityLabel: string;
    priorityTone: string;
    isPinned: boolean;
    creator: string;
    publishedAt: string;
    publishedHuman: string | null;
    hasAttachment: boolean;
    url: string;
    isUnread: boolean;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    announcements: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: { q: string; priority: string; from: string; to: string; perPage: number };
    priorities: Array<{ value: string; label: string }>;
    stats: { unread: number; total: number };
    urls: { index: string };
};

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

export default function AnnouncementsIndex({
    shell, hasProfile, announcements, filters, priorities, stats, urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const apply = (next: Partial<Props['filters']>): void => {
        const merged = { ...filters, ...next };
        const params: Record<string, string> = {};

        if (merged.q.trim() !== '') params.q = merged.q.trim();
        if (merged.priority !== '') params.priority = merged.priority;
        if (merged.from !== '') params.from = merged.from;
        if (merged.to !== '') params.to = merged.to;
        if (merged.perPage !== 10) params.per_page = String(merged.perPage);

        router.get(urls.index, params, { preserveScroll: true, preserveState: true });
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        apply({ q: search });
    };

    const reset = (): void => {
        setSearch('');
        router.get(urls.index, {}, { preserveScroll: true, preserveState: true });
    };

    const hasFilter = filters.q !== '' || filters.priority !== '' || filters.from !== '' || filters.to !== '';

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi"
                        title="Pengumuman"
                        description="Informasi kampus, program studi, dan kelas yang ditujukan untukmu."
                        badges={[`${stats.total} pengumuman`, `${stats.unread} belum dibaca`]}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Pengumuman spesifik prodi/kelas belum bisa ditampilkan.</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Megaphone size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.total}</span>
                                <span className="db-stat-label">Total pengumuman</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><BellRing size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.unread}</span>
                                <span className="db-stat-label">Belum dibaca</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Pin size={20} /></span>
                            <div>
                                <span className="db-stat-num">{announcements.rows.filter((row) => row.isPinned).length}</span>
                                <span className="db-stat-label">Disematkan</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Paperclip size={20} /></span>
                            <div>
                                <span className="db-stat-num">{announcements.rows.filter((row) => row.hasAttachment).length}</span>
                                <span className="db-stat-label">Berlampiran</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><SlidersHorizontal size={16} /> Filter &amp; Pencarian</h2>
                            {hasFilter && (
                                <button type="button" className="db-btn ghost sm" onClick={reset}>
                                    <FilterX size={13} /> Reset
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'flex', flexWrap: 'wrap', gap: 10, alignItems: 'flex-end' }}>
                                <label style={{ display: 'grid', gap: 4, flex: '1 1 260px' }}>
                                    <span className="db-hint" style={{ fontWeight: 700 }}>Cari pengumuman</span>
                                    <span style={{ position: 'relative', display: 'block' }}>
                                        <Search size={15} style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: 'var(--db-muted)' }} />
                                        <input
                                            type="text"
                                            className="db-input"
                                            style={{ paddingLeft: 34 }}
                                            placeholder="Judul atau isi pengumuman…"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                        />
                                    </span>
                                </label>

                                <label style={{ display: 'grid', gap: 4, flex: '0 1 170px' }}>
                                    <span className="db-hint" style={{ fontWeight: 700 }}>Prioritas</span>
                                    <select
                                        className="db-input"
                                        value={filters.priority}
                                        onChange={(event) => apply({ priority: event.target.value })}
                                    >
                                        <option value="">Semua prioritas</option>
                                        {priorities.map((priority) => (
                                            <option key={priority.value} value={priority.value}>{priority.label}</option>
                                        ))}
                                    </select>
                                </label>

                                <label style={{ display: 'grid', gap: 4, flex: '0 1 160px' }}>
                                    <span className="db-hint" style={{ fontWeight: 700 }}>Dari tanggal</span>
                                    <input
                                        type="date"
                                        className="db-input"
                                        value={filters.from}
                                        onChange={(event) => apply({ from: event.target.value })}
                                    />
                                </label>

                                <label style={{ display: 'grid', gap: 4, flex: '0 1 160px' }}>
                                    <span className="db-hint" style={{ fontWeight: 700 }}>Sampai tanggal</span>
                                    <input
                                        type="date"
                                        className="db-input"
                                        value={filters.to}
                                        onChange={(event) => apply({ to: event.target.value })}
                                    />
                                </label>

                                <label style={{ display: 'grid', gap: 4, flex: '0 1 110px' }}>
                                    <span className="db-hint" style={{ fontWeight: 700 }}>Per halaman</span>
                                    <select
                                        className="db-input"
                                        value={filters.perPage}
                                        onChange={(event) => apply({ perPage: Number(event.target.value) })}
                                    >
                                        {[10, 20, 50].map((size) => (
                                            <option key={size} value={size}>{size}</option>
                                        ))}
                                    </select>
                                </label>

                                <button type="submit" className="db-btn primary">
                                    <Search size={15} /> Cari
                                </button>
                            </form>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Megaphone size={16} /> Daftar Pengumuman</h2>
                            <span className="db-badge gray">{announcements.total} hasil</span>
                        </div>
                        <div className="db-card-body">
                            {announcements.rows.length === 0 ? (
                                <EmptyState
                                    icon={Inbox}
                                    title="Tidak ada pengumuman"
                                    description={hasFilter
                                        ? 'Tidak ada pengumuman yang cocok dengan filter. Coba longgarkan kata kunci atau rentang tanggal.'
                                        : 'Belum ada pengumuman yang ditujukan untukmu saat ini.'}
                                />
                            ) : (
                                <div style={{ display: 'grid', gap: 0 }}>
                                    {announcements.rows.map((row) => (
                                        <Link key={row.id} href={row.url} className={`db-ann${row.isUnread ? ' unread' : ''}`}>
                                            <span className="db-ann-dot" />
                                            <div>
                                                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                                                    <b>{row.title}</b>
                                                    {row.isPinned && <span className="db-badge"><Pin size={11} /> Disematkan</span>}
                                                    <span className={`db-badge ${toneClass(row.priorityTone)}`}>{row.priorityLabel}</span>
                                                    {row.hasAttachment && <span className="db-badge gray"><Paperclip size={11} /> Lampiran</span>}
                                                </div>
                                                <small>{row.excerpt}</small>
                                                <small style={{ marginTop: 4 }}>
                                                    <CalendarDays size={11} /> {row.publishedAt} · {row.creator}
                                                    {row.publishedHuman && ` · ${row.publishedHuman}`}
                                                </small>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}

                            <Pagination
                                page={announcements.currentPage}
                                totalPages={announcements.lastPage}
                                total={announcements.total}
                                unit="pengumuman"
                                onPrev={() => router.get(urls.index, {
                                    ...(filters.q !== '' ? { q: filters.q } : {}),
                                    ...(filters.priority !== '' ? { priority: filters.priority } : {}),
                                    ...(filters.from !== '' ? { from: filters.from } : {}),
                                    ...(filters.to !== '' ? { to: filters.to } : {}),
                                    ...(filters.perPage !== 10 ? { per_page: String(filters.perPage) } : {}),
                                    page: String(announcements.currentPage - 1),
                                }, { preserveScroll: true })}
                                onNext={() => router.get(urls.index, {
                                    ...(filters.q !== '' ? { q: filters.q } : {}),
                                    ...(filters.priority !== '' ? { priority: filters.priority } : {}),
                                    ...(filters.from !== '' ? { from: filters.from } : {}),
                                    ...(filters.to !== '' ? { to: filters.to } : {}),
                                    ...(filters.perPage !== 10 ? { per_page: String(filters.perPage) } : {}),
                                    page: String(announcements.currentPage + 1),
                                }, { preserveScroll: true })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
