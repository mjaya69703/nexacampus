// Daftar pengajuan cuti akademik (monitoring admin).
import { Head, router } from '@inertiajs/react';
import { CalendarMinus, CheckCircle2, Clock3, Eye, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { buildQuery, leaveReasonLabel } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    number: string;
    student: string;
    nim: string;
    studyProgram: string;
    academicYear: string;
    semester: number | null;
    durationSemesters: number;
    reasonCategory: string;
    status: string;
    submittedAt: string | null;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean };
    stats: { total: number; waiting: number; pendingPayment: number; active: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        academic_year_id: string | number;
        study_program_id: string | number;
        reason_category: string;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    academicYears: { id: number; name: string }[];
    studyPrograms: { id: number; name: string }[];
    reasonCategories: { id: string; name: string }[];
    statuses: { id: string; name: string }[];
    urls: { index: string };
};

export default function LeaveApplicationsIndex({
    shell,
    stats,
    data,
    filters,
    academicYears,
    studyPrograms,
    reasonCategories,
    statuses,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.academic_year_id,
        filters.study_program_id,
        filters.reason_category,
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
            academic_year_id: '',
            study_program_id: '',
            reason_category: '',
            status: '',
            page: 1,
        }, '');
    };

    const statusLabels = Object.fromEntries(statuses.map((status) => [status.id, status.name]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengajuan Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Layanan Mahasiswa"
                        title="Pengajuan Cuti"
                        description="Pantau pengajuan cuti akademik, verifikasi biaya cuti, aktivasi, dan pengembalian status aktif."
                    />

                    <ServiceStats
                        label="Statistik pengajuan cuti"
                        items={[
                            { label: 'Total Pengajuan', value: stats.total, icon: CalendarMinus },
                            { label: 'Menunggu Proses', value: stats.waiting, icon: Clock3, tone: 'gold' },
                            { label: 'Menunggu Bayar', value: stats.pendingPayment, icon: ReceiptText, tone: 'gold' },
                            { label: 'Cuti Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengajuan Cuti</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'application_number',
                                        label: 'Nomor',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b className="crud-name">{row.number}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.student}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.nim}</b>
                                                <small className="db-hint">{row.studyProgram}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'academic_year',
                                        label: 'Tahun / Semester',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.academicYear}</b>
                                                <small className="db-hint">Semester {row.semester ?? '-'} · {row.durationSemesters} semester</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'reason_category',
                                        label: 'Alasan',
                                        render: (row) => <span className="db-badge gray">{leaveReasonLabel(row.reasonCategory)}</span>,
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
                                            value={String(filters.academic_year_id)}
                                            onChange={(event) => visit({ academic_year_id: event.target.value, page: 1 })}
                                            aria-label="Filter tahun akademik"
                                        >
                                            <option value="">Semua tahun akademik</option>
                                            {academicYears.map((option) => (
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
                                            value={filters.reason_category}
                                            onChange={(event) => visit({ reason_category: event.target.value, page: 1 })}
                                            aria-label="Filter kategori alasan"
                                        >
                                            <option value="">Semua alasan</option>
                                            {reasonCategories.map((option) => (
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
                                emptyText="Belum ada pengajuan cuti yang cocok dengan filter."
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
