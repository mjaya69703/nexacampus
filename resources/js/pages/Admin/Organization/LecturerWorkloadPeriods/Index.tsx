// Daftar periode Beban Kerja Dosen (BKD) — admin kepegawaian.
import { Head, router } from '@inertiajs/react';
import { CalendarCheck, DoorOpen, Eye, Lock, Plus, Search } from 'lucide-react';
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
    name: string;
    code: string;
    academicYear: string;
    academicPeriod: string | null;
    periodRange: string;
    sksRange: string;
    minimumSks: number;
    maximumSks: number;
    status: string;
    statusLabel: string;
    submissionsCount: number;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; open: number; review: number; closed: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        status: string;
        academic_year_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    statuses: Option[];
    academicYears: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const statusTone = (status: string) => {
    if (status === 'open') return 'green';
    if (status === 'review') return 'amber';
    if (status === 'closed') return 'gray';
    return '';
};

export default function LecturerWorkloadPeriodsIndex({ shell, can, stats, data, filters, statuses, academicYears, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.status !== '' ? 1 : 0) + (filters.academic_year_id !== '' ? 1 : 0);

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
        visit({ status: '', academic_year_id: '', page: 1 }, true, '');
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
            <Head title={`Periode BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kepegawaian"
                        title="Periode Beban Kerja Dosen (BKD)"
                        description="Kelola jadwal siklus pembukaan, masa pengajuan, proses review asesor, hingga penutupan pelaporan Beban Kerja Dosen per periode akademik."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Periode Baru</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik periode BKD"
                        items={[
                            { label: 'Total Periode', value: stats.total, icon: CalendarCheck },
                            { label: 'Status Dibuka', value: stats.open, icon: DoorOpen, tone: 'green' },
                            { label: 'Masa Review', value: stats.review, icon: Search, tone: 'gold' },
                            { label: 'Ditutup / Selesai', value: stats.closed, icon: Lock },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Manajemen Siklus BKD</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Periode',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">Kode: {row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'academic_year',
                                        label: 'Tahun Akademik',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.academicYear}</b>
                                                <small className="db-hint">{row.academicPeriod ?? 'Tanpa periode akademik'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'starts_at',
                                        label: 'Rentang Waktu',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.periodRange}</span>,
                                    },
                                    {
                                        key: 'sks_range',
                                        label: 'Rentang SKS',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{row.sksRange}</b>,
                                    },
                                    {
                                        key: 'submissions_count',
                                        label: 'Laporan',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (
                                            <b style={{ color: row.submissionsCount > 0 ? 'var(--db-heading)' : 'var(--db-muted)', fontSize: 13 }}>
                                                {row.submissionsCount}
                                            </b>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${statusTone(row.status)}`.trim()}>{row.statusLabel}</span>
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
                                searchPlaceholder="Cari nama, kode, atau tahun akademik periode…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status siklus BKD"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.academic_year_id}
                                            onChange={(event) => visit({ academic_year_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun akademik"
                                        >
                                            <option value="">Semua tahun akademik</option>
                                            {academicYears.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
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
                                customActions={can.view ? (row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail">
                                        <Eye size={13} />
                                    </a>
                                ) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete || can.view}
                                canCreate={false}
                                emptyText="Belum ada periode BKD yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus periode BKD terpilih?' : 'Hapus periode BKD?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} periode BKD akan dihapus dari daftar siklus.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus. Periode yang sudah memiliki laporan BKD tidak dapat dihapus.`
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
