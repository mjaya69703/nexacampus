// Daftar pengguna — memakai kit CRUD shared (pola halaman permission/role).
import { Head, router } from '@inertiajs/react';
import { Plus, Trash2, Upload, UserCheck, UserX, Users } from 'lucide-react';
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
    id: number; no: number; photo: string; name: string; username: string;
    email: string; phone: string | null; roles: string[]; gender: string | null;
    isActive: boolean; isSelf: boolean; createdAt: string | null;
    editUrl: string | null; deleteUrl: string; restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; inactive: number; withRole: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; role: string; gender: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    roleOptions: { id: number; name: string }[];
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

export default function UserIndex({ shell, can, stats, data, filters, roleOptions, importResult, urls }: Props) {
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

    const activeFilterCount = [filters.role, filters.gender, filters.is_active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ role: '', gender: '', is_active: '', page: 1 }, true);
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

    const modal = (() => {
        if (!pending) return { title: '', message: '', confirm: 'Ya', danger: true };
        if (pending.kind === 'restore') {
            return { title: 'Pulihkan user?', message: `"${pending.row.name}" kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'bulk-restore') {
            return { title: 'Pulihkan user terpilih?', message: `${selected.length} user kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'force') {
            return {
                title: 'Hapus permanen?',
                message: pending.row.isSelf
                    ? 'Anda tidak bisa menghapus permanen akun sendiri.'
                    : `"${pending.row.name}" dihapus selamanya beserta profil terkaitnya.`,
                confirm: 'Ya, hapus permanen',
                danger: true,
            };
        }
        if (pending.kind === 'bulk-force') {
            const selfHit = data.rows.some((row) => row.isSelf && selected.includes(row.id));
            return {
                title: 'Hapus permanen user terpilih?',
                message: `${selected.length} user dihapus selamanya.${selfHit ? ' Akun Anda sendiri akan dilewati.' : ''}`,
                confirm: 'Ya, hapus permanen',
                danger: true,
            };
        }
        if (pending.kind === 'bulk-delete') {
            const selfHit = data.rows.some((row) => row.isSelf && selected.includes(row.id));
            return {
                title: 'Hapus user terpilih?',
                message: `${selected.length} user dipindah ke sampah.${selfHit ? ' Akun Anda sendiri akan dilewati.' : ''}`,
                confirm: 'Ya, hapus',
                danger: true,
            };
        }
        return {
            title: 'Hapus user?',
            message: pending.row.isSelf
                ? 'Anda tidak bisa menghapus akun sendiri.'
                : `"${pending.row.name}" dipindah ke sampah dan bisa dipulihkan.`,
            confirm: 'Ya, hapus',
            danger: true,
        };
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Pengguna · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Manajemen akses"
                        title="Pengguna"
                        description="Kelola akun pengguna, peran, status aktif, dan profil terkait mahasiswa maupun dosen."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Pengguna</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} user berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik pengguna">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total pengguna</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><UserCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><UserX size={20} /></span>
                            <div><span className="db-stat-num">{stats.inactive}</span><span className="db-stat-label">Nonaktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengguna</h2>
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
                                        key: 'first_name', label: 'Pengguna', sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 10 }}>
                                                <img src={row.photo} alt={row.name} width={34} height={34} style={{ borderRadius: '50%', objectFit: 'cover', flex: '0 0 auto' }} />
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}{row.isSelf ? ' (Anda)' : ''}</b>
                                                    <small className="db-hint">@{row.username}</small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'email', label: 'Kontak', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <span style={{ display: 'block', fontSize: 12.5 }}>{row.email}</span>
                                                <small className="db-hint">{row.phone ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'roles', label: 'Peran',
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 5 }}>
                                                {row.roles.length === 0 && <span className="db-badge gray">Tanpa peran</span>}
                                                {row.roles.map((role) => (
                                                    <span className="db-badge" key={role}>{role}</span>
                                                ))}
                                            </span>
                                        ),
                                    },
                                    { key: 'gender', label: 'Gender', render: (row) => row.gender ?? '-' },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash && !row.isSelf ? (
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
                                searchPlaceholder="Cari nama, username, email, HP, peran…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.role}
                                            onChange={(e) => visit({ role: e.target.value, page: 1 }, true)}
                                            aria-label="Filter peran"
                                        >
                                            <option value="">Semua peran</option>
                                            {roleOptions.map((role) => (
                                                <option key={role.id} value={role.id}>{role.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.gender}
                                            onChange={(e) => visit({ gender: e.target.value, page: 1 }, true)}
                                            aria-label="Filter gender"
                                        >
                                            <option value="">Semua gender</option>
                                            <option value="Laki-laki">Laki-laki</option>
                                            <option value="Perempuan">Perempuan</option>
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
                                showActions={(!isTrash && (can.update || can.delete)) || (isTrash && (can.restore || can.delete))}
                                canCreate={false}
                                createLabel="Tambah Pengguna"
                                exportHref={exportHref}
                                extraActions={!isTrash && can.create ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada pengguna yang cocok dengan filter.'}
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={modal.title}
                    message={modal.message}
                    confirmLabel={modal.confirm}
                    danger={modal.danger}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                <ImportModal
                    open={importOpen}
                    title="Impor Pengguna"
                    description="Unggah file Excel/CSV (maks 10 MB, 500 baris). Seluruh baris divalidasi dulu — satu baris gagal berarti file ditolak dan tidak ada data yang disimpan."
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
