// Pengumuman dosen — inbox, milik saya, filter prioritas/pencarian, dan pagination.
import { Head, Link, router } from '@inertiajs/react';
import { Bell, ChevronLeft, ChevronRight, Inbox, Megaphone, Pin, Plus, Search, SlidersHorizontal } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number; title: string; excerpt: string; priority: string | null; priorityLabel: string; isPinned: boolean;
    creator: string; publishedAt: string; hasAttachment: boolean; url: string; isMine: boolean; editUrl: string | null;
};

type Props = {
    shell: ShellProps;
    tab: string;
    rows: Row[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { search: string; priority: string; perPage: number };
    counts: { inbox: number; mine: number; unread: number };
    urls: { index: string; create: string };
};

const priorityTone = (priority: string | null) => {
    if (priority === 'urgent') return 'red';
    if (priority === 'important') return 'amber';
    return 'gray';
};

export default function AnnouncementsIndex({ shell, tab, rows, pagination, filters, counts, urls }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [priority, setPriority] = useState(filters.priority);
    const [perPage, setPerPage] = useState(String(filters.perPage));

    const navigate = (overrides: Record<string, string | number> = {}) => {
        router.get(urls.index, {
            tab,
            search,
            filterPriority: priority,
            perPage,
            ...overrides,
        }, { preserveState: true, preserveScroll: true });
    };

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        navigate({ page: 1 });
    };

    const switchTab = (nextTab: string) => {
        router.get(urls.index, { tab: nextTab, search, filterPriority: priority, perPage }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi"
                        title="Pengumuman"
                        description="Pengumuman yang diterima dan yang Anda buat."
                        badges={[`${counts.inbox} inbox`, `${counts.mine} milik saya`, `${counts.unread} belum dibaca`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Buat Pengumuman</Link>}
                    />

                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                        <button className={`db-btn ${tab === 'inbox' ? 'primary' : 'ghost'} sm`} type="button" onClick={() => switchTab('inbox')}>
                            <Inbox size={13} /> Inbox ({counts.inbox})
                        </button>
                        <button className={`db-btn ${tab === 'mine' ? 'primary' : 'ghost'} sm`} type="button" onClick={() => switchTab('mine')}>
                            <Megaphone size={13} /> Milik Saya ({counts.mine})
                        </button>
                        {counts.unread > 0 && <span className="db-badge red"><Bell size={11} /> {counts.unread} belum dibaca</span>}
                    </div>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><SlidersHorizontal size={16} /> Filter</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={applyFilters} className="crud-grid">
                                <div>
                                    <label className="crud-label">Pencarian</label>
                                    <input className="db-input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Judul atau isi pengumuman…" />
                                </div>
                                <div>
                                    <label className="crud-label">Prioritas</label>
                                    <select className="db-input" value={priority} onChange={(event) => setPriority(event.target.value)}>
                                        <option value="">Semua prioritas</option>
                                        <option value="normal">Normal</option>
                                        <option value="important">Penting</option>
                                        <option value="urgent">Mendesak</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Baris per halaman</label>
                                    <select className="db-input" value={perPage} onChange={(event) => setPerPage(event.target.value)}>
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                                    <button className="db-btn primary sm" type="submit"><Search size={13} /> Terapkan</button>
                                    <button className="db-btn ghost sm" type="button" onClick={() => { setSearch(''); setPriority(''); setPerPage('10'); router.get(urls.index, { tab }, { preserveState: true }); }}>Reset</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">{tab === 'mine' ? <Megaphone size={16} /> : <Inbox size={16} />} {tab === 'mine' ? 'Pengumuman saya' : 'Inbox pengumuman'} ({pagination.total})</h2>
                            <span className="db-hint">Halaman {pagination.currentPage} dari {pagination.lastPage}</span>
                        </div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengumuman" description={tab === 'mine' ? 'Buat pengumuman baru untuk mahasiswa.' : 'Tidak ada pengumuman yang cocok dengan filter.'} />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {rows.map((row) => (
                                        <article key={row.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{row.title} {row.isPinned && <Pin size={12} />}</b>
                                                    <small>{row.creator} · {row.publishedAt}{row.hasAttachment ? ' · lampiran' : ''}</small>
                                                </div>
                                                <span className={`db-badge ${priorityTone(row.priority)}`}>{row.priorityLabel}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: 'var(--db-muted)', marginTop: 4 }}>{row.excerpt}</div>
                                            <div className="db-class-actions">
                                                <Link href={row.url} className="db-btn ghost sm">Detail</Link>
                                                {row.editUrl && <Link href={row.editUrl} className="db-btn ghost sm">Edit</Link>}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </div>
                        {pagination.lastPage > 1 && (
                            <div className="db-card-body" style={{ borderTop: '1px solid var(--db-line)', display: 'flex', gap: 8, alignItems: 'center' }}>
                                <button className="db-btn ghost sm" type="button" disabled={pagination.currentPage <= 1} onClick={() => navigate({ page: pagination.currentPage - 1 })}>
                                    <ChevronLeft size={13} /> Sebelumnya
                                </button>
                                <span className="db-hint">Halaman {pagination.currentPage} / {pagination.lastPage}</span>
                                <button className="db-btn ghost sm" type="button" disabled={pagination.currentPage >= pagination.lastPage} onClick={() => navigate({ page: pagination.currentPage + 1 })} style={{ marginLeft: 'auto' }}>
                                    Berikutnya <ChevronRight size={13} />
                                </button>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
