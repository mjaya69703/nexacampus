// Daftar organisasi mahasiswa (admin).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Eye, Plus, Trophy, UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, initialsFor, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    name: string;
    type: string;
    typeLabel: string;
    typeShort: string;
    typeTone: string;
    faculty: string | null;
    studyProgram: string | null;
    advisor: string | null;
    membersCount: number;
    activeMembersCount: number;
    foundedAt: string | null;
    isActive: boolean;
    logoUrl: string | null;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; members: number; achievements: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; type: string; is_active: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    options: { types: Record<string, string> };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function OrganizationsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const activeFilterCount = [filters.type, filters.is_active].filter((value) => value !== '').length;

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirm = () => {
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

    return (
        <AdminShell shell={shell}>
            <Head title={`Organisasi Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Kemahasiswaan"
                        title="Organisasi Mahasiswa"
                        description="Kelola ormawa, pembina, profil organisasi, dan keanggotaan mahasiswa."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Organisasi</a>
                        ) : undefined}
                    />

                    <StudentAffairsStats
                        label="Statistik organisasi mahasiswa"
                        items={[
                            { label: 'Total ormawa', value: stats.total, icon: Users },
                            { label: 'Ormawa aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Anggota aktif', value: stats.members, icon: UserCheck },
                            { label: 'Prestasi tercatat', value: stats.achievements, icon: Trophy, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Organisasi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Organisasi',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                {row.logoUrl ? (
                                                    <img
                                                        src={row.logoUrl}
                                                        alt=""
                                                        style={{ width: 38, height: 38, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--db-line)' }}
                                                    />
                                                ) : (
                                                    <span className="db-stat-icon" style={{ width: 38, height: 38, borderRadius: 10, fontSize: 12, fontWeight: 800 }}>
                                                        {initialsFor(row.name)}
                                                    </span>
                                                )}
                                                <span>
                                                    <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>
                                                        {row.name}
                                                    </a>
                                                    <small className="db-hint">{row.typeLabel}</small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'type',
                                        label: 'Jenis',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${toneClass(row.typeTone)}`}>{row.typeShort}</span>,
                                    },
                                    {
                                        key: 'faculty',
                                        label: 'Fakultas / Prodi',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.faculty || 'Universitas'}</b>
                                                <small className="db-hint">{row.studyProgram || 'Lintas program studi'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'advisor',
                                        label: 'Pembina',
                                        render: (row) => <span className="db-hint">{row.advisor || '—'}</span>,
                                    },
                                    {
                                        key: 'members',
                                        label: 'Anggota',
                                        align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ color: 'var(--db-heading)' }}>{row.activeMembersCount}</b>
                                                <span className="db-hint"> / {row.membersCount} aktif</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>
                                                {row.isActive ? 'Aktif' : 'Nonaktif'}
                                            </span>
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
                                searchPlaceholder="Cari nama atau deskripsi organisasi…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ type: '', is_active: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(event) => visit({ type: event.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis organisasi"
                                        >
                                            <option value="">Semua jenis</option>
                                            {Object.entries(options.types).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status organisasi"
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
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Detail organisasi">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada organisasi yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus organisasi terpilih?' : 'Hapus organisasi?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} organisasi akan dihapus. Organisasi yang masih memiliki prestasi akan dilewati.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.name}" akan dihapus beserta data keanggotaannya.`
                            : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
