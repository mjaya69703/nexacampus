// Daftar aturan konversi SKS BKD (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { BookOpenCheck, CheckCircle2, Cog, GraduationCap, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type Row = {
    id: number;
    no: number;
    category: string;
    categoryLabel: string;
    sourceCode: string;
    name: string;
    sksValue: number;
    maximumSks: number | null;
    isActive: boolean;
    description: string | null;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; teaching: number; tridharma: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        category: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    categories: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const formatSks = (value: number) => `${Number.isInteger(value) ? value : value.toFixed(2)} SKS`;

export default function LecturerWorkloadRulesIndex({ shell, can, stats, data, filters, categories, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.category !== '' ? 1 : 0) + (filters.is_active !== '' ? 1 : 0);

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
        visit({ category: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
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
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Aturan SKS BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Cog}
                        eyebrow="Kepegawaian"
                        title="Aturan SKS BKD"
                        description="Atur regulasi konversi poin, kuota SKS maksimal per item, serta kategori aktivitas tri dharma (pengajaran, penelitian, pengabdian, penunjang, dan jabatan struktural)."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Aturan</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik aturan SKS BKD"
                        items={[
                            { label: 'Total Aturan', value: stats.total, icon: Cog },
                            { label: 'Aturan Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Kategori Pengajaran', value: stats.teaching, icon: GraduationCap, tone: 'gold' },
                            { label: 'Kategori Tridharma', value: stats.tridharma, icon: BookOpenCheck },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Aturan Konversi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Aturan',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">Kode sumber: {row.sourceCode}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.categoryLabel}</span>,
                                    },
                                    {
                                        key: 'sks_value',
                                        label: 'Nilai SKS',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatSks(row.sksValue)}</b>,
                                    },
                                    {
                                        key: 'maximum_sks',
                                        label: 'Maksimum',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (row.maximumSks === null ? <span className="db-hint">Tidak dibatasi</span> : formatSks(row.maximumSks)),
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
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
                                searchPlaceholder="Cari nama, kode sumber, atau deskripsi aturan…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.category}
                                            onChange={(event) => visit({ category: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori aktivitas"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categories.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status aturan"
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
                                emptyText="Belum ada aturan SKS BKD yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus aturan SKS terpilih?' : 'Hapus aturan SKS?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} aturan SKS BKD akan dihapus dari daftar konversi.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus dari daftar aturan SKS BKD.`
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
