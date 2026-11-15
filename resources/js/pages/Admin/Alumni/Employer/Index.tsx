// Daftar mitra perusahaan alumni — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { Briefcase, Building2, Handshake, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    industry: string | null;
    city: string | null;
    province: string | null;
    website: string | null;
    contactPerson: string | null;
    contactEmail: string | null;
    contactPhone: string | null;
    logoUrl: string | null;
    jobsCount: number;
    isActive: boolean;
    createdAt: string | null;
    editUrl: string | null;
    jobsUrl: string;
    deleteUrl: string;
    restoreUrl: string;
    forceUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean };
    stats: { total: number; active: number; withJobs: number; jobs: number; trashed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        industry: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        mode: 'all' | 'trash';
        perPage: number;
    };
    industryOptions: string[];
    urls: {
        index: string;
        create: string;
        export: string;
        bulkDestroy: string;
        bulkRestore: string;
        bulkForceDestroy: string;
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

function initials(name: string) {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus mitra perusahaan?',
        message: (n) => `"${n}" dipindah ke sampah. Lowongan yang tertaut tetap tersimpan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan mitra perusahaan?',
        message: (n) => `"${n}" kembali tampil di daftar mitra.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya beserta logonya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus mitra terpilih?',
        message: (n) => `${n} mitra perusahaan dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan mitra terpilih?',
        message: (n) => `${n} mitra perusahaan kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen mitra terpilih?',
        message: (n) => `${n} mitra perusahaan dihapus selamanya beserta logonya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function EmployerPartnerIndex({ shell, can, stats, data, filters, industryOptions, urls }: Props) {
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

    const activeFilterCount = [filters.industry, filters.is_active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => visit({ industry: '', is_active: '', page: 1 }, true);

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

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.name : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Mitra Perusahaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Handshake}
                        eyebrow="Alumni"
                        title="Mitra Perusahaan & Industri"
                        description="Kelola perusahaan mitra, kontak rekrutmen, dan keterhubungannya dengan bursa lowongan kerja alumni."
                        badges={[`${stats.total} mitra`, `${stats.active} aktif`]}
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Mitra</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik mitra">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Building2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total mitra</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Handshake size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Mitra aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.withJobs}</span><span className="db-stat-label">Punya lowongan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.jobs}</span><span className="db-stat-label">Total lowongan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Mitra Perusahaan</h2>
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
                                        key: 'name', label: 'Mitra', sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                {row.logoUrl ? (
                                                    <img
                                                        src={row.logoUrl}
                                                        alt={row.name}
                                                        style={{ width: 34, height: 34, borderRadius: 8, objectFit: 'contain', background: 'var(--db-soft)', flex: '0 0 auto' }}
                                                    />
                                                ) : (
                                                    <span
                                                        aria-hidden
                                                        style={{
                                                            width: 34, height: 34, borderRadius: 8, flex: '0 0 auto',
                                                            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                            background: 'var(--db-brand-soft)', color: 'var(--db-brand)',
                                                            fontSize: 11, fontWeight: 800,
                                                        }}
                                                    >
                                                        {initials(row.name)}
                                                    </span>
                                                )}
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                    <small className="db-hint">{row.industry ?? 'Sektor umum'}</small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'city', label: 'Kota / Provinsi', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.city ?? '-'}</b>
                                                <small className="db-hint">{row.province ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'contact_person', label: 'Kontak', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.contactPerson ?? '-'}</b>
                                                <small className="db-hint">{row.contactEmail ?? row.contactPhone ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'job_postings_count', label: 'Lowongan', sortable: true,
                                        render: (row) => (
                                            <a className="db-btn ghost sm" href={row.jobsUrl} title="Lihat lowongan mitra ini">
                                                <Briefcase size={13} /> {row.jobsCount}
                                            </a>
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
                                searchPlaceholder="Cari nama, industri, kota, kontak…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.industry}
                                            onChange={(e) => visit({ industry: e.target.value, page: 1 }, true)}
                                            aria-label="Filter industri"
                                        >
                                            <option value="">Semua industri</option>
                                            {industryOptions.map((industry) => <option key={industry} value={industry}>{industry}</option>)}
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
                                createLabel="Tambah Mitra"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada mitra perusahaan yang cocok dengan filter.'}
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
