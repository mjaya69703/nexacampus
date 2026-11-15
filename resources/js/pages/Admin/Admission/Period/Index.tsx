// Daftar periode & gelombang PMB — memakai kit CRUD shared.
import { Head, router } from '@inertiajs/react';
import { CalendarRange, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; code: string; academicYear: string;
    wave: number; opensAt: string | null; closesAt: string | null;
    applicationsCount: number; requirementsCount: number;
    isActive: boolean; isPublished: boolean; createdAt: string | null;
    editUrl: string | null; deleteUrl: string; restoreUrl: string;
    forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; published: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; academic_year_id: string; is_active: string; is_published: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    yearOptions: { id: number; name: string; year: number }[];
    urls: {
        index: string; create: string; export: string;
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
        title: () => 'Hapus periode PMB?',
        message: (n) => `"${n}" dipindah ke sampah. Periode yang sudah punya pendaftar tidak bisa dihapus.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan periode PMB?',
        message: (n) => `"${n}" kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya beserta data terkait yang menggantung.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus periode terpilih?',
        message: (n) => `${n} periode dipindah ke sampah (yang sudah punya pendaftar dilewati).`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan periode terpilih?',
        message: (n) => `${n} periode kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen periode terpilih?',
        message: (n) => `${n} periode dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function AdmissionPeriodIndex({ shell, can, stats, data, filters, yearOptions, urls }: Props) {
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

    const activeFilterCount = [filters.academic_year_id, filters.is_active, filters.is_published]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ academic_year_id: '', is_active: '', is_published: '', page: 1 }, true);
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

    const toggleFlag = (row: Row, field: 'is_active' | 'is_published') => {
        router.post(
            row.toggleUrl,
            { field, value: field === 'is_active' ? !row.isActive : !row.isPublished },
            { preserveScroll: true },
        );
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
            <Head title={`Daftar Periode PMB · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarRange}
                        eyebrow="Admission"
                        title="Periode & Gelombang PMB"
                        description="Atur jadwal pembukaan pendaftaran, gelombang, status publikasi, dan syarat dokumen calon mahasiswa."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Periode</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik periode PMB">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarRange size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total periode</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarRange size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarRange size={20} /></span>
                            <div><span className="db-stat-num">{stats.published}</span><span className="db-stat-label">Terpublikasi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Periode PMB</h2>
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
                                        key: 'name', label: 'Periode', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'wave', label: 'Tahun / Gelombang', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.academicYear}</b>
                                                <small className="db-hint">Gelombang {row.wave}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'opens_at', label: 'Jadwal', sortable: true,
                                        render: (row) => `${row.opensAt ?? '-'} → ${row.closesAt ?? '-'}`,
                                    },
                                    {
                                        key: 'applications', label: 'Pendaftar',
                                        render: (row) => (
                                            <span>
                                                <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{row.applicationsCount}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.requirementsCount} syarat dokumen</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_active', label: 'Aktif',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status aktif">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleFlag(row, 'is_active')}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                            )
                                        ),
                                    },
                                    {
                                        key: 'is_published', label: 'Publikasi',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status publikasi">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isPublished}
                                                        onChange={() => toggleFlag(row, 'is_published')}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Published' : 'Draft'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Published' : 'Draft'}</span>
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
                                            value={filters.academic_year_id}
                                            onChange={(e) => visit({ academic_year_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun akademik"
                                        >
                                            <option value="">Semua tahun akademik</option>
                                            {yearOptions.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(e) => visit({ is_active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status aktif"
                                        >
                                            <option value="">Semua status aktif</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_published}
                                            onChange={(e) => visit({ is_published: e.target.value, page: 1 }, true)}
                                            aria-label="Filter publikasi"
                                        >
                                            <option value="">Semua publikasi</option>
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
                                showActions={(!isTrash && can.update) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Periode"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada periode yang cocok dengan filter.'}
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
