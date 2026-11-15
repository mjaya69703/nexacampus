// Daftar penugasan jabatan pegawai (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { BadgeCheck, BriefcaseBusiness, CheckCircle2, Plus, Target } from 'lucide-react';
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
    employeeName: string;
    employeeNumber: string | null;
    positionName: string;
    positionCode: string | null;
    scopeType: string;
    scopeTypeLabel: string;
    scopeName: string | null;
    startsAt: string | null;
    endsAt: string | null;
    isPrimary: boolean;
    isActive: boolean;
    createdAt: string | null;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; primary: number; scoped: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        is_primary: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function EmployeePositionAssignmentsIndex({ shell, can, stats, data, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.is_primary, filters.is_active].filter((value) => value !== '').length;

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
        visit({ is_primary: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const toggleField = (row: Row, field: 'is_active' | 'is_primary', value: boolean) => {
        router.post(row.toggleUrl, { field, value }, { preserveScroll: true });
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus penugasan terpilih?' : 'Hapus penugasan jabatan?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} penugasan jabatan akan dipindahkan ke tempat sampah.`;
        return `Penugasan ${pending.row.positionName} untuk ${pending.row.employeeName} akan dipindahkan ke tempat sampah.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Penugasan Jabatan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BriefcaseBusiness}
                        eyebrow="Kepegawaian"
                        title="Penugasan Jabatan Pegawai"
                        description="Kelola penugasan struktural pegawai pada jabatan organisasi, lengkap dengan lingkup fakultas, program studi, atau unit kerja serta masa berlaku penugasan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Penugasan</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik penugasan jabatan"
                        items={[
                            { label: 'Total Penugasan', value: stats.total, icon: BriefcaseBusiness },
                            { label: 'Penugasan Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Jabatan Utama', value: stats.primary, icon: BadgeCheck, tone: 'gold' },
                            { label: 'Dengan Scope', value: stats.scoped, icon: Target, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Penugasan Jabatan</h2>
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
                                        key: 'position_name',
                                        label: 'Jabatan',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.positionName}</b>
                                                <small className="db-hint">{row.positionCode ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'scope',
                                        label: 'Lingkup',
                                        render: (row) => (
                                            <span>
                                                <span className="db-badge gray">{row.scopeTypeLabel}</span>
                                                {row.scopeName && <small className="db-hint" style={{ display: 'block' }}>{row.scopeName}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'starts_at',
                                        label: 'Masa Berlaku',
                                        sortable: true,
                                        render: (row) => (
                                            <span className="db-hint">
                                                {row.startsAt ?? '-'} s.d. {row.endsAt ?? 'sekarang'}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_primary',
                                        label: 'Utama',
                                        sortable: true,
                                        render: (row) => (
                                            can.update ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Tandai sebagai jabatan utama">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isPrimary}
                                                        onChange={() => toggleField(row, 'is_primary', !row.isPrimary)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isPrimary ? 'green' : 'gray'}`}>{row.isPrimary ? 'Utama' : 'Bukan'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isPrimary ? 'green' : 'gray'}`}>{row.isPrimary ? 'Utama' : 'Bukan'}</span>
                                            )
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
                                                        onChange={() => toggleField(row, 'is_active', !row.isActive)}
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
                                searchPlaceholder="Cari pegawai, jabatan, unit, fakultas, atau prodi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.is_primary}
                                            onChange={(event) => visit({ is_primary: event.target.value, page: 1 }, true)}
                                            aria-label="Filter jabatan utama"
                                        >
                                            <option value="">Semua jabatan</option>
                                            <option value="1">Jabatan utama</option>
                                            <option value="0">Bukan jabatan utama</option>
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status penugasan"
                                        >
                                            <option value="">Semua status</option>
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
                                emptyText="Belum ada penugasan jabatan yang cocok dengan filter."
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
