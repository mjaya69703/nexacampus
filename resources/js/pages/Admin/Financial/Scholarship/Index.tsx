// Daftar program beasiswa — kit CRUD (sampah/restore, toggle aktif, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Award, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; type: string; typeText: string;
    discount: string; duration: string; recipients: string; isActive: boolean;
    editUrl: string; deleteUrl: string; restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; type: string; discount_type: string; active: string;
        mode: 'all' | 'trash'; sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    types: string[];
    discountTypes: string[];
    urls: {
        index: string; create: string; store: string; export: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending = { kind: 'delete' | 'restore' | 'force'; row: Row } | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' } | null;

const MODAL_COPY: Record<string, { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus beasiswa?',
        message: (n) => `Program "${n}" dipindahkan ke sampah dan bisa dipulihkan.`,
        confirm: 'Ya, hapus', danger: true,
    },
    restore: {
        title: () => 'Pulihkan beasiswa?',
        message: (n) => `Program "${n}" dikembalikan ke daftar aktif.`,
        confirm: 'Ya, pulihkan', danger: false,
    },
    force: {
        title: () => 'Hapus permanen beasiswa?',
        message: (n) => `Program "${n}" dihapus permanen dan tidak bisa dipulihkan.`,
        confirm: 'Ya, hapus permanen', danger: true,
    },
    'bulk-delete': {
        title: (n) => `Hapus ${n} beasiswa?`,
        message: () => 'Program terpilih dipindahkan ke sampah dan bisa dipulihkan.',
        confirm: 'Ya, hapus', danger: true,
    },
    'bulk-restore': {
        title: (n) => `Pulihkan ${n} beasiswa?`,
        message: () => 'Program terpilih dikembalikan ke daftar aktif.',
        confirm: 'Ya, pulihkan', danger: false,
    },
    'bulk-force': {
        title: (n) => `Hapus permanen ${n} beasiswa?`,
        message: () => 'Program terpilih dihapus permanen dan tidak bisa dipulihkan.',
        confirm: 'Ya, hapus permanen', danger: true,
    },
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const TYPE_TEXT: Record<string, string> = {
    full: 'Pembebasan Penuh', partial: 'Potongan Sebagian', merit: 'Prestasi', need_based: 'Bantuan Ekonomi',
};

export default function ScholarshipIndex({ shell, can, stats, data, filters, types, discountTypes, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const isTrash = filters.mode === 'trash';

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [filters.type, filters.discount_type, filters.active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ type: '', discount_type: '', active: '', page: 1 }, true);
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

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, {}, { preserveScroll: true });
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
    const copyName = pending && 'row' in pending ? pending.row.name : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Program Beasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Keuangan"
                        title="Program Beasiswa"
                        description="Potongan otomatis saat penerbitan invoice berkala untuk penerima aktif."
                        actions={can.create && !isTrash ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Beasiswa</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik beasiswa">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total program</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Beasiswa</h2>
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
                                        key: 'name', label: 'Program', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.typeText} · {row.recipients}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'discount', label: 'Potongan / Durasi', sortable: false, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.discount}</b>
                                                <small className="db-hint">{row.duration}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status aktif">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleActive(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                            )
                                        ),
                                    },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama program…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(e) => visit({ type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori"
                                        >
                                            <option value="">Semua kategori</option>
                                            {types.map((t) => <option key={t} value={t}>{TYPE_TEXT[t] ?? t}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.discount_type}
                                            onChange={(e) => visit({ discount_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter metode potongan"
                                        >
                                            <option value="">Semua metode</option>
                                            {discountTypes.map((t) => <option key={t} value={t}>{t === 'fixed' ? 'Nominal Tetap' : 'Persentase'}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.active}
                                            onChange={(e) => visit({ active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
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
                                editUrl={(row) => row.editUrl}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && can.update) || can.restore || can.delete}
                                customActions={(!isTrash && can.update) ? (row) => (
                                    <a className="db-btn ghost sm" href={row.editUrl} title="Ubah">
                                        <Pencil size={13} />
                                    </a>
                                ) : undefined}
                                canCreate={false}
                                createLabel="Tambah Beasiswa"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada beasiswa yang cocok dengan filter.'}
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
