// Daftar pengajuan surat mahasiswa (monitoring admin).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, Eye, FileText, MailCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ServiceStats, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { statusLabel } from '../../../../components/Shared/StudentServices/labels';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    requestNumber: string;
    letterType: string;
    studentName: string;
    nim: string;
    studyProgram: string;
    purpose: string;
    status: string;
    submittedAt: string | null;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean };
    stats: { total: number; waiting: number; approved: number; issued: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        service_letter_type_id: string | number;
        study_program_id: string | number;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    letterTypes: { id: number; name: string }[];
    studyPrograms: { id: number; name: string }[];
    urls: { index: string };
};

const STATUSES = ['submitted', 'in_approval', 'under_review', 'revision_requested', 'approved', 'rejected', 'issued', 'cancelled'];

export default function LetterRequestsIndex({ shell, stats, data, filters, letterTypes, studyPrograms, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.service_letter_type_id,
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
        visit({ service_letter_type_id: '', study_program_id: '', status: '', page: 1 }, '');
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengajuan Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Layanan Mahasiswa"
                        title="Pengajuan Surat"
                        description="Pantau pengajuan surat mahasiswa, proses review, persetujuan, hingga penerbitan surat."
                    />

                    <ServiceStats
                        label="Statistik pengajuan surat"
                        items={[
                            { label: 'Total Pengajuan', value: stats.total, icon: FileText },
                            { label: 'Menunggu Proses', value: stats.waiting, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Diterbitkan', value: stats.issued, icon: MailCheck, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengajuan Surat</h2>
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
                                                <b className="crud-name">{row.requestNumber}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.letterType}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.studentName}</b>
                                                <small className="db-hint">{row.nim} · {row.studyProgram}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'purpose',
                                        label: 'Keperluan',
                                        render: (row) => (
                                            <span style={{ display: 'block', maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }} title={row.purpose}>
                                                {row.purpose}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <StatusBadge status={row.status} />,
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
                                searchPlaceholder="Cari nomor, mahasiswa, atau jenis surat…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.service_letter_type_id)}
                                            onChange={(event) => visit({ service_letter_type_id: event.target.value, page: 1 })}
                                            aria-label="Filter jenis surat"
                                        >
                                            <option value="">Semua jenis surat</option>
                                            {letterTypes.map((option) => (
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
                                                <option key={status} value={status}>{statusLabel(status)}</option>
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
                                emptyText="Belum ada pengajuan surat yang cocok dengan filter."
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
