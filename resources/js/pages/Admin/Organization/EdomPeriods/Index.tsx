// Daftar periode EDOM (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CalendarCheck, CalendarDays, CalendarRange, Lock, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string | number; label: string };
type AcademicYear = { id: number; name: string; code: string };

type Row = {
    id: number;
    no: number;
    name: string;
    code: string;
    academicYearName: string;
    academicPeriodName: string;
    startsAt: string;
    endsAt: string;
    minimumResponses: number;
    status: string;
    statusLabel: string;
    responsesCount: number;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; open: number; closed: number; avgMin: number };
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
    academicYears: AcademicYear[];
    statuses: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const statusTone = (status: string) => (status === 'open' ? 'green' : status === 'draft' ? 'amber' : 'gray');

export default function EdomPeriodsIndex({ shell, can, stats, data, filters, academicYears, statuses, urls }: Props) {
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
            <Head title={`Periode EDOM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarRange}
                        eyebrow="Kepegawaian"
                        title="Periode EDOM"
                        description="Kelola jadwal Evaluasi Dosen Oleh Mahasiswa (EDOM) beserta rentang waktu pengisian dan batas minimal respons."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Periode</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik periode EDOM"
                        items={[
                            { label: 'Total Periode', value: stats.total, icon: CalendarDays },
                            { label: 'Periode Dibuka', value: stats.open, icon: CalendarCheck, tone: 'green' },
                            { label: 'Periode Ditutup', value: stats.closed, icon: Lock, tone: 'gold' },
                            { label: 'Rata-rata Min. Respon', value: `${stats.avgMin} mahasiswa`, icon: Users },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Jadwal & Status Periode</h2>
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
                                                <small className="db-hint">{row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'academic_year_name',
                                        label: 'Tahun Akademik',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.academicYearName}</b>
                                                <small className="db-hint">{row.academicPeriodName}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'starts_at',
                                        label: 'Rentang Waktu',
                                        sortable: true,
                                        render: (row) => `${row.startsAt} – ${row.endsAt}`,
                                    },
                                    {
                                        key: 'minimum_responses',
                                        label: 'Min. Respon',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.minimumResponses,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${statusTone(row.status)}`}>{row.statusLabel}</span>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.responsesCount} respons</small>
                                            </span>
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
                                searchPlaceholder="Cari nama periode, kode, atau tahun akademik…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status periode"
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
                                            {academicYears.map((year) => (
                                                <option key={year.id} value={year.id}>{year.name}</option>
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
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada periode EDOM yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus periode EDOM terpilih?' : 'Hapus periode EDOM?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} periode EDOM akan dihapus. Periode yang sudah memiliki respons tidak dapat dihapus.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus dari daftar periode EDOM.`
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
