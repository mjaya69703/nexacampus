// Daftar laporan BKD dosen untuk direview (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { Calculator, CheckCircle2, Clock3, Download, Eye, FileSignature } from 'lucide-react';
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
    lecturerName: string;
    lecturerEmail: string | null;
    periodName: string;
    periodCode: string | null;
    programName: string;
    teachingSks: number;
    structuralSks: number;
    tridharmaSks: number;
    totalSks: number;
    status: string;
    statusLabel: string;
    submittedAt: string;
    showUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean; delete: boolean; export: boolean };
    stats: { total: number; inApproval: number; approved: number; averageSks: number };
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
        period_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    statuses: Option[];
    periods: Option[];
    urls: { index: string; exportCsv: string; exportXlsx: string; exportPdf: string };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'revision') return 'amber';
    if (status === 'in_approval' || status === 'submitted') return 'gold';
    return 'gray';
};

const formatSks = (value: number) => `${value.toFixed(1)} SKS`;

export default function LecturerWorkloadSubmissionsIndex({ shell, can, stats, data, filters, statuses, periods, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<Row | null>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.status !== '' ? 1 : 0) + (filters.period_id !== '' ? 1 : 0);

    const visit = (overrides: Record<string, string | number | undefined>, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ status: '', period_id: '', page: 1 }, '');
    };

    const confirmDelete = () => {
        if (!pending) return;
        setProcessing(true);
        router.delete(pending.deleteUrl, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Review BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Kepegawaian"
                        title="Review Laporan Beban Kerja Dosen"
                        description="Periksa dan verifikasi rekapitulasi capaian SKS pengajaran, penelitian, pengabdian, penunjang, serta tugas struktural yang diajukan oleh dosen."
                        actions={can.export ? (
                            <>
                                <a className="db-btn light" href={urls.exportCsv}><Download size={15} /> CSV</a>
                                <a className="db-btn light" href={urls.exportXlsx}><Download size={15} /> Excel</a>
                                <a className="db-btn light" href={urls.exportPdf}><Download size={15} /> PDF</a>
                            </>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik laporan BKD"
                        items={[
                            { label: 'Total Laporan Masuk', value: stats.total, icon: FileSignature },
                            { label: 'Menunggu Approval', value: stats.inApproval, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui (Approved)', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Rata-Rata SKS', value: formatSks(stats.averageSks), icon: Calculator },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Pengajuan Beban Kerja Dosen</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'lecturer_name',
                                        label: 'Dosen',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.lecturerName}</b>
                                                <small className="db-hint">{row.lecturerEmail ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'period_name',
                                        label: 'Periode BKD',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.periodName}</b>
                                                <small className="db-hint">{row.periodCode ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'program_name', label: 'Program Studi', render: (row) => <span className="db-hint">{row.programName}</span> },
                                    {
                                        key: 'total_sks',
                                        label: 'Total SKS',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatSks(row.totalSks)}</b>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${statusTone(row.status)}`.trim()}>{row.statusLabel}</span>,
                                    },
                                    {
                                        key: 'submitted_at',
                                        label: 'Diajukan',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.submittedAt}</span>,
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
                                searchPlaceholder="Cari nama dosen, periode BKD, atau program studi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 })}
                                            aria-label="Filter status laporan BKD"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.period_id}
                                            onChange={(event) => visit({ period_id: event.target.value, page: 1 })}
                                            aria-label="Filter periode BKD"
                                        >
                                            <option value="">Semua periode</option>
                                            {periods.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                selectable={false}
                                canUpdate={false}
                                customActions={can.view ? (row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail laporan">
                                        <Eye size={13} />
                                    </a>
                                ) : undefined}
                                onDeleteRow={can.delete ? (row) => (row.canDelete ? setPending(row) : undefined) : undefined}
                                showActions={can.view || can.delete}
                                canCreate={false}
                                emptyText="Belum ada laporan BKD yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title="Hapus laporan BKD?"
                    message={pending ? `Laporan BKD milik "${pending.lecturerName}" beserta seluruh rincian itemnya akan dihapus permanen.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDelete}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
