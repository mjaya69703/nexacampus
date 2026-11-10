// Rekap review performa dosen (EDOM, kepatuhan mengajar, absensi, BKD).
import { Head, router } from '@inertiajs/react';
import { Calculator, CheckCircle2, Eye, Star, Users } from 'lucide-react';
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
    edomScore: number | null;
    responseCount: number;
    finalScore: number | null;
    status: string;
    statusLabel: string;
    calculatedAt: string;
    showUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; update: boolean; delete: boolean };
    stats: { total: number; published: number; averageScore: number };
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
    urls: { index: string; calculateAll: string };
};

type Pending = { kind: 'calculate' } | { kind: 'delete'; row: Row } | null;

const statusTone = (status: string) => {
    if (status === 'published') return 'green';
    if (status === 'calculated') return 'amber';
    return 'gray';
};

const formatScore = (value: number | null, digits = 1) => (value === null ? '-' : value.toFixed(digits));

export default function LecturerPerformanceReviewsIndex({ shell, can, stats, data, filters, statuses, periods, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<Pending>(null);
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

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);

        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending.kind === 'calculate') {
            router.post(urls.calculateAll, {}, { preserveScroll: true, onFinish: done });
            return;
        }

        router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Review Performa Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Star}
                        eyebrow="Kepegawaian"
                        title="Review Performa Dosen"
                        description="Rekapitulasi evaluasi kinerja tri dharma dosen berdasarkan skor EDOM, kepatuhan jadwal mengajar, kedisiplinan absensi, serta kelayakan beban kerja (BKD)."
                        actions={can.update ? (
                            <button className="db-btn light" type="button" onClick={() => setPending({ kind: 'calculate' })}>
                                <Calculator size={15} /> Hitung Ulang Performa
                            </button>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik review performa"
                        items={[
                            { label: 'Total Evaluasi', value: stats.total, icon: Users },
                            { label: 'Published', value: stats.published, icon: CheckCircle2, tone: 'green' },
                            { label: 'Rata-Rata Nilai', value: `${formatScore(stats.averageScore)} / 100`, icon: Star, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Rekapitulasi Skor & Kualifikasi</h2>
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
                                        label: 'Periode EDOM',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.periodName}</b>
                                                <small className="db-hint">{row.periodCode ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'program_name', label: 'Program Studi', render: (row) => <span className="db-hint">{row.programName}</span> },
                                    {
                                        key: 'edom_score',
                                        label: 'EDOM',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <span>{formatScore(row.edomScore, 2)}</span>,
                                    },
                                    {
                                        key: 'edom_response_count',
                                        label: 'Respon',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <span className="db-hint">{row.responseCount}</span>,
                                    },
                                    {
                                        key: 'final_score',
                                        label: 'Nilai Akhir',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatScore(row.finalScore)}</b>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${statusTone(row.status)}`.trim()}>{row.statusLabel}</span>,
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
                                searchPlaceholder="Cari nama dosen, periode EDOM, atau program studi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 })}
                                            aria-label="Filter status review performa"
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
                                            aria-label="Filter periode EDOM"
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
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail review">
                                        <Eye size={13} />
                                    </a>
                                ) : undefined}
                                onDeleteRow={can.delete ? (row) => (row.canDelete ? setPending({ kind: 'delete', row }) : undefined) : undefined}
                                showActions={can.view || can.delete}
                                canCreate={false}
                                emptyText="Belum ada hasil review performa yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'calculate' ? 'Hitung ulang performa dosen?' : 'Hapus hasil evaluasi?'}
                    message={pending?.kind === 'calculate'
                        ? 'Sistem akan mengalkulasi ulang nilai evaluasi performa (EDOM, BKD, absensi) untuk seluruh dosen aktif.'
                        : pending?.kind === 'delete'
                            ? `Hasil evaluasi performa milik "${pending.row.lecturerName}" akan dihapus permanen.`
                            : ''}
                    confirmLabel={pending?.kind === 'calculate' ? 'Ya, hitung ulang' : 'Ya, hapus'}
                    danger={pending?.kind === 'delete'}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
