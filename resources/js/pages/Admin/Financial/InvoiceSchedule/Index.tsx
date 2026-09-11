// Daftar jadwal penerbitan tagihan — kit CRUD (eksekusi manual, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { CalendarClock, Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; kind: string; kindText: string;
    mode: string; modeText: string; year: string | null; semester: number | null;
    publishAt: string | null; status: string; statusTone: string; statusText: string;
    isActive: boolean; created: number; invoices: number; canRun: boolean;
    editUrl: string; deleteUrl: string; runUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; run: boolean };
    stats: { total: number; pending: number; completed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; kind: string; mode: string; status: string; active: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    kinds: string[];
    modes: string[];
    statuses: string[];
    urls: {
        index: string; create: string; store: string; export: string; bulkDestroy: string;
    };
};

type Pending = { kind: 'delete' | 'run'; row: Row } | { kind: 'bulk-delete' } | null;

const KIND_TEXT: Record<string, string> = { tuition: 'SPP / Kuliah', custom: 'Kustom' };
const MODE_TEXT: Record<string, string> = { active_students: 'Semua Aktif', selected_students: 'Terpilih', single: 'Satu Mhs' };
const STATUS_TEXT: Record<string, string> = { pending: 'Menunggu', running: 'Diproses', completed: 'Selesai', failed: 'Gagal', cancelled: 'Batal' };

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function InvoiceScheduleIndex({ shell, can, stats, data, filters, kinds, modes, statuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
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

    const activeFilterCount = [filters.kind, filters.mode, filters.status, filters.active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ kind: '', mode: '', status: '', active: '', page: 1 }, true);
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
        } else if (pending.kind === 'run') {
            router.post(pending.row.runUrl, {}, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const modal = pending?.kind === 'run'
        ? { title: 'Eksekusi jadwal sekarang?', message: 'Invoice diterbitkan untuk seluruh target jadwal ini. Baris yang sudah ada dilewati.', confirm: 'Ya, eksekusi' }
        : pending?.kind === 'bulk-delete'
            ? { title: `Hapus ${selected.length} jadwal?`, message: 'Jadwal terpilih dipindahkan ke sampah. Invoice yang sudah terbit tidak ikut terhapus.', confirm: 'Ya, hapus' }
            : { title: 'Hapus jadwal?', message: 'Jadwal dipindahkan ke sampah. Invoice yang sudah terbit tidak ikut terhapus.', confirm: 'Ya, hapus' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Jadwal Tagihan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Keuangan"
                        title="Jadwal Penerbitan Tagihan"
                        description="Otomatisasi invoice massal per agenda. Eksekusi manual tersedia untuk jadwal pending."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Jadwal</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik jadwal">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total jadwal</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.pending}</span><span className="db-stat-label">Menunggu</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.completed}</span><span className="db-stat-label">Selesai</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Jadwal</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'name', label: 'Jadwal', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.kindText} · {row.modeText}{row.year ? ` · ${row.year}` : ''}{row.semester ? ` · Smt ${row.semester}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'publish_at', label: 'Terbit', sortable: true,
                                        render: (row) => row.publishAt ?? '-',
                                    },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusText}</span>,
                                    },
                                    {
                                        key: 'created_count', label: 'Hasil', sortable: false, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.created} terbit</b>
                                                <small className="db-hint">{row.invoices} invoice · {row.isActive ? 'aktif' : 'nonaktif'}</small>
                                            </span>
                                        ),
                                    },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama jadwal…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.kind}
                                            onChange={(e) => visit({ kind: e.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis"
                                        >
                                            <option value="">Semua jenis</option>
                                            {kinds.map((k) => <option key={k} value={k}>{KIND_TEXT[k] ?? k}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.mode}
                                            onChange={(e) => visit({ mode: e.target.value, page: 1 }, true)}
                                            aria-label="Filter target"
                                        >
                                            <option value="">Semua target</option>
                                            {modes.map((m) => <option key={m} value={m}>{MODE_TEXT[m] ?? m}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((s) => <option key={s} value={s}>{STATUS_TEXT[s] ?? s}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.active}
                                            onChange={(e) => visit({ active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter keaktifan"
                                        >
                                            <option value="">Aktif + nonaktif</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                customActions={(row) => (
                                    <>
                                        {can.update && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                        {can.run && row.canRun && (
                                            <button
                                                type="button"
                                                className="db-btn ghost sm"
                                                title="Eksekusi sekarang"
                                                onClick={() => setPending({ kind: 'run', row })}
                                            >
                                                <Play size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Buat Jadwal"
                                exportHref={exportHref}
                                emptyText="Belum ada jadwal yang cocok dengan filter."
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
                    danger={pending?.kind !== 'run'}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
