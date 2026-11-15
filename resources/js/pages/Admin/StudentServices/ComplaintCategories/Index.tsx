// Daftar kategori pengaduan mahasiswa (admin).
import { Head, router } from '@inertiajs/react';
import { Clock3, MessageSquareWarning, Plus, ShieldCheck, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats } from '../../../../components/Shared/StudentServices/ServiceUI';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    code: string;
    workUnit: string;
    slaHours: number;
    complaintsCount: number;
    isActive: boolean;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; tickets: number; avgSla: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        default_work_unit_id: string | number;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    workUnits: { id: number; name: string }[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function ComplaintCategoriesIndex({ shell, can, stats, data, filters, workUnits, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.default_work_unit_id, filters.is_active]
        .filter((value) => value !== '' && value !== null && value !== undefined).length;

    const visit = (overrides: Record<string, string | number | undefined>, clear = false, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clear) setSelected([]);
            },
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ default_work_unit_id: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
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
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Kategori Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MessageSquareWarning}
                        eyebrow="Layanan Mahasiswa"
                        title="Kategori Pengaduan"
                        description="Kelompokkan pengaduan mahasiswa, tentukan unit kerja penanggung jawab, dan target SLA penyelesaian."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kategori</a>
                        ) : undefined}
                    />

                    <ServiceStats
                        label="Statistik kategori pengaduan"
                        items={[
                            { label: 'Total Kategori', value: stats.total, icon: MessageSquareWarning },
                            { label: 'Aktif', value: stats.active, icon: ShieldCheck, tone: 'green' },
                            { label: 'Total Tiket', value: stats.tickets, icon: Users, tone: 'gold' },
                            { label: 'Rata-rata SLA', value: `${stats.avgSla} jam`, icon: Clock3, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Kategori Pengaduan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Kategori',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'work_unit',
                                        label: 'Unit Kerja',
                                        render: (row) => row.workUnit,
                                    },
                                    {
                                        key: 'default_sla_hours',
                                        label: 'SLA',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => `${row.slaHours} jam`,
                                    },
                                    {
                                        key: 'complaints_count',
                                        label: 'Tiket',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.complaintsCount,
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            can.update ? (
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
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({
                                    sort: key,
                                    direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
                                    page: 1,
                                })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama atau kode kategori…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.default_work_unit_id)}
                                            onChange={(event) => visit({ default_work_unit_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter unit kerja"
                                        >
                                            <option value="">Semua unit kerja</option>
                                            {workUnits.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
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
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada kategori pengaduan yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus kategori terpilih?' : 'Hapus kategori pengaduan?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} kategori pengaduan akan dihapus.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus dari daftar kategori.`
                            : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
