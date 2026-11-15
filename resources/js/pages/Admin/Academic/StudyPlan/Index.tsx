// Daftar KRS — kit CRUD (hapus permanen, tanpa sampah, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { ClipboardList, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    year: string | null; semester: number | null; status: string; statusTone: string;
    courses: number; credits: number; createdAt: string | null;
    showUrl: string; editUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; submitted: number; approved: number; draft: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; year: string; program: string; semester: string; status: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    yearOptions: { id: number; name: string }[];
    programOptions: { id: number; name: string }[];
    statuses: string[];
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string;
        bulkDestroy: string;
    };
};

type Pending = { kind: 'delete' | 'bulk-delete'; row?: Row } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudyPlanIndex({ shell, can, stats, data, filters, yearOptions, programOptions, statuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const visitNim = () => visit({ nim, page: 1 }, true);

    const activeFilterCount = [nim, filters.year, filters.program, filters.semester, filters.status]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNim('');
        visit({ nim: '', year: '', program: '', semester: '', status: '', page: 1 }, true);
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

        if (pending.kind === 'delete' && pending.row) {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const modal = pending?.kind === 'bulk-delete'
        ? { title: 'Hapus permanen terpilih?', message: `${selected.length} KRS beserta detailnya dihapus permanen dan tidak bisa dipulihkan.`, confirm: 'Ya, hapus permanen' }
        : { title: 'Hapus permanen KRS?', message: 'KRS beserta seluruh detail MK-nya dihapus permanen dan tidak bisa dipulihkan.', confirm: 'Ya, hapus permanen' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar KRS · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik"
                        title="KRS Mahasiswa"
                        description="Rencana studi per tahun. Hapus bersifat permanen beserta detail MK-nya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat KRS</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik KRS">
                        <div className="db-stat">
                            <span className="db-stat-icon"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total KRS</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.submitted}</span><span className="db-stat-label">Diajukan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.approved}</span><span className="db-stat-label">Disetujui</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><ClipboardList size={20} /></span>
                            <div><span className="db-stat-num">{stats.draft}</span><span className="db-stat-label">Draft</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel KRS</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'student', label: 'Mahasiswa', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim ?? '-'} · {row.program ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'year', label: 'Tahun', render: (row) => `${row.year ?? '-'}${row.semester ? ` · Smt ${row.semester}` : ''}` },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.status}</span>,
                                    },
                                    {
                                        key: 'courses', label: 'MK / SKS', align: 'right',
                                        render: (row) => <span className="db-badge green">{row.courses} MK · {row.credits} SKS</span>,
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
                                searchPlaceholder="Cari nama, email…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <input
                                            className="db-input"
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visitNim(); } }}
                                            aria-label="Filter NIM"
                                        />
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
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus permanen terpilih"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {can.update && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah + kelola MK">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Buat KRS"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                emptyText="Belum ada KRS yang cocok dengan filter."
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
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
