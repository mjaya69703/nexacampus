// Daftar event & agenda alumni — kit CRUD shared (Inertia React).
import { Head, router } from '@inertiajs/react';
import { CalendarDays, Globe2, MapPin, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    title: string;
    eventType: string;
    eventTypeLabel: string;
    eventDate: string | null;
    eventDateLabel: string | null;
    endDateLabel: string | null;
    registrationDeadlineLabel: string | null;
    registrationOpen: boolean;
    location: string | null;
    isOnline: boolean;
    meetingUrl: string | null;
    maxParticipants: number | null;
    participantsCount: number;
    slotsLeft: number | null;
    posterUrl: string | null;
    isPublished: boolean;
    createdAt: string | null;
    showUrl: string | null;
    editUrl: string | null;
    deleteUrl: string;
    restoreUrl: string;
    forceUrl: string;
    toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; restore: boolean; toggle: boolean; view: boolean };
    stats: { total: number; published: number; online: number; upcoming: number; participants: number; trashed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        event_type: string;
        is_online: string;
        is_published: string;
        period: string;
        sort: string;
        direction: 'asc' | 'desc';
        mode: 'all' | 'trash';
        perPage: number;
    };
    eventTypeOptions: Record<string, string>;
    urls: {
        index: string;
        create: string;
        export: string;
        bulkDestroy: string;
        bulkRestore: string;
        bulkForceDestroy: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined));
}

const MODAL_COPY: Record<Exclude<Pending, null>['kind'], { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    delete: {
        title: () => 'Hapus event alumni?',
        message: (n) => `"${n}" dipindah ke sampah. Data pendaftar tetap tersimpan.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    restore: {
        title: () => 'Pulihkan event alumni?',
        message: (n) => `"${n}" kembali tampil di daftar event.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    force: {
        title: () => 'Hapus permanen?',
        message: (n) => `"${n}" dihapus selamanya beserta posternya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
    'bulk-delete': {
        title: () => 'Hapus event terpilih?',
        message: (n) => `${n} event dipindah ke sampah.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
    'bulk-restore': {
        title: () => 'Pulihkan event terpilih?',
        message: (n) => `${n} event kembali tampil di daftar.`,
        confirm: 'Ya, pulihkan',
        danger: false,
    },
    'bulk-force': {
        title: () => 'Hapus permanen event terpilih?',
        message: (n) => `${n} event dihapus selamanya.`,
        confirm: 'Ya, hapus permanen',
        danger: true,
    },
};

export default function AlumniEventIndex({ shell, can, stats, data, filters, eventTypeOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const isTrash = filters.mode === 'trash';

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [filters.event_type, filters.is_online, filters.is_published, filters.period]
        .filter((v) => v !== '').length;

    const resetFilters = () => visit({ event_type: '', is_online: '', is_published: '', period: '', page: 1 }, true);

    const toggleSort = (key: string) => {
        visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const togglePublished = (row: Row) => {
        router.post(row.toggleUrl, { is_published: !row.isPublished }, { preserveScroll: true });
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        switch (pending.kind) {
            case 'delete':
                router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'restore':
                router.post(pending.row.restoreUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'force':
                router.delete(pending.row.forceUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-delete':
                router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-restore':
                router.post(urls.bulkRestore, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-force':
                router.post(urls.bulkForceDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const copy = pending ? MODAL_COPY[pending.kind] : null;
    const copyName = pending && 'row' in pending ? pending.row.title : String(selected.length);

    return (
        <AdminShell shell={shell}>
            <Head title={`Event Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Alumni"
                        title="Agenda & Kegiatan Alumni"
                        description="Kelola webinar, temu alumni, job fair, dan kegiatan lain beserta publikasi serta kuota pesertanya."
                        badges={[`${stats.total} event`, `${stats.published} terbit`, `${stats.participants} peserta`]}
                        actions={can.create ? <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Event</a> : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik event">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total event</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{stats.published}</span><span className="db-stat-label">Terbit</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Globe2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.online}</span><span className="db-stat-label">Online</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.participants}</span><span className="db-stat-label">Peserta terdaftar</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Event Alumni</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button type="button" role="tab" aria-selected={!isTrash} className={`crud-tab${!isTrash ? ' active' : ''}`} onClick={() => visit({ mode: 'all', page: 1 }, true)}>
                                    Semua <span className="db-badge gray">{stats.total}</span>
                                </button>
                                <button type="button" role="tab" aria-selected={isTrash} className={`crud-tab${isTrash ? ' active' : ''}`} onClick={() => visit({ mode: 'trash', page: 1 }, true)}>
                                    <Trash2 size={13} /> Sampah <span className="db-badge gray">{stats.trashed}</span>
                                </button>
                            </div>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'title', label: 'Event', sortable: true,
                                        render: (row) => (
                                            <span>
                                                {row.showUrl ? (
                                                    <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-brand)', fontWeight: 700, fontSize: 13 }}>{row.title}</a>
                                                ) : (
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                )}
                                                <small className="db-hint">{row.eventTypeLabel}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'event_date', label: 'Jadwal', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.eventDateLabel ?? '-'}</b>
                                                <small className="db-hint">{row.endDateLabel ? `Selesai ${row.endDateLabel}` : (row.registrationDeadlineLabel ? `Daftar s.d. ${row.registrationDeadlineLabel}` : 'Tanpa batas daftar')}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'location', label: 'Lokasi',
                                        render: (row) => (
                                            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                {row.isOnline ? <Globe2 size={13} /> : <MapPin size={13} />}
                                                <span>{row.isOnline ? 'Online' : (row.location ?? '-')}</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'participants_count', label: 'Peserta', align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)' }}>{row.participantsCount}</b>
                                                <small className="db-hint">{row.maxParticipants ? `dari ${row.maxParticipants}` : 'tanpa kuota'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_published', label: 'Publikasi', sortable: true,
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status publikasi">
                                                    <input type="checkbox" checked={row.isPublished} onChange={() => togglePublished(row)} style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }} />
                                                    <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Terbit' : 'Draft'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Terbit' : 'Draft'}</span>
                                            )
                                        ),
                                    },
                                    {
                                        key: 'registration', label: 'Pendaftaran',
                                        render: (row) => <span className={`db-badge ${row.registrationOpen ? 'green' : 'gray'}`}>{row.registrationOpen ? 'Dibuka' : 'Ditutup'}</span>,
                                    },
                                    { key: 'created_at', label: 'Dibuat', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari judul, lokasi, deskripsi…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select className="db-input" value={filters.event_type} onChange={(e) => visit({ event_type: e.target.value, page: 1 }, true)} aria-label="Filter tipe event">
                                            <option value="">Semua tipe</option>
                                            {Object.entries(eventTypeOptions).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.period} onChange={(e) => visit({ period: e.target.value, page: 1 }, true)} aria-label="Filter periode">
                                            <option value="">Semua waktu</option>
                                            <option value="upcoming">Akan datang</option>
                                            <option value="past">Sudah lewat</option>
                                        </select>
                                        <select className="db-input" value={filters.is_online} onChange={(e) => visit({ is_online: e.target.value, page: 1 }, true)} aria-label="Filter mode">
                                            <option value="">Semua mode</option>
                                            <option value="1">Online</option>
                                            <option value="0">Offline</option>
                                        </select>
                                        <select className="db-input" value={filters.is_published} onChange={(e) => visit({ is_published: e.target.value, page: 1 }, true)} aria-label="Filter publikasi">
                                            <option value="">Semua publikasi</option>
                                            <option value="1">Terbit</option>
                                            <option value="0">Draft</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: isTrash ? 'bulk-force' : 'bulk-delete' })}
                                bulkLabel={isTrash ? 'Hapus permanen terpilih' : 'Hapus terpilih'}
                                canRestore={isTrash && can.restore}
                                onBulkRestore={isTrash ? () => setPending({ kind: 'bulk-restore' }) : undefined}
                                canUpdate={!isTrash && can.update}
                                editUrl={(row) => row.editUrl ?? urls.index}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                customActions={(row) => row.showUrl ? <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail"><CalendarDays size={13} /></a> : null}
                                showActions={(!isTrash && (can.update || can.view)) || can.restore || can.delete}
                                canCreate={false}
                                createLabel="Tambah Event"
                                exportHref={exportHref}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada event yang cocok dengan filter.'}
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy ? copy.title(copyName) : ''}
                    message={copy ? copy.message(copyName) : ''}
                    confirmLabel={copy?.confirm ?? 'Ya'}
                    danger={copy?.danger ?? true}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
