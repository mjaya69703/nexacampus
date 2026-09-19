// Daftar pengumuman — memakai kit CRUD shared (pola menu academic).
import { Head, router } from '@inertiajs/react';
import { Eye, Megaphone, Paperclip, Pin, Plus, Send, Trash2, Undo2, Upload } from 'lucide-react';
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
    id: number; no: number; title: string;
    targetType: string; targetLabel: string;
    priority: string; priorityLabel: string;
    isPinned: boolean; isPublished: boolean; isScheduled: boolean;
    publishedAt: string | null; creatorName: string; readsCount: number;
    hasAttachment: boolean; createdAt: string | null;
    editUrl: string | null; showUrl: string; deleteUrl: string;
    restoreUrl: string; forceUrl: string; togglePinUrl: string;
    publishUrl: string; unpublishUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean; publish: boolean; view: boolean };
    stats: { total: number; published: number; draft: number; pinned: number; reads: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; target_type: string; priority: string; is_published: string; is_pinned: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    targetTypeOptions: { id: string; name: string }[];
    priorityOptions: { id: string; name: string }[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string; importSubmit: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string; bulkPublish: string; searchTargets: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force' | 'publish' | 'unpublish'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' | 'bulk-publish' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus pengumuman?',
        message: (n) => `"${n}" dipindah ke sampah dan bisa dipulihkan. Lampiran ikut terhapus.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan pengumuman?',
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
    publish: {
        title: () => 'Publish pengumuman?',
        message: (n) => `"${n}" langsung terlihat penerima sesuai target.`,
        confirm: 'Ya, publish',
        danger: false,
    },
    unpublish: {
        title: () => 'Batalkan publish?',
        message: (n) => `"${n}" kembali ke draft dan disembunyikan dari penerima.`,
        confirm: 'Ya, batalkan',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus pengumuman terpilih?',
        message: (n) => `${n} pengumuman dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan pengumuman terpilih?',
        message: (n) => `${n} pengumuman kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen pengumuman terpilih?',
        message: (n) => `${n} pengumuman dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-publish': {
        title: () => 'Publish pengumuman terpilih?',
        message: (n) => `${n} pengumuman diterbitkan. Yang sudah terbit dilewati.`,
        confirm: 'Ya, publish',
        danger: false,
    },
};

export default function AnnouncementIndex({ shell, can, stats, data, filters, targetTypeOptions, priorityOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const activeFilterCount =
        (filters.target_type !== '' ? 1 : 0) +
        (filters.priority !== '' ? 1 : 0) +
        (filters.is_published !== '' ? 1 : 0) +
        (filters.is_pinned !== '' ? 1 : 0);

    const resetFilters = () => {
        setSearch('');
        router.get(urls.index, buildQuery({ ...filters, q: '', target_type: '', priority: '', is_published: '', is_pinned: '', page: 1 } as never, '', { page: 1 }), {
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

    const togglePin = (row: Row) => {
        router.post(row.togglePinUrl, { is_pinned: !row.isPinned }, { preserveScroll: true });
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
            case 'publish':
                router.post(pending.row.publishUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'unpublish':
                router.post(pending.row.unpublishUrl, {}, { preserveScroll: true, onFinish: done });
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
            case 'bulk-publish':
                router.post(urls.bulkPublish, { ids: selected }, { preserveScroll: true, onFinish: done });
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
            <Head title={`Daftar Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi"
                        title="Pengumuman"
                        description="Kelola pengumuman kampus: target penerima, prioritas, pin, dan status publikasi. Publish dikawal izin khusus."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Pengumuman</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} pengumuman berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik pengumuman">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Megaphone size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Send size={20} /></span>
                            <div><span className="db-stat-num">{stats.published}</span><span className="db-stat-label">Published</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Pin size={20} /></span>
                            <div><span className="db-stat-num">{stats.pinned}</span><span className="db-stat-label">Pin</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Eye size={20} /></span>
                            <div><span className="db-stat-num">{stats.reads}</span><span className="db-stat-label">Dibaca</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengumuman</h2>
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
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>
                                                    {row.isPinned && <Pin size={12} style={{ display: 'inline', marginRight: 5 }} />}
                                                    {row.title}
                                                </b>
                                                <small className="db-hint">{row.targetLabel} · oleh {row.creatorName}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'priority', label: 'Prioritas', sortable: false,
                                        render: (row) => <span className="db-badge gray">{row.priorityLabel}</span>,
                                    },
                                    {
                                        key: 'is_published', label: 'Status',
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>
                                                    {row.isPublished ? 'Published' : 'Draft'}
                                                </span>
                                                {row.isScheduled && (
                                                    <small className="db-hint" style={{ display: 'block', marginTop: 4 }}>Terjadwal</small>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'published_at', label: 'Dipublikasi', sortable: true,
                                        render: (row) => row.publishedAt ?? '-',
                                    },
                                    {
                                        key: 'reads', label: 'Dibaca', sortable: false, align: 'right',
                                        render: (row) => <span className="db-badge gray">{row.readsCount}x</span>,
                                    },
                                    {
                                        key: 'attachment', label: 'Lampiran', sortable: false,
                                        render: (row) => row.hasAttachment
                                            ? <span className="db-badge green"><Paperclip size={11} /> Ada</span>
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
                                searchPlaceholder="Cari judul, pembuat…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.target_type}
                                            onChange={(e) => visit({ target_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter target"
                                        >
                                            <option value="">Semua target</option>
                                            {targetTypeOptions.map((opt) => (
                                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.priority}
                                            onChange={(e) => visit({ priority: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prioritas"
                                        >
                                            <option value="">Semua prioritas</option>
                                            {priorityOptions.map((opt) => (
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
                                        <select
                                            className="db-input"
                                            value={filters.is_pinned}
                                            onChange={(e) => visit({ is_pinned: e.target.value, page: 1 }, true)}
                                            aria-label="Filter pin"
                                        >
                                            <option value="">Pin + biasa</option>
                                            <option value="1">Di-pin</option>
                                            <option value="0">Biasa</option>
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
                                        {!isTrash && can.toggle && (
                                            <button
                                                className={`db-btn ghost sm${row.isPinned ? ' active' : ''}`}
                                                type="button"
                                                title={row.isPinned ? 'Lepas pin' : 'Pin ke atas'}
                                                onClick={() => togglePin(row)}
                                            >
                                                <Pin size={14} />
                                            </button>
                                        )}
                                        {!isTrash && can.publish && !row.isPublished && (
                                            <button
                                                className="db-btn ghost sm"
                                                type="button"
                                                title="Publish"
                                                onClick={() => setPending({ kind: 'publish', row })}
                                            >
                                                <Send size={14} />
                                            </button>
                                        )}
                                        {!isTrash && can.publish && row.isPublished && (
                                            <button
                                                className="db-btn ghost sm"
                                                type="button"
                                                title="Batalkan publish"
                                                onClick={() => setPending({ kind: 'unpublish', row })}
                                            >
                                                <Undo2 size={14} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions={(!isTrash && (can.update || can.publish || can.view)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Buat Pengumuman"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={(
                                    <>
                                        {!isTrash && can.publish && selected.length > 0 && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setPending({ kind: 'bulk-publish' })}>
                                                <Send size={14} /> Publish terpilih
                                            </button>
                                        )}
                                        {!isTrash && can.create && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                                <Upload size={14} /> Import
                                            </button>
                                        )}
                                    </>
                                )}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada pengumuman yang cocok dengan filter.'}
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
                    title="Impor Pengumuman"
                    description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Lampiran tidak ikut diimpor. Satu baris gagal berarti file ditolak."
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
