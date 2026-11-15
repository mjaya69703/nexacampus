// Daftar fakultas — memakai kit CRUD shared (pola menu access/system).
import { Head, router } from '@inertiajs/react';
import { GraduationCap, Plus, Trash2, University, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; code: string; shortName: string | null;
    programCount: number; isActive: boolean; desc: string | null;
    createdAt: string | null; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; programs: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: { q: string; is_active: string; sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number };
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
        title: () => 'Hapus fakultas?',
        message: (n, used) => `"${n}" dipindah ke sampah dan bisa dipulihkan.${used ? ' Fakultas ini masih memiliki prodi — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan fakultas?',
        message: (n) => `"${n}" kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n, used) => `"${n}" dihapus selamanya dan tidak bisa dipulihkan.${used ? ' Fakultas ini masih memiliki prodi — penghapusan akan ditolak.' : ''}`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus fakultas terpilih?',
        message: (n) => `${n} fakultas dipindah ke sampah. Yang masih memiliki prodi akan dilewati.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan fakultas terpilih?',
        message: (n) => `${n} fakultas kembali aktif.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen fakultas terpilih?',
        message: (n) => `${n} fakultas dihapus selamanya. Yang masih memiliki prodi akan dilewati.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function FacultyIndex({ shell, can, stats, data, filters, importResult, urls }: Props) {
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
    const copyUsed = pending && 'row' in pending ? pending.row.programCount > 0 : false;

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Fakultas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={University}
                        eyebrow="Akademik"
                        title="Fakultas"
                        description="Kelola fakultas sebagai akar struktur akademik. Menonaktifkan fakultas ikut menonaktifkan seluruh prodinya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Fakultas</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} fakultas berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik fakultas">
                        <div className="db-stat">
                            <span className="db-stat-icon"><University size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total fakultas</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><University size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.programs}</span><span className="db-stat-label">Total prodi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Fakultas</h2>
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
                                        key: 'name', label: 'Fakultas', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}{row.shortName ? ` · ${row.shortName}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'study_programs_count', label: 'Prodi', sortable: false, align: 'right',
                                        render: (row) => <span className={`db-badge ${row.programCount > 0 ? 'green' : 'gray'}`}>{row.programCount} prodi</span>,
                                    },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isActive ? 'Nonaktifkan (+ prodi)' : 'Aktifkan'}>
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
                                searchPlaceholder="Cari nama, kode, singkatan…"
                                filterBar={(
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
                                createLabel="Tambah Fakultas"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada fakultas yang cocok dengan filter.'}
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
                    title="Impor Fakultas"
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
