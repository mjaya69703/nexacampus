// Daftar kampanye tracer study — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { BarChart3, ChartBarBig, ClipboardList, Plus, Send, Trash2 } from 'lucide-react';
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
    academicYear: string | null;
    startDate: string | null;
    endDate: string | null;
    status: string;
    statusLabel: string;
    totalSent: number;
    totalResponded: number;
    responsesCount: number;
    responseRate: number;
    questionsCount: number;
    targetYears: number[];
    targetPrograms: string[];
    createdAt: string | null;
    showUrl: string | null;
    editUrl: string | null;
    deleteUrl: string;
    restoreUrl: string;
    forceUrl: string;
    activateUrl: string;
    responsesUrl: string;
    analyticsUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; activate: boolean; view: boolean; responses: boolean };
    stats: { total: number; draft: number; active: number; closed: number; responses: number; trashed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        status: string;
        academic_year_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        mode: 'all' | 'trash';
        perPage: number;
    };
    statusOptions: Record<string, string>;
    academicYearOptions: { id: number; name: string }[];
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
    | { kind: 'delete' | 'restore' | 'force' | 'activate'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined));
}

const STATUS_BADGE: Record<string, string> = {
    draft: 'gray',
    active: 'green',
    closed: 'amber',
};

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus kampanye tracer study?',
        message: (n) => `"${n}" dipindah ke sampah. Respon yang sudah masuk tetap tersimpan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan kampanye?',
        message: (n) => `"${n}" kembali tampil di daftar kampanye.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    activate: {
        title: () => 'Aktifkan kampanye?',
        message: (n) => `"${n}" akan dihitung sasaran alumninya dan dibuka untuk pengisian. Status draft tidak bisa dikembalikan.`,
        confirm: 'Ya, aktifkan',
        danger: false,
    },
    'bulk-delete': {
        title: () => 'Hapus kampanye terpilih?',
        message: (n) => `${n} kampanye dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan kampanye terpilih?',
        message: (n) => `${n} kampanye kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen kampanye terpilih?',
        message: (n) => `${n} kampanye dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

function targetSummary(row: Row) {
    const parts: string[] = [];
    if (row.targetYears.length > 0) parts.push(`Lulus ${row.targetYears.join(', ')}`);
    if (row.targetPrograms.length > 0) parts.push(row.targetPrograms.join(', '));
    return parts.length > 0 ? parts.join(' · ') : 'Semua alumni aktif';
}

export default function TracerStudyIndex({ shell, can, stats, data, filters, statusOptions, academicYearOptions, urls }: Props) {
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

    const activeFilterCount = [filters.status, filters.academic_year_id].filter((v) => v !== '').length;
    const resetFilters = () => visit({ status: '', academic_year_id: '', page: 1 }, true);

    const toggleSort = (key: string) => {
        visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 });
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
            case 'activate':
                router.post(pending.row.activateUrl, {}, { preserveScroll: true, onFinish: done });
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
            <Head title={`Tracer Study · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Alumni"
                        title="Kampanye Tracer Study"
                        description="Susun kuesioner, tentukan sasaran alumni, lalu pantau tingkat respon dan hasil analitiknya."
                        badges={[`${stats.total} kampanye`, `${stats.active} aktif`, `${stats.responses} respon`]}
                        actions={can.create ? <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kampanye</a> : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik tracer study">
                        <div className="db-stat">
                            <span className="db-stat-icon"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total kampanye</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.draft}</span><span className="db-stat-label">Draft</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Send size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><ChartBarBig size={20} /></span>
                            <div><span className="db-stat-num">{stats.closed}</span><span className="db-stat-label">Ditutup</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Kampanye</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button type="button" role="tab" aria-selected={!isTrash} className={`crud-tab${!isTrash ? ' active' : ''}`} onClick={() => visit({ mode: 'all', page: 1 }, true)}>
                                    Semua <span className="db-badge gray">{stats.total}</span>
                                </button>
                                <button type="button" role="tab" aria-selected={isTrash} className={`crud-tab${isTrash ? ' active' : ''}`} onClick={() => visit({ mode: 'trash', page: 1 }, true)}>
                                    <Trash2 size={13} /> Sampah <span className="db-badge gray">{stats.trashed}</span>
                                </button>
                            </div>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'title', label: 'Kampanye', sortable: true,
                                        render: (row) => (
                                            <span>
                                                {row.showUrl ? (
                                                    <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-brand)', fontWeight: 700, fontSize: 13 }}>{row.title}</a>
                                                ) : (
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                )}
                                                <small className="db-hint">{row.academicYear ?? 'Tanpa tahun akademik'} · {row.questionsCount} pertanyaan</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'start_date', label: 'Periode', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.startDate ?? '-'}</b>
                                                <small className="db-hint">s.d. {row.endDate ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status', sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', flexDirection: 'column', gap: 4 }}>
                                                <span className={`db-badge ${STATUS_BADGE[row.status] ?? 'gray'}`}>{row.statusLabel}</span>
                                                {can.activate && !isTrash && row.status === 'draft' && (
                                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending({ kind: 'activate', row })} title="Aktifkan kampanye">
                                                        <Send size={12} /> Aktifkan
                                                    </button>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'total_responded', label: 'Respon', sortable: true, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{row.responsesCount} / {row.totalSent}</b>
                                                <small className="db-hint">{row.responseRate}% response rate</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'target', label: 'Sasaran',
                                        render: (row) => <small className="db-hint" style={{ display: 'block', maxWidth: 240 }}>{targetSummary(row)}</small>,
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
                                searchPlaceholder="Cari judul atau deskripsi kampanye…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select className="db-input" value={filters.status} onChange={(e) => visit({ status: e.target.value, page: 1 }, true)} aria-label="Filter status">
                                            <option value="">Semua status</option>
                                            {Object.entries(statusOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.academic_year_id} onChange={(e) => visit({ academic_year_id: e.target.value, page: 1 }, true)} aria-label="Filter tahun akademik">
                                            <option value="">Semua tahun akademik</option>
                                            {academicYearOptions.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
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
                                    !isTrash ? (
                                        <>
                                            {row.showUrl && <a className="db-btn ghost sm" href={row.showUrl} title="Detail kampanye"><ClipboardList size={13} /></a>}
                                            {can.responses && <a className="db-btn ghost sm" href={row.responsesUrl} title="Lihat respon"><BarChart3 size={13} /></a>}
                                            {can.view && <a className="db-btn ghost sm" href={row.analyticsUrl} title="Analytics"><ChartBarBig size={13} /></a>}
                                        </>
                                    ) : null
                                )}
                                showActions={(!isTrash && (can.update || can.view || can.responses)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Kampanye"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada kampanye yang cocok dengan filter.'}
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
