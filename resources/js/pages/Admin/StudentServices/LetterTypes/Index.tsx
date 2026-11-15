// Daftar jenis surat layanan mahasiswa (admin).
import { Head, router } from '@inertiajs/react';
import { FileText, Plus, ScrollText, ShieldCheck, Sparkles, Trash2 } from 'lucide-react';
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
    fulfillmentMode: string;
    fulfillmentLabel: string;
    requiresClearance: boolean;
    clearanceLabel: string;
    requestsCount: number;
    isActive: boolean;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; autoGenerate: number; clearance: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        fulfillment_mode: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const FULFILLMENT_OPTIONS = [
    { id: 'auto_generate', name: 'Auto Generate' },
    { id: 'manual_upload', name: 'Unggah Manual' },
    { id: 'hybrid', name: 'Hybrid' },
];

export default function LetterTypesIndex({ shell, can, stats, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.fulfillment_mode !== '' ? 1 : 0) + (filters.is_active !== '' ? 1 : 0);

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
        visit({ fulfillment_mode: '', is_active: '', page: 1 }, true, '');
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
            <Head title={`Jenis Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ScrollText}
                        eyebrow="Layanan Mahasiswa"
                        title="Jenis Surat"
                        description="Atur jenis surat layanan, metode pemenuhan, syarat bebas keuangan, dan penandatangan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Jenis Surat</a>
                        ) : undefined}
                    />

                    <ServiceStats
                        label="Statistik jenis surat"
                        items={[
                            { label: 'Total Jenis', value: stats.total, icon: FileText },
                            { label: 'Aktif', value: stats.active, icon: ShieldCheck, tone: 'green' },
                            { label: 'Auto Generate', value: stats.autoGenerate, icon: Sparkles, tone: 'gold' },
                            { label: 'Butuh Clearance', value: stats.clearance, icon: ShieldCheck, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Jenis Surat</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Jenis Surat',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'fulfillment_mode',
                                        label: 'Pemenuhan',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.fulfillmentLabel}</span>,
                                    },
                                    {
                                        key: 'clearance',
                                        label: 'Clearance',
                                        render: (row) => (
                                            <span className={`db-badge ${row.requiresClearance ? 'amber' : 'green'}`}>
                                                {row.clearanceLabel}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'requests_count',
                                        label: 'Pengajuan',
                                        align: 'right',
                                        render: (row) => row.requestsCount,
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
                                searchPlaceholder="Cari nama atau kode surat…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.fulfillment_mode}
                                            onChange={(event) => visit({ fulfillment_mode: event.target.value, page: 1 }, true)}
                                            aria-label="Filter metode pemenuhan"
                                        >
                                            <option value="">Semua metode</option>
                                            {FULFILLMENT_OPTIONS.map((option) => (
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
                                emptyText="Belum ada jenis surat yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus jenis surat terpilih?' : 'Hapus jenis surat?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} jenis surat akan dihapus.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus dari daftar jenis surat.`
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
