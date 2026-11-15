// Daftar aset kampus per PRODUK (grup) — memakai kit CRUD shared.
// 1 baris = 1 kombinasi nama+merk+model+kategori + ringkasan jumlah/kondisi.
import { Head, router } from '@inertiajs/react';
import { Boxes, Eye, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type GroupRow = {
    id: number; no: number; name: string | null; brand: string | null; model: string | null;
    category: string | null; categoryLabel: string | null; qty: number; good: number; damaged: number;
    totalValue: string | null; rooms: string[]; showUrl: string;
};

type TrashRow = {
    id: number; no: number; assetCode: string; name: string; room: string | null;
    restoreUrl: string; forceUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; view: boolean };
    stats: { products: number; total: number; good: number; damaged: number; trashed: number };
    data: {
        rows: GroupRow[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    trash?: {
        rows: TrashRow[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; category: string | number; condition: string; status: string; funding: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    categoryOptions: { id: number; name: string }[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; bulkCreate: string; export: string; exportPdf: string;
        importTemplate: string; importSubmit: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'restore' | 'force'; row: TrashRow }
    | { kind: 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const CONDITION_OPTIONS = [
    { id: 'good', name: 'Baik (Normal)' },
    { id: 'minor_damage', name: 'Rusak Ringan' },
    { id: 'major_damage', name: 'Rusak Berat' },
    { id: 'in_repair', name: 'Sedang Dalam Perbaikan' },
];

const STATUS_OPTIONS = [
    { id: 'active', name: 'Aktif Digunakan' },
    { id: 'maintenance', name: 'Maintenance' },
    { id: 'disposed', name: 'Penghapusan / Afkir' },
];

const FUNDING_OPTIONS = [
    { id: 'anggaran_kampus', name: 'Anggaran Kampus' },
    { id: 'hibah', name: 'Hibah' },
    { id: 'bantuan_pemerintah', name: 'Bantuan Pemerintah' },
    { id: 'sumbangan', name: 'Sumbangan' },
    { id: 'lainnya', name: 'Lainnya' },
];

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    restore: {
        title: () => 'Pulihkan aset?',
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
    'bulk-restore': {
        title: () => 'Pulihkan aset terpilih?',
        message: (n) => `${n} aset kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen aset terpilih?',
        message: (n) => `${n} aset dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function AssetIndex({ shell, can, stats, data, trash, filters, categoryOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount =
        (String(filters.category) !== '' ? 1 : 0) +
        (filters.condition !== '' ? 1 : 0) +
        (filters.status !== '' ? 1 : 0) +
        (filters.funding !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', category: '', condition: '', status: '', funding: '', page: 1 } as never, '', { page: 1 }), {
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

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = (trash?.rows ?? []).map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const toggleSort = (key: string) => {
        visit({
            sort: key,
            direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        });
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
            case 'restore':
                router.post(pending.row.restoreUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'force':
                router.delete(pending.row.forceUrl, { preserveScroll: true, onFinish: done });
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
    const copyName = pending && 'row' in pending ? pending.row.name || pending.row.assetCode : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Aset Kampus · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Boxes}
                        eyebrow="Kampus"
                        title="Aset & Fasilitas"
                        description="Satu baris = satu jenis barang. Klik untuk melihat daftar unit per nomor inventaris."
                        actions={can.create ? (
                            <>
                                <a className="db-btn light" href={urls.bulkCreate}><Plus size={15} /> Tambah Banyak Unit</a>
                                <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Satuan</a>
                            </>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} aset berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik aset">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Boxes size={20} /></span>
                            <div><span className="db-stat-num">{stats.products}</span><span className="db-stat-label">Jenis barang</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Boxes size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total unit</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Boxes size={20} /></span>
                            <div><span className="db-stat-num">{stats.damaged}</span><span className="db-stat-label">Unit rusak</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">{isTrash ? 'Sampah Unit' : 'Tabel Jenis Barang'}</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={!isTrash}
                                    className={`crud-tab${!isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'all', page: 1 }, true)}
                                >
                                    Semua <span className="db-badge gray">{stats.products}</span>
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
                            {!isTrash ? (
                                <CrudTable<GroupRow>
                                columns={[
                                    { key: 'no', label: 'No', sortable: false, render: (row) => row.no },
                                        {
                                            key: 'product', label: 'Produk', sortable: false,
                                            render: (row) => (
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name ?? '(Tanpa nama)'}</b>
                                                    <small className="db-hint">
                                                        {[row.brand, row.model, row.categoryLabel].filter(Boolean).join(' · ') || '—'}
                                                    </small>
                                                </span>
                                            ),
                                        },
                                        {
                                            key: 'qty', label: 'Jumlah', sortable: true, align: 'right',
                                            render: (row) => <span className="db-badge green">{row.qty} unit</span>,
                                        },
                                        {
                                            key: 'condition', label: 'Kondisi', sortable: false,
                                            render: (row) => row.damaged > 0
                                                ? <span className="db-badge gray">{row.good} baik · {row.damaged} rusak</span>
                                                : <span className="db-badge green">{row.good} baik</span>,
                                        },
                                        {
                                            key: 'rooms', label: 'Ruangan', sortable: false,
                                            render: (row) => row.rooms.length > 0
                                                ? <small className="db-hint">{row.rooms.slice(0, 3).join(', ')}{row.rooms.length > 3 ? ` +${row.rooms.length - 3}` : ''}</small>
                                                : <span className="db-hint">—</span>,
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
                                    searchPlaceholder="Cari nama, merk, model, kode…"
                                    filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.category)}
                                            onChange={(e) => visit({ category: e.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categoryOptions.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.condition}
                                                onChange={(e) => visit({ condition: e.target.value, page: 1 }, true)}
                                                aria-label="Filter kondisi"
                                            >
                                                <option value="">Semua kondisi</option>
                                                {CONDITION_OPTIONS.map((opt) => (
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
                                            <select
                                                className="db-input"
                                                value={filters.funding}
                                                onChange={(e) => visit({ funding: e.target.value, page: 1 }, true)}
                                                aria-label="Filter sumber dana"
                                            >
                                                <option value="">Semua sumber dana</option>
                                                {FUNDING_OPTIONS.map((opt) => (
                                                    <option key={opt.id} value={opt.id}>{opt.name}</option>
                                                ))}
                                            </select>
                                        </FilterPanel>
                                    )}
                                    selected={[]}
                                    onToggle={() => undefined}
                                    onToggleAll={() => undefined}
                                    selectable={false}
                                    canDelete={false}
                                    onBulkDelete={() => undefined}
                                    canUpdate={false}
                                    customActions={(row) => (
                                        <a className="db-btn ghost sm" href={row.showUrl} title="Lihat unit">
                                            <Eye size={14} /> Unit
                                        </a>
                                    )}
                                    showActions={can.view}
                                    canCreate={false}
                                    createLabel="Tambah Aset"
                                    exportHref={exportHref}
                                    exportExtra={[
                                        { label: 'PDF laporan', href: urls.exportPdf },
                                    ]}
                                    extraActions={can.create ? (
                                        <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                            <Upload size={14} /> Import
                                        </button>
                                    ) : undefined}
                                    emptyText="Belum ada barang yang cocok dengan filter."
                                    onPage={(p) => visit({ page: p })}
                                    perPage={filters.perPage}
                                    onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                                />
                            ) : (
                                <CrudTable<TrashRow>
                                    columns={[
                                        { key: 'id', label: 'No', sortable: false, render: (row) => row.no },
                                        {
                                            key: 'asset', label: 'Unit', sortable: false,
                                            render: (row) => (
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                    <small className="db-hint">{row.assetCode}{row.room ? ` · ${row.room}` : ''}</small>
                                                </span>
                                            ),
                                        },
                                    ]}
                                    rows={trash?.rows ?? []}
                                    page={{
                                        currentPage: trash?.currentPage ?? 1,
                                        lastPage: trash?.lastPage ?? 1,
                                        perPage: trash?.perPage ?? filters.perPage,
                                        total: trash?.total ?? 0,
                                    }}
                                    sort="id"
                                    direction="desc"
                                    onSort={() => undefined}
                                    search={search}
                                    onSearchChange={setSearch}
                                    onSearchSubmit={() => visit({ page: 1 }, true)}
                                    searchPlaceholder="Cari kode, nama…"
                                    selected={selected}
                                    onToggle={toggle}
                                    onToggleAll={toggleAll}
                                    canDelete={can.delete}
                                    onBulkDelete={() => setPending({ kind: 'bulk-force' })}
                                    bulkLabel="Hapus permanen terpilih"
                                    canRestore={can.restore}
                                    onBulkRestore={() => setPending({ kind: 'bulk-restore' })}
                                    canUpdate={false}
                                    onRestoreRow={can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                    onDeleteRow={can.delete ? (row) => setPending({ kind: 'force', row }) : undefined}
                                    showActions={can.restore || can.delete}
                                    canCreate={false}
                                    createLabel="Tambah Aset"
                                    emptyText="Sampah kosong."
                                    onPage={(p) => visit({ page: p })}
                                    perPage={filters.perPage}
                                    onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                                />
                            )}
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

                <ImportModal
                    open={importOpen}
                    title="Impor Aset"
                    description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Satu baris gagal berarti file ditolak."
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

