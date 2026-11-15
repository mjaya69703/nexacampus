// Daftar peran — memakai kit CRUD shared (pola halaman permission).
import { Head, router } from '@inertiajs/react';
import { Globe, KeyRound, Plus, Trash2, Upload, UserCog } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; guard: string; userCount: number;
    createdAt: string | null; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean };
    stats: { total: number; web: number; permissions: number; assigned: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; guard: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
        importTemplate: string; importSubmit: string;
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
        title: () => 'Hapus role?',
        message: (n, used) => `"${n}" dipindah ke sampah dan bisa dipulihkan.${used ? ' Role ini masih memiliki user — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan role?',
        message: (n) => `"${n}" kembali aktif beserta relasi permission-nya.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n, used) => `"${n}" dihapus selamanya dan tidak bisa dipulihkan.${used ? ' Role ini masih memiliki user — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus role terpilih?',
        message: (n) => `${n} role dipindah ke sampah. Yang masih memiliki user akan dilewati.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan role terpilih?',
        message: (n) => `${n} role kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen role terpilih?',
        message: (n) => `${n} role dihapus selamanya. Yang masih memiliki user akan dilewati.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function RoleIndex({ shell, can, stats, data, filters, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
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
    const copyUsed = pending && 'row' in pending ? pending.row.userCount > 0 : false;

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Peran · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UserCog}
                        eyebrow="Manajemen akses"
                        title="Peran"
                        description="Kelola role yang menjadi lapisan utama kontrol akses pengguna, termasuk relasi ke permission yang dimilikinya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Peran</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} role berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik peran">
                        <div className="db-stat">
                            <span className="db-stat-icon"><UserCog size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total peran</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Globe size={20} /></span>
                            <div><span className="db-stat-num">{stats.web}</span><span className="db-stat-label">Guard web</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><KeyRound size={20} /></span>
                            <div><span className="db-stat-num">{stats.permissions}</span><span className="db-stat-label">Total permission</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Peran</h2>
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
                                    { key: 'name', label: 'Nama peran', sortable: true, render: (row) => <span className="crud-name">{row.name}</span> },
                                    { key: 'guard_name', label: 'Guard', sortable: true, render: (row) => <span className={`db-badge ${row.guard === 'api' ? 'amber' : ''}`.trim()}>{row.guard}</span> },
                                    {
                                        key: 'user_count', label: 'Pengguna', sortable: true, align: 'right',
                                        render: (row) => <span className={`db-badge ${row.userCount > 0 ? 'green' : 'gray'}`}>{row.userCount} user</span>,
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
                                searchPlaceholder="Cari nama peran…"
                                filterBar={(
                                    <select
                                        className="db-input"
                                        value={filters.guard}
                                        onChange={(e) => visit({ guard: e.target.value, page: 1 }, true)}
                                        aria-label="Filter guard"
                                    >
                                        <option value="">Semua guard</option>
                                        <option value="web">web</option>
                                        <option value="api">api</option>
                                    </select>
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
                                createLabel="Tambah Peran"
                                exportHref={exportHref}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong. Role yang dihapus bisa dipulihkan dari sini.' : 'Belum ada peran yang cocok dengan filter.'}
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
                title="Impor Peran"
                description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Tulis permission by nama dipisah koma. Satu baris gagal berarti file ditolak."
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
