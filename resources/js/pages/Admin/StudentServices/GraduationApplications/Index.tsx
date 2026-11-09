// Daftar pengajuan yudisium (monitoring admin).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, Eye, GraduationCap, Trophy } from 'lucide-react';
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
    number: string;
    student: string;
    nim: string;
    studyProgram: string;
    batch: string;
    graduationPeriod: string;
    status: string;
    submittedAt: string | null;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean };
    stats: { total: number; waiting: number; approved: number; finalized: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        graduation_batch_id: string | number;
        study_program_id: string | number;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    batches: { id: number; name: string }[];
    studyPrograms: { id: number; name: string }[];
    statuses: { id: string; name: string }[];
    urls: { index: string };
};

export default function GraduationApplicationsIndex({
    shell,
    stats,
    data,
    filters,
    batches,
    studyPrograms,
    statuses,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.graduation_batch_id,
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
        visit({ graduation_batch_id: '', study_program_id: '', status: '', page: 1 }, '');
    };

    const statusLabels = Object.fromEntries(statuses.map((status) => [status.id, status.name]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengajuan Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Layanan Mahasiswa"
                        title="Pengajuan Yudisium"
                        description="Review kelayakan akademik, verifikasi dokumen, setujui, dan finalisasi kelulusan mahasiswa."
                    />

                    <ServiceStats
                        label="Statistik pengajuan yudisium"
                        items={[
                            { label: 'Total Pengajuan', value: stats.total, icon: GraduationCap },
                            { label: 'Menunggu Proses', value: stats.waiting, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Selesai', value: stats.finalized, icon: Trophy, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengajuan Yudisium</h2>
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
                                                <small className="db-hint" style={{ display: 'block' }}>{row.batch}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim} · {row.studyProgram}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'graduation_period',
                                        label: 'Periode Yudisium',
                                        sortable: true,
                                        render: (row) => row.graduationPeriod || '-',
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
                                searchPlaceholder="Cari nomor, mahasiswa, atau judul skripsi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.graduation_batch_id)}
                                            onChange={(event) => visit({ graduation_batch_id: event.target.value, page: 1 })}
                                            aria-label="Filter batch yudisium"
                                        >
                                            <option value="">Semua batch</option>
                                            {batches.map((option) => (
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
                                emptyText="Belum ada pengajuan yudisium yang cocok dengan filter."
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
