// Daftar batch yudisium (admin).
import { Head, router } from '@inertiajs/react';
import { CalendarCheck2, CheckCircle2, Clock3, Eye, GraduationCap, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    code: string;
    period: string;
    scope: string;
    yudisiumDate: string;
    status: string;
    applicationsCount: number;
    approvedCount: number;
    finalizedCount: number;
    showUrl: string;
    editUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; view: boolean };
    stats: { total: number; open: number; review: number; finalized: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        academic_period_id: string | number;
        study_program_id: string | number;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    academicPeriods: { id: number; name: string }[];
    studyPrograms: { id: number; name: string }[];
    urls: { index: string; create: string };
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draf',
    open: 'Dibuka',
    review: 'Review',
    finalized: 'Final',
    cancelled: 'Dibatalkan',
};

const STATUSES = ['draft', 'open', 'review', 'finalized', 'cancelled'];

export default function GraduationBatchesIndex({
    shell,
    can,
    stats,
    data,
    filters,
    academicPeriods,
    studyPrograms,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.academic_period_id,
        filters.study_program_id,
        filters.status,
    ].filter((value) => value !== '' && value !== null && value !== undefined).length;

    const visit = (overrides: Record<string, string | number | undefined>, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ academic_period_id: '', study_program_id: '', status: '', page: 1 }, '');
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Batch Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Layanan Mahasiswa"
                        title="Batch Yudisium"
                        description="Kelola periode yudisium, kuota pengajuan, dan finalisasi kelulusan mahasiswa."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Batch</a>
                        ) : undefined}
                    />

                    <ServiceStats
                        label="Statistik batch yudisium"
                        items={[
                            { label: 'Total Batch', value: stats.total, icon: GraduationCap },
                            { label: 'Dibuka', value: stats.open, icon: CalendarCheck2, tone: 'green' },
                            { label: 'Review', value: stats.review, icon: Clock3, tone: 'gold' },
                            { label: 'Final', value: stats.finalized, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Batch Yudisium</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Batch',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'period',
                                        label: 'Periode',
                                        render: (row) => row.period,
                                    },
                                    {
                                        key: 'scope',
                                        label: 'Cakupan',
                                        render: (row) => <span className="db-badge gray">{row.scope}</span>,
                                    },
                                    {
                                        key: 'yudisium_date',
                                        label: 'Tanggal Yudisium',
                                        sortable: true,
                                        render: (row) => row.yudisiumDate,
                                    },
                                    {
                                        key: 'applications_count',
                                        label: 'Pengajuan',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ color: 'var(--db-heading)' }}>{row.applicationsCount}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>
                                                    {row.approvedCount} disetujui · {row.finalizedCount} final
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <StatusBadge status={row.status} label={STATUS_LABELS[row.status]} />,
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
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari nama atau kode batch…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.academic_period_id)}
                                            onChange={(event) => visit({ academic_period_id: event.target.value, page: 1 })}
                                            aria-label="Filter periode akademik"
                                        >
                                            <option value="">Semua periode</option>
                                            {academicPeriods.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={String(filters.study_program_id)}
                                            onChange={(event) => visit({ study_program_id: event.target.value, page: 1 })}
                                            aria-label="Filter program studi"
                                        >
                                            <option value="">Semua program studi</option>
                                            {studyPrograms.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 })}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {STATUSES.map((status) => (
                                                <option key={status} value={status}>{STATUS_LABELS[status]}</option>
                                            ))}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                selectable={false}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada batch yudisium yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
