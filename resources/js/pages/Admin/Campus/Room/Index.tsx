// Daftar ruangan — memakai kit CRUD shared (pola menu academic).
import { Head, router } from '@inertiajs/react';
import { DoorOpen, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; code: string | null; building: string | null;
    floor: string | null; capacity: number | null; type: string; usageCount: number;
    isActive: boolean; createdAt: string | null; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; capacity: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; building: string | number; type: string; is_active: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    buildingOptions: { id: number; name: string }[];
    typeOptions: string[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string; importSubmit: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string, used: boolean) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus ruangan?',
        message: (n, used) => `"${n}" dipindah ke sampah dan bisa dipulihkan.${used ? ' Ruangan ini masih dipakai — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan ruangan?',
        message: (n) => `"${n}" kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n, used) => `"${n}" dihapus selamanya dan tidak bisa dipulihkan.${used ? ' Ruangan ini masih dipakai — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus ruangan terpilih?',
        message: (n) => `${n} ruangan dipindah ke sampah. Yang masih dipakai akan dilewati.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan ruangan terpilih?',
        message: (n) => `${n} ruangan kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen ruangan terpilih?',
        message: (n) => `${n} ruangan dihapus selamanya. Yang masih dipakai akan dilewati.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function RoomIndex({ shell, can, stats, data, filters, buildingOptions, typeOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount =
        (String(filters.building) !== '' ? 1 : 0) +
        (filters.type !== '' ? 1 : 0) +
        (filters.is_active !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', building: '', type: '', is_active: '', page: 1 } as never, '', { page: 1 }), {
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

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
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

    const submitImport = (file: File) => {
        setProcessing(true);
        router.post(urls.importSubmit, { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setImportOpen(false);
            },
        });
    };

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.name : String(selected.length);
    const copyUsed = pending && 'row' in pending ? pending.row.usageCount > 0 : false;

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Ruangan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={DoorOpen}
                        eyebrow="Kampus"
                        title="Ruangan"
                        description="Kelola ruangan per gedung. Ruangan yang dipakai jadwal, reservasi, aset, atau tiket tidak bisa dihapus."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Ruangan</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} ruangan berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik ruangan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><DoorOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total ruangan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><DoorOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><DoorOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.capacity}</span><span className="db-stat-label">Total kapasitas</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Ruangan</h2>
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
                                        key: 'name', label: 'Ruangan', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code ?? 'Tanpa kode'}{row.building ? ` · ${row.building}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'type', label: 'Tipe', sortable: false,
                                        render: (row) => <span className="db-badge gray">{row.type}</span>,
                                    },
                                    {
                                        key: 'capacity', label: 'Kapasitas', sortable: false, align: 'right',
                                        render: (row) => row.capacity ?? '-',
                                    },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isActive ? 'Nonaktifkan' : 'Aktifkan'}>
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
                                searchPlaceholder="Cari nama, kode…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.building)}
                                            onChange={(e) => visit({ building: e.target.value, page: 1 }, true)}
                                            aria-label="Filter gedung"
                                        >
                                            <option value="">Semua gedung</option>
                                            {buildingOptions.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(e) => visit({ type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tipe"
                                        >
                                            <option value="">Semua tipe</option>
                                            {typeOptions.map((t) => (
                                                <option key={t} value={t}>{t}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(e) => visit({ is_active: e.target.value, page: 1 }, true)}
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
                                editUrl={(row) => row.editUrl ?? urls.index}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && can.update) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Ruangan"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada ruangan yang cocok dengan filter.'}
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
                    message={copy ? copy.message(copyName, copyUsed) : ''}
                    confirmLabel={copy?.confirm ?? 'Ya'}
                    danger={copy?.danger ?? true}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                <ImportModal
                    open={importOpen}
                    title="Impor Ruangan"
                    description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Kolom building diisi nama gedung. Satu baris gagal berarti file ditolak."
                    templateUrl={urls.importTemplate}
                    templateLabel="Unduh template"
                    processing={processing}
                    onSubmit={submitImport}
                    onClose={() => setImportOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
