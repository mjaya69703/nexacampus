// Daftar berita — memakai kit CRUD shared (pola menu academic).
import { Head, router } from '@inertiajs/react';
import { Eye, Image, Newspaper, Plus, Trash2, Upload } from 'lucide-react';
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
    id: number; no: number; title: string; slug: string; category: string | null;
    isPublished: boolean; publishedAt: string | null; hasImage: boolean;
    createdAt: string | null; editUrl: string | null; showUrl: string; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean; view: boolean };
    stats: { total: number; published: number; draft: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; category: string | number; is_published: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    categoryOptions: { id: number; name: string }[];
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

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus berita?',
        message: (n) => `"${n}" dipindah ke sampah dan bisa dipulihkan. Gambar sampul ikut terhapus.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan berita?',
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
    'bulk-delete': {
        title: () => 'Hapus berita terpilih?',
        message: (n) => `${n} berita dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan berita terpilih?',
        message: (n) => `${n} berita kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen berita terpilih?',
        message: (n) => `${n} berita dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function NewsIndex({ shell, can, stats, data, filters, categoryOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount = (String(filters.category) !== '' ? 1 : 0) + (filters.is_published !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', category: '', is_published: '', page: 1 } as never, '', { page: 1 }), {
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

    const togglePublished = (row: Row) => {
        router.post(row.toggleUrl, { is_published: !row.isPublished }, { preserveScroll: true });
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
    const copyName = pending && 'row' in pending ? pending.row.title : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Berita · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Newspaper}
                        eyebrow="Publikasi"
                        title="Berita"
                        description="Kelola berita kampus: kategori, gambar sampul, dan status publikasi."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tulis Berita</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} berita berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik berita">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Newspaper size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total berita</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Newspaper size={20} /></span>
                            <div><span className="db-stat-num">{stats.published}</span><span className="db-stat-label">Published</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Newspaper size={20} /></span>
                            <div><span className="db-stat-num">{stats.draft}</span><span className="db-stat-label">Draft</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Berita</h2>
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
                                        key: 'title', label: 'Judul', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">{row.slug}{row.category ? ` · ${row.category}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_published', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isPublished ? 'Jadikan draft' : 'Publish'}>
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isPublished}
                                                        onChange={() => togglePublished(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Published' : 'Draft'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Published' : 'Draft'}</span>
                                            )
                                        ),
                                    },
                                    { key: 'published_at', label: 'Dipublikasi', sortable: true, render: (row) => row.publishedAt ?? '-' },
                                    {
                                        key: 'image', label: 'Sampul', sortable: false,
                                        render: (row) => row.hasImage
                                            ? <span className="db-badge green"><Image size={11} /> Ada</span>
                                            : <span className="db-hint">—</span>,
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
                                searchPlaceholder="Cari judul, slug…"
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
                                            value={filters.is_published}
                                            onChange={(e) => visit({ is_published: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Published + Draft</option>
                                            <option value="1">Published</option>
                                            <option value="0">Draft</option>
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
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Pratinjau">
                                                <Eye size={14} />
                                            </a>
                                        )}
                                    </>
                                )}
                                showActions={(!isTrash && (can.update || can.view)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tulis Berita"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada berita yang cocok dengan filter.'}
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
                    title="Impor Berita"
                    description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Gambar sampul tidak ikut diimpor. Satu baris gagal berarti file ditolak."
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
