// Daftar pengajuan cuti pegawai (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CalendarCheck2, CalendarClock, CalendarPlus, CircleX, Plus } from 'lucide-react';
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
    requestNumber: string;
    employeeName: string;
    employeeNumber: string | null;
    leaveType: string;
    period: string;
    totalDays: number;
    status: string;
    statusLabel: string;
    createdAt: string | null;
    showUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; in_approval: number; approved: number; rejected_cancelled: number };
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
        leave_type_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        statuses: Option[];
        leaveTypes: Option[];
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

function statusTone(status: string): string {
    if (status === 'approved') return 'green';
    if (['rejected', 'cancelled'].includes(status)) return 'red';
    return 'gray';
}

export default function EmployeeLeaveRequestsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.status, filters.leave_type_id].filter((value) => value !== '').length;

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
        visit({ status: '', leave_type_id: '', page: 1 }, true, '');
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus pengajuan cuti terpilih?' : 'Hapus pengajuan cuti?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} pengajuan cuti akan dipindahkan ke tempat sampah.`;
        return `Pengajuan cuti ${pending.row.requestNumber} atas nama ${pending.row.employeeName} akan dipindahkan ke tempat sampah.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Cuti Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Kepegawaian"
                        title="Pengajuan Cuti Pegawai"
                        description="Kelola permohonan cuti tahunan, sakit, dan cuti khusus pegawai. Pengajuan yang membutuhkan persetujuan otomatis masuk ke alur approval kepegawaian."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Ajukan Cuti</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik cuti pegawai"
                        items={[
                            { label: 'Total Pengajuan', value: stats.total, icon: CalendarClock },
                            { label: 'Dalam Persetujuan', value: stats.in_approval, icon: CalendarPlus, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CalendarCheck2, tone: 'green' },
                            { label: 'Ditolak / Batal', value: stats.rejected_cancelled, icon: CircleX, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Pengajuan Cuti</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'request_number',
                                        label: 'Nomor',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.requestNumber}</span>,
                                    },
                                    {
                                        key: 'employee_name',
                                        label: 'Pegawai',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.employeeName}</b>
                                                <small className="db-hint">{row.employeeNumber ?? 'Tanpa nomor pegawai'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'leave_type_name',
                                        label: 'Jenis Cuti',
                                        render: (row) => <span className="db-badge gray">{row.leaveType}</span>,
                                    },
                                    {
                                        key: 'period',
                                        label: 'Periode',
                                        render: (row) => <span className="db-hint">{row.period}</span>,
                                    },
                                    {
                                        key: 'total_days',
                                        label: 'Hari',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{row.totalDays.toFixed(1)}</b>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${statusTone(row.status)}`}>{row.statusLabel}</span>,
                                    },
                                    {
                                        key: 'created_at',
                                        label: 'Diajukan',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.createdAt ?? '-'}</span>,
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
                                searchPlaceholder="Cari nomor, pegawai, atau jenis cuti…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status cuti"
                                        >
                                            <option value="">Semua status</option>
                                            {options.statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.leave_type_id}
                                            onChange={(event) => visit({ leave_type_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis cuti"
                                        >
                                            <option value="">Semua jenis cuti</option>
                                            {options.leaveTypes.map((option) => (
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
                                canUpdate={can.view}
                                editUrl={(row) => row.showUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.view || can.delete}
                                canCreate={false}
                                emptyText="Belum ada pengajuan cuti yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pendingTitle}
                    message={pendingMessage}
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
