// Daftar event kemahasiswaan (admin).
import { Head, router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Clock3, Eye, MapPin, Plus, Star, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    title: string;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    organization: string | null;
    startsAt: string | null;
    location: string | null;
    points: number;
    quota: number | null;
    registered: number;
    attended: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    isFeatured: boolean;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; publish: boolean };
    stats: { total: number; published: number; upcoming: number; completed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        category: string;
        status: string;
        organization: string | number;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        categories: Record<string, string>;
        statuses: Record<string, string>;
        organizations: Array<{ id: number; name: string }>;
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function EventsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
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

    const activeFilterCount = [filters.category, filters.status, filters.organization]
        .filter((value) => value !== '').length;

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
            <Head title={`Event Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kemahasiswaan"
                        title="Event Mahasiswa"
                        description="Kelola agenda kemahasiswaan, pendaftaran peserta, kehadiran, dan poin keikutsertaan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Event</a>
                        ) : undefined}
                    />

                    <StudentAffairsStats
                        label="Statistik event mahasiswa"
                        items={[
                            { label: 'Total event', value: stats.total, icon: CalendarDays },
                            { label: 'Dipublikasikan', value: stats.published, icon: CheckCircle2, tone: 'green' },
                            { label: 'Akan datang', value: stats.upcoming, icon: Clock3, tone: 'gold' },
                            { label: 'Selesai', value: stats.completed, icon: Star, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Event</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Event',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                <span className={`db-stat-icon ${row.isFeatured ? 'gold' : ''}`.trim()} style={{ width: 36, height: 36, borderRadius: 10 }}>
                                                    {row.isFeatured ? <Star size={16} /> : <CalendarDays size={16} />}
                                                </span>
                                                <span>
                                                    <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>
                                                        {row.title}
                                                    </a>
                                                    <small className="db-hint">{row.organization || 'Lintas organisasi'}</small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${toneClass(row.categoryTone)}`}>{row.categoryLabel}</span>,
                                    },
                                    {
                                        key: 'starts_at',
                                        label: 'Jadwal',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.startsAt || '—'}</b>
                                                {row.location && (
                                                    <small className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                                                        <MapPin size={11} /> {row.location}
                                                    </small>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'registered',
                                        label: 'Peserta',
                                        render: (row) => (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                <Users size={12} /> {row.registered}{row.quota ? ` / ${row.quota}` : ''}
                                                <span className="db-badge green">{row.attended} hadir</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'points',
                                        label: 'Poin',
                                        render: (row) => <span className="db-badge blue">{row.points} poin</span>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${toneClass(row.statusTone)}`}>{row.statusLabel}</span>
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
                                searchPlaceholder="Cari judul, penyelenggara, atau lokasi…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ category: '', status: '', organization: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.category}
                                            onChange={(event) => visit({ category: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori event"
                                        >
                                            <option value="">Semua kategori</option>
                                            {Object.entries(options.categories).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status event"
                                        >
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.organization}
                                            onChange={(event) => visit({ organization: event.target.value, page: 1 }, true)}
                                            aria-label="Filter organisasi penyelenggara"
                                        >
                                            <option value="">Semua organisasi</option>
                                            {options.organizations.map((organization) => (
                                                <option key={organization.id} value={organization.id}>{organization.name}</option>
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
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Detail event">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada event yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus event terpilih?' : 'Hapus event?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} event akan dihapus beserta data pendaftarannya.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.title}" akan dihapus beserta data pendaftarannya.`
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
