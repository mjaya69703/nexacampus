// Log aktivitas — read-only (Inertia React).
import { Head, router } from '@inertiajs/react';
import { CalendarDays, Eye, History, Layers, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; log: string | null; description: string;
    event: string | null; subject: string; causer: string; preview: string;
    createdAt: string | null; detailUrl: string;
};

type Props = {
    shell: ShellProps;
    stats: { total: number; today: number; logNames: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; log: string; event: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    urls: { index: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function ActivityLogIndex({ shell, stats, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const toggleSort = (key: string) => {
        visit({
            sort: key,
            direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Log Aktivitas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={History}
                        eyebrow="Sistem"
                        title="Log Aktivitas"
                        description="Jejak aksi pengguna di seluruh modul: siapa, melakukan apa, ke data mana, dan kapan."
                    />

                    <section className="db-stats" aria-label="Statistik log aktivitas">
                        <div className="db-stat">
                            <span className="db-stat-icon"><History size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total aktivitas</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{stats.today}</span><span className="db-stat-label">Hari ini</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Layers size={20} /></span>
                            <div><span className="db-stat-num">{stats.logNames}</span><span className="db-stat-label">Jenis log</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Log Aktivitas</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    { key: 'log_name', label: 'Log', sortable: true, render: (row) => <span className="crud-name">{row.log ?? '-'}</span> },
                                    { key: 'description', label: 'Aksi', sortable: true, render: (row) => row.description },
                                    { key: 'subject', label: 'Subjek', render: (row) => row.subject },
                                    {
                                        key: 'causer', label: 'Pelaku',
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}>
                                                <UsersRound size={14} style={{ color: 'var(--db-muted)' }} /> {row.causer}
                                            </span>
                                        ),
                                    },
                                    { key: 'preview', label: 'Ringkasan', render: (row) => <small className="db-hint">{row.preview}</small> },
                                    { key: 'created_at', label: 'Waktu', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari log, aksi, event…"
                                filterBar={(
                                    <>
                                        <input
                                            className="db-input"
                                            value={filters.log}
                                            placeholder="Nama log…"
                                            onChange={(e) => visit({ log: e.target.value, page: 1 })}
                                            aria-label="Filter nama log"
                                        />
                                        <input
                                            className="db-input"
                                            value={filters.event}
                                            placeholder="Event…"
                                            onChange={(e) => visit({ event: e.target.value, page: 1 })}
                                            aria-label="Filter event"
                                        />
                                    </>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.detailUrl} title="Lihat detail">
                                        <Eye size={13} /> Detail
                                    </a>
                                )}
                                selectable={false}
                                emptyText="Belum ada log aktivitas yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
