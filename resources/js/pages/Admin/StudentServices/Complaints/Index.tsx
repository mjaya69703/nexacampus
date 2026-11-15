// Daftar pengaduan mahasiswa (ticketing admin).
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Eye, MessageSquareWarning, Timer, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { PriorityBadge, ServiceStats, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { buildQuery } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    ticketNumber: string;
    subject: string;
    student: string;
    nim: string;
    category: string;
    workUnit: string;
    assignedUser: string;
    priority: string;
    status: string;
    dueAt: string | null;
    slaLabel: string;
    slaTone: string;
    lastMessageAt: string | null;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; assign: boolean };
    stats: { total: number; open: number; waitingStudent: number; overdue: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        student_complaint_category_id: string | number;
        assigned_work_unit_id: string | number;
        status: string;
        priority: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    categories: { id: number; name: string }[];
    workUnits: { id: number; name: string }[];
    statuses: { id: string; name: string }[];
    priorities: { id: string; name: string }[];
    urls: { index: string };
};

export default function ComplaintsIndex({
    shell,
    stats,
    data,
    filters,
    categories,
    workUnits,
    statuses,
    priorities,
    urls,
}: Props) {
    const [search, setSearch] = useState(filters.q);

    const activeFilterCount = [
        filters.student_complaint_category_id,
        filters.assigned_work_unit_id,
        filters.status,
        filters.priority,
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
            student_complaint_category_id: '',
            assigned_work_unit_id: '',
            status: '',
            priority: '',
            page: 1,
        }, '');
    };

    const statusLabels = Object.fromEntries(statuses.map((status) => [status.id, status.name]));

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengaduan Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MessageSquareWarning}
                        eyebrow="Layanan Mahasiswa"
                        title="Pengaduan Mahasiswa"
                        description="Pantau tiket pengaduan, prioritas, SLA, penugasan unit kerja, dan penyelesaian masalah mahasiswa."
                    />

                    <ServiceStats
                        label="Statistik pengaduan"
                        items={[
                            { label: 'Total Tiket', value: stats.total, icon: MessageSquareWarning },
                            { label: 'Terbuka', value: stats.open, icon: Timer, tone: 'gold' },
                            { label: 'Menunggu Mahasiswa', value: stats.waitingStudent, icon: UserCheck, tone: 'gold' },
                            { label: 'Lewat SLA', value: stats.overdue, icon: AlertTriangle, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengaduan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'ticket_number',
                                        label: 'Tiket',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b className="crud-name">{row.ticketNumber}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.subject}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        render: (row) => (
                                            <span>
                                                <span style={{ display: 'block', fontSize: 12.5 }}>{row.category}</span>
                                                <small className="db-hint">{row.workUnit}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'priority',
                                        label: 'Prioritas',
                                        sortable: true,
                                        render: (row) => <PriorityBadge priority={row.priority} />,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <StatusBadge status={row.status} label={statusLabels[row.status]} />,
                                    },
                                    {
                                        key: 'due_at',
                                        label: 'SLA',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.slaTone}`}>{row.slaLabel}</span>
                                                {row.dueAt && <small className="db-hint" style={{ display: 'block' }}>{row.dueAt}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'last_message_at',
                                        label: 'Aktivitas',
                                        sortable: true,
                                        render: (row) => row.lastMessageAt ?? '-',
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
                                searchPlaceholder="Cari nomor tiket, subjek, atau mahasiswa…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={String(filters.student_complaint_category_id)}
                                            onChange={(event) => visit({ student_complaint_category_id: event.target.value, page: 1 })}
                                            aria-label="Filter kategori"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categories.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={String(filters.assigned_work_unit_id)}
                                            onChange={(event) => visit({ assigned_work_unit_id: event.target.value, page: 1 })}
                                            aria-label="Filter unit kerja"
                                        >
                                            <option value="">Semua unit kerja</option>
                                            {workUnits.map((option) => (
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
                                        <select
                                            className="db-input"
                                            value={filters.priority}
                                            onChange={(event) => visit({ priority: event.target.value, page: 1 })}
                                            aria-label="Filter prioritas"
                                        >
                                            <option value="">Semua prioritas</option>
                                            {priorities.map((option) => (
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
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Buka tiket">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada pengaduan yang cocok dengan filter."
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
