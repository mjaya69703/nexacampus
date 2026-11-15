// Daftar aturan penomoran NIM — memakai kit CRUD shared.
import { Head, router } from '@inertiajs/react';
import { Hash, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; pattern: string; sequenceScope: string;
    scopeLabel: string; sequencePadding: number; sequenceStart: number;
    countersCount: number; isActive: boolean; createdAt: string | null;
    editUrl: string; deleteUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; toggle: boolean };
    stats: { total: number; active: number; counters: number; scopes: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; sequence_scope: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    scopeOptions: { id: string; label: string }[];
    urls: { index: string; create: string; export: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk-delete' } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function NimRuleIndex({ shell, can, stats, data, filters, scopeOptions, urls }: Props) {
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

    const activeFilterCount = [filters.sequence_scope, filters.is_active]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        visit({ sequence_scope: '', is_active: '', page: 1 }, true);
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const copy = pending?.kind === 'delete'
        ? {
            title: 'Hapus aturan NIM?',
            message: `Aturan "${pending.row.name}" dihapus. Counter nomor urut yang terkait ikut terhapus.`,
            confirm: 'Ya, hapus',
        }
        : {
            title: 'Hapus aturan terpilih?',
            message: `${selected.length} aturan NIM akan dihapus permanen beserta counter-nya.`,
            confirm: 'Ya, hapus',
        };

    return (
        <AdminShell shell={shell}>
            <Head title={`Aturan Penomoran NIM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Hash}
                        eyebrow="Admission"
                        title="Aturan Penomoran NIM"
                        description="Pola pembentukan NIM otomatis untuk mahasiswa baru. Hanya satu aturan yang dapat aktif pada satu waktu."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Aturan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik aturan NIM">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Hash size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total aturan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Hash size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Hash size={20} /></span>
                            <div><span className="db-stat-num">{stats.counters}</span><span className="db-stat-label">Counter terpakai</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Hash size={20} /></span>
                            <div><span className="db-stat-num">{stats.scopes}</span><span className="db-stat-label">Ruang lingkup</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Aturan NIM</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'name', label: 'Nama aturan', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.scopeLabel}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'pattern', label: 'Pola NIM', sortable: true,
                                        render: (row) => <code className="crud-name">{row.pattern}</code>,
                                    },
                                    { key: 'sequence_scope', label: 'Scope', sortable: true, render: (row) => row.scopeLabel },
                                    {
                                        key: 'sequence_padding', label: 'Padding',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => `${row.sequencePadding} digit`,
                                    },
                                    { key: 'sequence_start', label: 'Mulai', sortable: true, align: 'right', render: (row) => row.sequenceStart },
                                    { key: 'counters', label: 'Counter', align: 'right', render: (row) => row.countersCount },
                                    {
                                        key: 'is_active', label: 'Aktif',
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
                                searchPlaceholder="Cari nama aturan, pola NIM…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.sequence_scope}
                                            onChange={(e) => visit({ sequence_scope: e.target.value, page: 1 }, true)}
                                            aria-label="Filter ruang lingkup"
                                        >
                                            <option value="">Semua ruang lingkup</option>
                                            {scopeOptions.map((scope) => <option key={scope.id} value={scope.id}>{scope.label}</option>)}
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
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus aturan terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                createLabel="Tambah Aturan"
                                exportHref={exportHref}
                                emptyText="Belum ada aturan NIM yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy.title}
                    message={copy.message}
                    confirmLabel={copy.confirm}
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
