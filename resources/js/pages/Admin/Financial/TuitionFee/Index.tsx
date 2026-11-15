// Daftar tarif biaya kuliah — kit CRUD (sampah/restore, toggle aktif, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Plus, Trash2, Upload, Wallet } from 'lucide-react';
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
    id: number; no: number; year: string; program: string; semester: number | null;
    baseFee: string; total: string; deadline: string | null; isActive: boolean;
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
        q: string; year: string; program: string; semester: string; active: string;
        mode: 'all' | 'trash'; sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    yearOptions: { id: number; name: string }[];
    programOptions: { id: number; name: string }[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; store: string; export: string; exportPdf: string;
        importTemplate: string; importSubmit: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
    };
};

type Pending = { kind: 'delete' | 'restore' | 'force'; row: Row } | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' } | null;

const MODAL_COPY: Record<string, { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus tarif?',
        message: (n) => `Tarif "${n}" dipindahkan ke sampah dan bisa dipulihkan.`,
        confirm: 'Ya, hapus', danger: true,
    },
    restore: {
        title: () => 'Pulihkan tarif?',
        message: (n) => `Tarif "${n}" dikembalikan ke daftar aktif.`,
        confirm: 'Ya, pulihkan', danger: false,
    },
    force: {
        title: () => 'Hapus permanen tarif?',
        message: (n) => `Tarif "${n}" dihapus permanen dan tidak bisa dipulihkan.`,
        confirm: 'Ya, hapus permanen', danger: true,
    },
    'bulk-delete': {
        title: (n) => `Hapus ${n} tarif?`,
        message: () => 'Tarif terpilih dipindahkan ke sampah dan bisa dipulihkan.',
        confirm: 'Ya, hapus', danger: true,
    },
    'bulk-restore': {
        title: (n) => `Pulihkan ${n} tarif?`,
        message: () => 'Tarif terpilih dikembalikan ke daftar aktif.',
        confirm: 'Ya, pulihkan', danger: false,
    },
    'bulk-force': {
        title: (n) => `Hapus permanen ${n} tarif?`,
        message: () => 'Tarif terpilih dihapus permanen dan tidak bisa dipulihkan.',
        confirm: 'Ya, hapus permanen', danger: true,
    },
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function TuitionFeeIndex({ shell, can, stats, data, filters, yearOptions, programOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [semester, setSemester] = useState(filters.semester);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

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

    const activeFilterCount = [filters.year, filters.program, semester, filters.active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setSemester('');
        visit({ year: '', program: '', semester: '', active: '', page: 1 }, true);
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
    const copyName = pending && 'row' in pending
        ? `${pending.row.year} · ${pending.row.program}`
        : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Tarif Biaya Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wallet}
                        eyebrow="Keuangan"
                        title="Tarif Biaya Kuliah"
                        description="Tarif per tahun, prodi, dan semester. Kombinasi ketiganya unik — dipakai sebagai dasar pembuatan tagihan."
                        actions={can.create && !isTrash ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Tarif</a>
                        ) : undefined}
                    />

                    <ImportResultBanner result={importResult} successText={(n) => `${n} data berhasil diimpor.`} failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`} />

                    <section className="db-stats" aria-label="Statistik tarif">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Wallet size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total tarif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Wallet size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Tarif</h2>
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
                                        key: 'year', label: 'Tahun / Prodi', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.year}</b>
                                                <small className="db-hint">{row.program}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'semester', label: 'Smt', render: (row) => row.semester ?? '-' },
                                    {
                                        key: 'base_fee', label: 'SPP / Total', sortable: true, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.total}</b>
                                                <small className="db-hint">SPP {row.baseFee}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'payment_deadline', label: 'Deadline', sortable: true, render: (row) => row.deadline ?? '-' },
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
                                searchPlaceholder="Cari tahun, prodi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.year}
                                            onChange={(e) => visit({ year: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun"
                                        >
                                            <option value="">Semua tahun</option>
                                            {yearOptions.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.program}
                                            onChange={(e) => visit({ program: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prodi"
                                        >
                                            <option value="">Semua prodi</option>
                                            {programOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        <input
                                            className="db-input"
                                            value={semester}
                                            inputMode="numeric"
                                            placeholder="Semester…"
                                            onChange={(e) => setSemester(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ semester, page: 1 }, true); } }}
                                            aria-label="Filter semester"
                                        />
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
                                canCreate={false}
                                createLabel="Tambah Tarif"
                                exportHref={exportHref}
                                exportExtra={[{ label: 'PDF laporan', href: urls.exportPdf }]}
                                extraActions={can.create ? (<button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}><Upload size={14} /> Import</button>) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada tarif yang cocok dengan filter.'}
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

                <ImportModal
                    open={importOpen}
                    title="Impor Tarif Biaya"
                    description="Unggah file sesuai template untuk menambah banyak tarif sekaligus. Satu baris gagal berarti file ditolak."
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
