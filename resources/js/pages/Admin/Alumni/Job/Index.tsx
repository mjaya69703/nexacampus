// Daftar lowongan kerja alumni — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { Briefcase, Building2, Clock, Eye, Plus, Trash2 } from 'lucide-react';
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
    title: string;
    companyName: string;
    partnerName: string | null;
    industry: string | null;
    jobType: string | null;
    jobTypeLabel: string | null;
    location: string | null;
    salaryRange: string | null;
    postedDate: string | null;
    deadlineDate: string | null;
    isExpired: boolean;
    isActive: boolean;
    createdAt: string | null;
    showUrl: string | null;
    editUrl: string | null;
    deleteUrl: string;
    restoreUrl: string;
    forceUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean; view: boolean };
    stats: { total: number; active: number; fullTime: number; internship: number; expired: number; trashed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        employer_partner_id: string;
        job_type: string;
        validity: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        mode: 'all' | 'trash';
        perPage: number;
    };
    partnerOptions: { id: number; name: string; industry: string | null; isActive: boolean }[];
    jobTypeOptions: Record<string, string>;
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

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus lowongan kerja?',
        message: (n) => `"${n}" dipindah ke sampah dan tidak tampil di bursa kerja alumni.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan lowongan kerja?',
        message: (n) => `"${n}" kembali tampil di daftar lowongan.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus lowongan terpilih?',
        message: (n) => `${n} lowongan dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan lowongan terpilih?',
        message: (n) => `${n} lowongan kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen lowongan terpilih?',
        message: (n) => `${n} lowongan dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function JobPostingIndex({ shell, can, stats, data, filters, partnerOptions, jobTypeOptions, urls }: Props) {
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

    const activeFilterCount = [
        filters.employer_partner_id,
        filters.job_type,
        filters.validity,
        filters.is_active,
    ].filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => visit({
        employer_partner_id: '',
        job_type: '',
        validity: '',
        is_active: '',
        page: 1,
    }, true);

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
    const copyName = pending && 'row' in pending ? pending.row.title : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Lowongan Kerja Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Briefcase}
                        eyebrow="Alumni"
                        title="Bursa Kerja & Lowongan"
                        description="Publikasikan peluang kerja, magang, dan rekrutmen dari mitra perusahaan maupun institusi eksternal untuk alumni."
                        badges={[`${stats.total} lowongan`, `${stats.active} aktif`]}
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Lowongan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik lowongan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total lowongan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Lowongan aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Building2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.fullTime}</span><span className="db-stat-label">Full-time</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{stats.internship}</span><span className="db-stat-label">Magang</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Clock size={20} /></span>
                            <div><span className="db-stat-num">{stats.expired}</span><span className="db-stat-label">Lewat deadline</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Lowongan Kerja</h2>
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
                                        key: 'title', label: 'Lowongan', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">{row.companyName}{row.industry ? ` · ${row.industry}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'company_name', label: 'Mitra', sortable: true,
                                        render: (row) => row.partnerName ?? <span className="db-hint">Non-mitra</span>,
                                    },
                                    {
                                        key: 'job_type', label: 'Tipe', sortable: true,
                                        render: (row) => row.jobTypeLabel
                                            ? <span className="db-badge gray">{row.jobTypeLabel}</span>
                                            : <span className="db-hint">-</span>,
                                    },
                                    {
                                        key: 'location', label: 'Lokasi', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.location ?? '-'}</b>
                                                <small className="db-hint">{row.salaryRange ?? 'Gaji tidak disebutkan'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'deadline_date', label: 'Deadline', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.deadlineDate ?? '-'}</b>
                                                <small className="db-hint">
                                                    {row.isExpired
                                                        ? <span className="db-badge red">Lewat</span>
                                                        : <span className="db-badge green">Terbuka</span>}
                                                    {' '}Posting {row.postedDate ?? '-'}
                                                </small>
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
                                searchPlaceholder="Cari judul, perusahaan, lokasi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.employer_partner_id}
                                            onChange={(e) => visit({ employer_partner_id: e.target.value, page: 1 }, true)}
                                            aria-label="Filter mitra"
                                        >
                                            <option value="">Semua mitra</option>
                                            {partnerOptions.map((partner) => (
                                                <option key={partner.id} value={partner.id}>{partner.name}{partner.isActive ? '' : ' (nonaktif)'}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.job_type}
                                            onChange={(e) => visit({ job_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tipe pekerjaan"
                                        >
                                            <option value="">Semua tipe</option>
                                            {Object.entries(jobTypeOptions).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.validity}
                                            onChange={(e) => visit({ validity: e.target.value, page: 1 }, true)}
                                            aria-label="Filter masa berlaku"
                                        >
                                            <option value="">Semua masa berlaku</option>
                                            <option value="open">Masih terbuka</option>
                                            <option value="expired">Lewat deadline</option>
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
                                customActions={!isTrash && can.view ? (row) => (
                                    row.showUrl ? (
                                        <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail">
                                            <Eye size={13} />
                                        </a>
                                    ) : null
                                ) : undefined}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && (can.update || can.view)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Lowongan"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada lowongan yang cocok dengan filter.'}
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
