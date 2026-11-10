// Daftar catatan absensi pegawai (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CalendarCheck2, CalendarClock, Clock3, Fingerprint, Plus } from 'lucide-react';
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
    employeeName: string;
    employeeNumber: string | null;
    attendanceDate: string | null;
    status: string;
    statusLabel: string;
    checkIn: string | null;
    checkOut: string | null;
    workMinutes: number;
    workUnit: string | null;
    location: string | null;
    locationStatus: string | null;
    locationStatusLabel: string;
    photoCount: number;
    source: string;
    createdAt: string | null;
    showUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; view: boolean; delete: boolean };
    stats: { total: number; today: number; present_today: number; late_today: number };
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
        date: string;
        work_unit_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        statuses: Option[];
        workUnits: Option[];
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

function statusTone(status: string): string {
    if (status === 'present') return 'green';
    if (status === 'late') return 'gold';
    if (status === 'absent') return 'red';
    if (['leave', 'sick'].includes(status)) return 'gray';
    return 'gray';
}

function formatMinutes(minutes: number): string {
    if (!minutes || minutes <= 0) return '-';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) return `${rest} mnt`;

    return `${hours} jam${rest > 0 ? ` ${rest} mnt` : ''}`;
}

export default function EmployeeAttendanceRecordsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.status, filters.date, filters.work_unit_id].filter((value) => value !== '').length;

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
        visit({ status: '', date: '', work_unit_id: '', page: 1 }, true, '');
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus catatan absensi terpilih?' : 'Hapus catatan absensi?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} catatan absensi akan dipindahkan ke tempat sampah.`;
        return `Catatan absensi ${pending.row.employeeName} tanggal ${pending.row.attendanceDate ?? '-'} akan dipindahkan ke tempat sampah.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Fingerprint}
                        eyebrow="Kepegawaian"
                        title="Absensi Pegawai"
                        description="Pantau presensi harian pegawai beserta lokasi geofence, bukti foto, dan durasi kerja. Input manual tersedia untuk koreksi data absensi."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Input Absensi</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik absensi pegawai"
                        items={[
                            { label: 'Total Catatan', value: stats.total, icon: Fingerprint },
                            { label: 'Absensi Hari Ini', value: stats.today, icon: CalendarClock, tone: 'gold' },
                            { label: 'Hadir Hari Ini', value: stats.present_today, icon: CalendarCheck2, tone: 'green' },
                            { label: 'Terlambat Hari Ini', value: stats.late_today, icon: Clock3, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Absensi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
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
                                        key: 'attendance_date',
                                        label: 'Tanggal',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.attendanceDate ?? '-'}</span>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${statusTone(row.status)}`}>{row.statusLabel}</span>,
                                    },
                                    {
                                        key: 'check_in_at',
                                        label: 'Check-In / Out',
                                        render: (row) => (
                                            <span className="db-hint">
                                                {row.checkIn ?? '-'} s.d. {row.checkOut ?? '-'}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'work_minutes',
                                        label: 'Durasi',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatMinutes(row.workMinutes)}</b>,
                                    },
                                    {
                                        key: 'work_unit_id',
                                        label: 'Unit Kerja',
                                        render: (row) => <span className="db-hint">{row.workUnit ?? '-'}</span>,
                                    },
                                    {
                                        key: 'location_status',
                                        label: 'Lokasi',
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${row.locationStatus === 'inside_radius' ? 'green' : row.locationStatus === 'outside_radius' || row.locationStatus === 'gps_missing' ? 'red' : 'gray'}`}>
                                                    {row.locationStatusLabel}
                                                </span>
                                                {row.location && <small className="db-hint" style={{ display: 'block' }}>{row.location}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'photo',
                                        label: 'Bukti',
                                        align: 'right',
                                        render: (row) => (
                                            <span className="db-hint">{row.photoCount > 0 ? `${row.photoCount} foto` : '-'}</span>
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
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama, nomor pegawai, unit kerja, atau lokasi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status absensi"
                                        >
                                            <option value="">Semua status</option>
                                            {options.statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="date"
                                            className="db-input"
                                            value={filters.date}
                                            onChange={(event) => visit({ date: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tanggal absensi"
                                        />
                                        <select
                                            className="db-input"
                                            value={filters.work_unit_id}
                                            onChange={(event) => visit({ work_unit_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter unit kerja"
                                        >
                                            <option value="">Semua unit kerja</option>
                                            {options.workUnits.map((option) => (
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
                                emptyText="Belum ada catatan absensi yang cocok dengan filter."
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
