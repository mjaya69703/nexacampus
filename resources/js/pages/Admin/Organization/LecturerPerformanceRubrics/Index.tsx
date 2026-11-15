// Daftar rubrik performa dosen (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { Award, BadgeCheck, Plus, Scale, ShieldCheck, Target, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    code: string;
    name: string;
    edomWeight: number;
    teachingWeight: number;
    attendanceWeight: number;
    workloadWeight: number;
    totalWeight: number;
    minimumResponses: number;
    targetWorkloadSks: number;
    isActive: boolean;
    notes: string | null;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; activeCode: string; activeName: string; targetSks: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    urls: { index: string; create: string };
};

type Pending = { kind: 'activate'; row: Row } | { kind: 'delete'; row: Row } | null;

const weightLabel = (value: number) => `${Number.isInteger(value) ? value : value.toFixed(2)}%`;

const weightTone = (value: number) => (value >= 30 ? 'green' : value >= 20 ? 'amber' : 'gray');

export default function LecturerPerformanceRubricsIndex({ shell, can, stats, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = filters.is_active !== '' ? 1 : 0;

    const visit = (overrides: Record<string, string | number | undefined>, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ is_active: '', page: 1 }, '');
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);

        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending.kind === 'activate') {
            router.post(pending.row.toggleUrl, { is_active: true }, { preserveScroll: true, onFinish: done });
            return;
        }

        router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Rubrik Performa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Scale}
                        eyebrow="Kepegawaian"
                        title="Rubrik Performa Dosen"
                        description="Konfigurasikan persentase bobot penilaian EDOM, kepatuhan jadwal mengajar, kedisiplinan absensi, dan realisasi BKD untuk menghitung skor akhir kinerja tri dharma."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Rubrik</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik rubrik performa"
                        items={[
                            { label: 'Total Rubrik', value: stats.total, icon: Scale },
                            { label: 'Rubrik Aktif', value: stats.activeCode, icon: BadgeCheck, tone: 'green' },
                            { label: 'Target BKD Aktif', value: `${stats.targetSks} SKS`, icon: Target, tone: 'gold' },
                            { label: 'Standar Aktif', value: stats.activeName, icon: Award },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Rubrik Penilaian</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Rubrik',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code}{row.notes ? ` · ${row.notes}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'weights',
                                        label: 'Komposisi Bobot',
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', flexWrap: 'wrap', gap: 4 }}>
                                                <span className={`db-badge ${weightTone(row.edomWeight)}`} title="Bobot EDOM">EDOM {weightLabel(row.edomWeight)}</span>
                                                <span className={`db-badge ${weightTone(row.teachingWeight)}`} title="Bobot mengajar">Ajar {weightLabel(row.teachingWeight)}</span>
                                                <span className={`db-badge ${weightTone(row.attendanceWeight)}`} title="Bobot absensi">Absen {weightLabel(row.attendanceWeight)}</span>
                                                <span className={`db-badge ${weightTone(row.workloadWeight)}`} title="Bobot BKD">BKD {weightLabel(row.workloadWeight)}</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'total_weight',
                                        label: 'Total',
                                        align: 'right',
                                        render: (row) => (
                                            <b style={{ color: row.totalWeight === 100 ? 'var(--db-green, #1f9d63)' : 'var(--db-brand)', fontSize: 13 }}>
                                                {weightLabel(row.totalWeight)}
                                            </b>
                                        ),
                                    },
                                    {
                                        key: 'minimum_responses',
                                        label: 'Min. Respon',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => row.minimumResponses,
                                    },
                                    {
                                        key: 'target_workload_sks',
                                        label: 'Target BKD',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => `${row.targetWorkloadSks} SKS`,
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif Digunakan' : 'Nonaktif'}</span>
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
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari nama, kode, atau catatan rubrik…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 })}
                                            aria-label="Filter status rubrik"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                customActions={(row) => (
                                    <>
                                        {can.update && !row.isActive && (
                                            <button
                                                className="db-btn primary sm"
                                                type="button"
                                                title="Aktifkan rubrik ini"
                                                onClick={() => setPending({ kind: 'activate', row })}
                                            >
                                                <ShieldCheck size={13} />
                                            </button>
                                        )}
                                        {can.delete && !row.isActive && (
                                            <button
                                                className="db-btn danger sm"
                                                type="button"
                                                title="Hapus rubrik"
                                                onClick={() => setPending({ kind: 'delete', row })}
                                            >
                                                <Trash2 size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions={can.update || can.delete}
                                selectable={false}
                                canCreate={false}
                                emptyText="Belum ada rubrik performa yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'activate' ? 'Aktifkan rubrik ini?' : 'Hapus rubrik performa?'}
                    message={pending?.kind === 'activate'
                        ? `"${pending.row.name}" akan dijadikan standar penilaian performa dosen. Rubrik aktif saat ini otomatis dinonaktifkan.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus permanen dari daftar rubrik.`
                            : ''}
                    confirmLabel={pending?.kind === 'activate' ? 'Ya, aktifkan' : 'Ya, hapus'}
                    danger={pending?.kind === 'delete'}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
