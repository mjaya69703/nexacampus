// Log notifikasi — read-only (Inertia React).
import { Head, router } from '@inertiajs/react';
import { BellRing, CircleCheck, CircleX, Clock3, MinusCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; event: string | null; channel: string | null;
    provider: string | null; status: string; statusTone: string;
    recipient: string | null; phone: string | null; subject: string | null;
    bodyPreview: string; errorPreview: string;
    sentAt: string | null; createdAt: string | null;
};

type Props = {
    shell: ShellProps;
    stats: { total: number; sent: number; failed: number; skipped: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; channel: string; status: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    urls: { index: string; export: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const CHANNELS = ['whatsapp', 'email', 'in_app', 'web_push'];
const STATUSES = ['queued', 'sent', 'failed', 'skipped'];

export default function NotificationLogIndex({ shell, stats, data, filters, urls }: Props) {
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

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    return (
        <AdminShell shell={shell}>
            <Head title={`Log Notifikasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BellRing}
                        eyebrow="Sistem"
                        title="Log Notifikasi"
                        description="Jejak audit setiap notifikasi: WhatsApp, email, dalam aplikasi, dan web push — terkirim, gagal, atau dilewati."
                    />

                    <section className="db-stats" aria-label="Statistik log notifikasi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><BellRing size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total log</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CircleCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.sent}</span><span className="db-stat-label">Terkirim</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><CircleX size={20} /></span>
                            <div><span className="db-stat-num">{stats.failed}</span><span className="db-stat-label">Gagal</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><MinusCircle size={20} /></span>
                            <div><span className="db-stat-num">{stats.skipped}</span><span className="db-stat-label">Dilewati</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Log Notifikasi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    { key: 'event_key', label: 'Event', sortable: true, render: (row) => <span className="crud-name">{row.event ?? '-'}</span> },
                                    { key: 'channel', label: 'Channel', sortable: true, render: (row) => row.channel ?? '-' },
                                    { key: 'status', label: 'Status', sortable: true, render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.status}</span> },
                                    {
                                        key: 'recipient_name', label: 'Penerima',
                                        render: (row) => (
                                            <span>
                                                <span style={{ display: 'block', fontSize: 12.5 }}>{row.recipient ?? '-'}</span>
                                                <small className="db-hint">{row.phone ?? ''}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'subject', label: 'Subject', render: (row) => row.subject ?? '-' },
                                    { key: 'body', label: 'Pesan', render: (row) => <small className="db-hint">{row.bodyPreview}</small> },
                                    { key: 'error', label: 'Error', render: (row) => <small className="db-hint">{row.errorPreview}</small> },
                                    { key: 'sent_at', label: 'Terkirim', sortable: true, render: (row) => row.sentAt ?? '-' },
                                    { key: 'created_at', label: 'Dibuat', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari event, subject, penerima…"
                                filterBar={(
                                    <>
                                        <select
                                            className="db-input"
                                            value={filters.channel}
                                            onChange={(e) => visit({ channel: e.target.value, page: 1 })}
                                            aria-label="Filter channel"
                                        >
                                            <option value="">Semua channel</option>
                                            {CHANNELS.map((c) => <option key={c} value={c}>{c}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 })}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions={false}
                                selectable={false}
                                exportHref={exportHref}
                                emptyText="Belum ada log notifikasi yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>

                    <div className="db-note info">
                        <Clock3 size={15} />
                        <span>Log lama dibersihkan otomatis mengikuti retensi di Pengaturan.</span>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
