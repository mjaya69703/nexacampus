// Daftar kebijakan bebas tanggungan — kit CRUD (hapus permanen, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; invoiceType: string; holdType: string;
    mode: string; modeTone: string; modeText: string; graceDays: string;
    isActive: boolean; holds: number; description: string | null;
    editUrl: string; deleteUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; toggle: boolean };
    stats: { total: number; active: number; blocking: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; invoice_type: string; hold_type: string; mode: string; active: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    invoiceTypes: { id: string; name: string }[];
    holdTypes: { id: string; name: string }[];
    modes: string[];
    urls: {
        index: string; create: string; store: string; export: string; bulkDestroy: string;
    };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk-delete' } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function ClearancePolicyIndex({ shell, can, stats, data, filters, invoiceTypes, holdTypes, modes, urls }: Props) {
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

    const activeFilterCount = [filters.invoice_type, filters.hold_type, filters.mode, filters.active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ invoice_type: '', hold_type: '', mode: '', active: '', page: 1 }, true);
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
        router.post(row.toggleUrl, {}, { preserveScroll: true });
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
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const modal = pending?.kind === 'bulk-delete'
        ? { title: `Hapus permanen ${selected.length} kebijakan?`, message: 'Kebijakan terpilih dihapus permanen dan evaluasi clearance berikutnya memakai sisa yang aktif.', confirm: 'Ya, hapus permanen' }
        : { title: 'Hapus permanen kebijakan?', message: 'Kebijakan dihapus permanen dari konfigurasi aktif.', confirm: 'Ya, hapus permanen' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Kebijakan Bebas Tanggungan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ShieldCheck}
                        eyebrow="Keuangan"
                        title="Kebijakan Bebas Tanggungan"
                        description="Aturan pemicu hold layanan akademik per jenis tagihan. Diterapkan pada evaluasi clearance berikutnya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kebijakan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik kebijakan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><ShieldCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total kebijakan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><ShieldCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><ShieldCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.blocking}</span><span className="db-stat-label">Mode blokir</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Kebijakan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'invoice_type', label: 'Tagihan → Hold', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.invoiceType}</b>
                                                <small className="db-hint">→ {row.holdType} · {row.holds} hold aktif</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'mode', label: 'Mode',
                                        render: (row) => <span className={`db-badge ${row.modeTone}`}>{row.modeText}</span>,
                                    },
                                    { key: 'grace_days', label: 'Tenggang', sortable: true, render: (row) => row.graceDays },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle ? (
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
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari deskripsi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.invoice_type}
                                            onChange={(e) => visit({ invoice_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis tagihan"
                                        >
                                            <option value="">Semua tagihan</option>
                                            {invoiceTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.hold_type}
                                            onChange={(e) => visit({ hold_type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter target hold"
                                        >
                                            <option value="">Semua target</option>
                                            {holdTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.mode}
                                            onChange={(e) => visit({ mode: e.target.value, page: 1 }, true)}
                                            aria-label="Filter mode"
                                        >
                                            <option value="">Semua mode</option>
                                            {modes.map((m) => <option key={m} value={m}>{m === 'blocking' ? 'Pemblokiran' : 'Peringatan'}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.active}
                                            onChange={(e) => visit({ active: e.target.value, page: 1 }, true)}
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
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus permanen terpilih"
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
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Tambah Kebijakan"
                                exportHref={exportHref}
                                emptyText="Belum ada kebijakan yang cocok dengan filter."
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
