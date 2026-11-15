// Daftar peminjaman ruangan — memakai kit CRUD shared (pola menu academic).
import { Head, router } from '@inertiajs/react';
import { CalendarCheck, Check, Eye, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; reservationNumber: string; title: string; room: string | null;
    requester: string; date: string | null; time: string; status: string; statusLabel: string;
    createdAt: string | null; editUrl: string | null; showUrl: string; deleteUrl: string;
    restoreUrl: string; forceUrl: string; approveUrl: string; rejectUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; approve: boolean; view: boolean };
    stats: { total: number; pending: number; approved: number; rejected: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; status: string; room: string | number; date: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    statusOptions: Record<string, string>;
    roomOptions: { id: number; name: string }[];
    urls: {
        index: string; create: string; export: string; exportPdf: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force' | 'approve'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus reservasi?',
        message: (n) => `"${n}" dipindah ke sampah dan bisa dipulihkan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan reservasi?',
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
    approve: {
        title: () => 'Setujui reservasi?',
        message: (n) => `"${n}" disetujui. Sistem menolak bila bentrok jadwal/reservasi lain.`,
        confirm: 'Ya, setujui',
        danger: false,
    },
    'bulk-delete': {
        title: () => 'Hapus reservasi terpilih?',
        message: (n) => `${n} reservasi dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan reservasi terpilih?',
        message: (n) => `${n} reservasi kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen reservasi terpilih?',
        message: (n) => `${n} reservasi dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function ReservationIndex({ shell, can, stats, data, filters, statusOptions, roomOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount =
        (filters.status !== '' ? 1 : 0) +
        (String(filters.room) !== '' ? 1 : 0) +
        (filters.date !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', status: '', room: '', date: '', page: 1 } as never, '', { page: 1 }), {
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
            case 'approve':
                router.post(pending.row.approveUrl, {}, { preserveScroll: true, onFinish: done });
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
    const copyName = pending && 'row' in pending ? pending.row.reservationNumber : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Peminjaman Ruangan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kampus"
                        title="Peminjaman Ruangan"
                        description="Verifikasi permohonan, setujui yang bebas bentrok, dan tolak dengan alasan jelas."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Reservasi</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik reservasi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.pending}</span><span className="db-stat-label">Pending</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Check size={20} /></span>
                            <div><span className="db-stat-num">{stats.approved}</span><span className="db-stat-label">Disetujui</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><X size={20} /></span>
                            <div><span className="db-stat-num">{stats.rejected}</span><span className="db-stat-label">Ditolak</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Reservasi</h2>
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
                                        key: 'title', label: 'Reservasi', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">{row.reservationNumber}{row.room ? ` · ${row.room}` : ''} · {row.requester}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'reservation_date', label: 'Jadwal', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.date ?? '-'}</b>
                                                <small className="db-hint">{row.time}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status', sortable: false,
                                        render: (row) => <span className={`db-badge ${row.status === 'approved' ? 'green' : row.status === 'pending' ? 'gold' : 'gray'}`}>{row.statusLabel}</span>,
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
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {Object.entries(statusOptions).map(([id, name]) => (
                                                <option key={id} value={id}>{name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={String(filters.room)}
                                            onChange={(e) => visit({ room: e.target.value, page: 1 }, true)}
                                            aria-label="Filter ruangan"
                                        >
                                            <option value="">Semua ruangan</option>
                                            {roomOptions.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="date"
                                            className="db-input"
                                            value={filters.date}
                                            onChange={(e) => visit({ date: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tanggal"
                                        />
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
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail & approval">
                                                <Eye size={14} />
                                            </a>
                                        )}
                                        {!isTrash && can.approve && row.status === 'pending' && (
                                            <button
                                                className="db-btn ghost sm"
                                                type="button"
                                                title="Setujui"
                                                onClick={() => setPending({ kind: 'approve', row })}
                                            >
                                                <Check size={14} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions={(!isTrash && (can.update || can.view || can.approve)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Buat Reservasi"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada reservasi yang cocok dengan filter.'}
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
