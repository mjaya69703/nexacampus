// Daftar pengajuan pindah mahasiswa (monitoring admin).
import { Head, router } from '@inertiajs/react';
import { ArrowLeftRight, CheckCircle2, Clock3, Eye, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { transferTypeLabel } from '../../../../components/Shared/StudentServices/labels';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    number: string;
    student: string;
    nim: string;
    transferType: string;
    fromProgram: string;
    toProgram: string;
    recommendedSemester: number | null;
    status: string;
    submittedAt: string | null;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean };
    stats: { total: number; waiting: number; pendingPayment: number; applied: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        transfer_type: string;
        from_study_program_id: string | number;
        to_study_program_id: string | number;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    studyPrograms: { id: number; name: string }[];
    transferTypes: { id: string; name: string }[];
    statuses: { id: string; name: string }[];
    urls: { index: string };
};

export default function TransferRequestsIndex({
    shell,
    stats,
    data,
    filters,
    studyPrograms,
    transferTypes,
    statuses,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.transfer_type,
        filters.from_study_program_id,
        filters.to_study_program_id,
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
        visit({
            transfer_type: '',
            from_study_program_id: '',
            to_study_program_id: '',
            status: '',
            page: 1,
        }, '');
    };

    const statusLabels = Object.fromEntries(statuses.map((status) => [status.id, status.name]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengajuan Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowLeftRight}
                        eyebrow="Layanan Mahasiswa"
                        title="Pengajuan Pindah"
                        description="Kelola pengajuan pindah program studi, fakultas, atau kelas beserta evaluasi akademik dan biaya."
                    />

                    <ServiceStats
                        label="Statistik pengajuan pindah"
                        items={[
                            { label: 'Total Pengajuan', value: stats.total, icon: ArrowLeftRight },
                            { label: 'Menunggu Proses', value: stats.waiting, icon: Clock3, tone: 'gold' },
                            { label: 'Menunggu Bayar', value: stats.pendingPayment, icon: ReceiptText, tone: 'gold' },
                            { label: 'Sudah Diterapkan', value: stats.applied, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengajuan Pindah</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'request_number',
                                        label: 'Nomor',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b className="crud-name">{row.number}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.student} · {row.nim}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'transfer_type',
                                        label: 'Jenis Pindah',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{transferTypeLabel(row.transferType)}</span>,
                                    },
                                    {
                                        key: 'program',
                                        label: 'Asal → Tujuan',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.fromProgram}</b>
                                                <small className="db-hint">→ {row.toProgram}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'recommended_semester',
                                        label: 'Semester',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.recommendedSemester ?? '-',
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <StatusBadge status={row.status} label={statusLabels[row.status]} />,
                                    },
                                    {
                                        key: 'created_at',
                                        label: 'Diajukan',
                                        sortable: true,
                                        render: (row) => row.submittedAt ?? '-',
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
                                searchPlaceholder="Cari nomor, mahasiswa, atau alasan…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.transfer_type}
                                            onChange={(event) => visit({ transfer_type: event.target.value, page: 1 })}
                                            aria-label="Filter jenis pindah"
                                        >
                                            <option value="">Semua jenis pindah</option>
                                            {transferTypes.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={String(filters.from_study_program_id)}
                                            onChange={(event) => visit({ from_study_program_id: event.target.value, page: 1 })}
                                            aria-label="Filter program studi asal"
                                        >
                                            <option value="">Semua prodi asal</option>
                                            {studyPrograms.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={String(filters.to_study_program_id)}
                                            onChange={(event) => visit({ to_study_program_id: event.target.value, page: 1 })}
                                            aria-label="Filter program studi tujuan"
                                        >
                                            <option value="">Semua prodi tujuan</option>
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
                                            {statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
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
                                canUpdate={false}
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada pengajuan pindah yang cocok dengan filter."
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
