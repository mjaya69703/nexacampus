// Daftar profil pegawai (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { BriefcaseBusiness, CheckCircle2, CirclePause, Plus, Users } from 'lucide-react';
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
    name: string;
    email: string | null;
    username: string | null;
    employeeNumber: string | null;
    employmentType: string;
    employmentTypeLabel: string;
    employmentStatus: string;
    employmentStatusLabel: string;
    workUnit: string | null;
    positionsCount: number;
    isActive: boolean;
    createdAt: string | null;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; inactive: number; positions: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        employment_type: string;
        employment_status: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        employmentTypes: Option[];
        employmentStatuses: Option[];
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function EmployeeProfilesIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.employment_type, filters.employment_status, filters.is_active].filter((value) => value !== '').length;

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
        visit({ employment_type: '', employment_status: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus profil pegawai terpilih?' : 'Hapus profil pegawai?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} profil pegawai akan dipindahkan ke tempat sampah.`;
        return `Profil "${pending.row.name}" akan dipindahkan ke tempat sampah. Profil yang masih memiliki penugasan jabatan atau pengajuan cuti tidak dapat dihapus.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Kepegawaian"
                        title="Profil Pegawai"
                        description="Kelola data kepegawaian seluruh dosen, tenaga kependidikan, dan staf administrasi: nomor pegawai, jenis kepegawaian, status kerja, serta unit kerja utama."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Pegawai</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik pegawai"
                        items={[
                            { label: 'Total Pegawai', value: stats.total, icon: Users },
                            { label: 'Pegawai Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Pegawai Nonaktif', value: stats.inactive, icon: CirclePause, tone: 'red' },
                            { label: 'Total Penugasan Aktif', value: stats.positions, icon: BriefcaseBusiness, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Pegawai</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'employee_number',
                                        label: 'Pegawai',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">
                                                    {row.employeeNumber ?? 'Nomor pegawai belum diisi'}
                                                    {row.email ? ` · ${row.email}` : ''}
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'employment_type',
                                        label: 'Jenis',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.employmentTypeLabel}</span>,
                                    },
                                    {
                                        key: 'employment_status',
                                        label: 'Status Kerja',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.employmentStatus === 'active' ? 'green' : 'gray'}`}>
                                                {row.employmentStatusLabel}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'primary_work_unit',
                                        label: 'Unit Kerja',
                                        render: (row) => <span className="db-hint">{row.workUnit ?? '-'}</span>,
                                    },
                                    {
                                        key: 'active_position_assignments_count',
                                        label: 'Penugasan',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (
                                            <b style={{ color: row.positionsCount > 0 ? 'var(--db-brand)' : 'var(--db-muted)', fontSize: 13 }}>
                                                {row.positionsCount} aktif
                                            </b>
                                        ),
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Aktif',
                                        sortable: true,
                                        render: (row) => (
                                            can.update ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isActive ? 'Nonaktifkan' : 'Aktifkan'}>
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleActive(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                            )
                                        ),
                                    },
                                    {
                                        key: 'created_at',
                                        label: 'Dibuat',
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
                                searchPlaceholder="Cari nama, email, NIP, atau unit kerja…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.employment_type}
                                            onChange={(event) => visit({ employment_type: event.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis kepegawaian"
                                        >
                                            <option value="">Semua jenis</option>
                                            {options.employmentTypes.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.employment_status}
                                            onChange={(event) => visit({ employment_status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status kerja"
                                        >
                                            <option value="">Semua status kerja</option>
                                            {options.employmentStatuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status aktif"
                                        >
                                            <option value="">Semua keaktifan</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada pegawai yang cocok dengan filter."
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
