// Daftar laporan kerusakan — memakai kit CRUD shared (pola menu academic).
import { Head, router } from '@inertiajs/react';
import { Eye, Plus, Trash2, Upload, Wrench } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; ticketNumber: string; title: string; room: string | null;
    asset: string | null; reporter: string; priority: string; priorityLabel: string;
    status: string; statusLabel: string; createdAt: string | null;
    editUrl: string | null; showUrl: string; deleteUrl: string;
    restoreUrl: string; forceUrl: string; resolveUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; resolve: boolean; view: boolean };
    stats: { total: number; open: number; progress: number; resolved: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; priority: string; status: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    urls: {
        index: string; create: string; export: string; exportPdf: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force' | 'resolve'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const PRIORITY_OPTIONS = [
    { id: 'low', name: 'Rendah' },
    { id: 'medium', name: 'Sedang' },
    { id: 'high', name: 'Tinggi' },
    { id: 'urgent', name: 'Sangat Mendesak' },
];

const STATUS_OPTIONS = [
    { id: 'open', name: 'Terbuka' },
    { id: 'in_progress', name: 'Dalam Pengerjaan' },
    { id: 'resolved', name: 'Selesai' },
    { id: 'closed', name: 'Ditutup' },
    { id: 'rejected', name: 'Ditolak' },
];

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus tiket?',
        message: (n) => `"${n}" dipindah ke sampah dan bisa dipulihkan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan tiket?',
        message: (n) => `"${n}" kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya dan tidak bisa dipulihkan.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    resolve: {
        title: () => 'Tandai selesai?',
        message: (n) => `"${n}" ditandai selesai. Catatan penyelesaian diisi di halaman detail.`,
        confirm: 'Ya, selesai',
        danger: false,
    },
    'bulk-delete': {
        title: () => 'Hapus tiket terpilih?',
        message: (n) => `${n} tiket dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan tiket terpilih?',
        message: (n) => `${n} tiket kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen tiket terpilih?',
        message: (n) => `${n} tiket dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function MaintenanceIndex({ shell, can, stats, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount = (filters.priority !== '' ? 1 : 0) + (filters.status !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', priority: '', status: '', page: 1 } as never, '', { page: 1 }), {
            preserveState: true,
            replace: true,
            onSuccess: () => setSelected([]),
        });
    };

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const toggleSort = (key: string) => {
        visit({
            sort: key,
            direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        switch (pending.kind) {
            case 'delete':
                router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'restore':
                router.post(pending.row.restoreUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'force':
                router.delete(pending.row.forceUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'resolve':
                router.post(pending.row.resolveUrl, { resolution_notes: 'Masalah telah selesai diperbaiki.' }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-delete':
                router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-restore':
                router.post(urls.bulkRestore, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-force':
                router.post(urls.bulkForceDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.ticketNumber : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Laporan Kerusakan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wrench}
                        eyebrow="Kampus"
                        title="Laporan Kerusakan"
                        description="Tangani tiket kerusakan fasilitas: tugaskan teknisi, selesaikan dengan catatan, sinkron kondisi aset."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Laporan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik tiket">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Wrench size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total tiket</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Wrench size={20} /></span>
                            <div><span className="db-stat-num">{stats.open}</span><span className="db-stat-label">Terbuka</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Wrench size={20} /></span>
                            <div><span className="db-stat-num">{stats.resolved}</span><span className="db-stat-label">Selesai</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Tiket</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={!isTrash}
                                    className={`crud-tab${!isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'all', page: 1 }, true)}
                                >
                                    Semua <span className="db-badge gray">{stats.total}</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={isTrash}
                                    className={`crud-tab${isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'trash', page: 1 }, true)}
                                >
                                    <Trash2 size={13} /> Sampah <span className="db-badge gray">{stats.trashed}</span>
                                </button>
                            </div>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'title', label: 'Tiket', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">{row.ticketNumber}{row.room ? ` · ${row.room}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'priority', label: 'Prioritas', sortable: false,
                                        render: (row) => <span className="db-badge gray">{row.priorityLabel}</span>,
                                    },
                                    {
                                        key: 'status', label: 'Status', sortable: false,
                                        render: (row) => <span className={`db-badge ${row.status === 'resolved' ? 'green' : row.status === 'open' ? '' : 'gray'}`}>{row.statusLabel}</span>,
                                    },
                                    { key: 'created_at', label: 'Dibuat', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nomor, judul…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.priority}
                                            onChange={(e) => visit({ priority: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prioritas"
                                        >
                                            <option value="">Semua prioritas</option>
                                            {PRIORITY_OPTIONS.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {STATUS_OPTIONS.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: isTrash ? 'bulk-force' : 'bulk-delete' })}
                                bulkLabel={isTrash ? 'Hapus permanen terpilih' : 'Hapus terpilih'}
                                canRestore={isTrash && can.restore}
                                onBulkRestore={isTrash ? () => setPending({ kind: 'bulk-restore' }) : undefined}
                                canUpdate={!isTrash && can.update}
                                editUrl={(row) => row.editUrl ?? urls.index}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                customActions={(row) => (
                                    <>
                                        {!isTrash && can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={14} />
                                            </a>
                                        )}
                                        {!isTrash && can.resolve && (row.status === 'open' || row.status === 'in_progress') && (
                                            <button
                                                className="db-btn ghost sm"
                                                type="button"
                                                title="Tandai selesai"
                                                onClick={() => setPending({ kind: 'resolve', row })}
                                            >
                                                <Wrench size={14} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions={(!isTrash && (can.update || can.view || can.resolve)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Buat Laporan"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada tiket yang cocok dengan filter.'}
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy ? copy.title(copyName) : ''}
                    message={copy ? copy.message(copyName) : ''}
                    confirmLabel={copy?.confirm ?? 'Ya'}
                    danger={copy?.danger ?? true}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
