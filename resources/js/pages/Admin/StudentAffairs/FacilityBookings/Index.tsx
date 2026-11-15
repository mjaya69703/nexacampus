// Booking fasilitas organisasi mahasiswa (admin).
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarDays, CheckCircle2, Clock3, Eye, MapPin, Users, XCircle } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import { buildQuery, StudentAffairsModal, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    reservationNumber: string;
    title: string;
    organization: string;
    requester: string;
    room: string | null;
    building: string | null;
    date: string | null;
    time: string;
    attendeeCount: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    rejectionReason: string | null;
    showUrl: string;
    approveUrl: string;
    rejectUrl: string;
    cancelUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { approve: boolean; delete: boolean };
    stats: { total: number; pending: number; approved: number; rejected: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        status: string;
        organization: string | number;
        date: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        statuses: Record<string, string>;
        organizations: Array<{ id: number; name: string }>;
    };
    urls: { index: string; bulkDestroy: string; campusReservations: string };
};

type Pending =
    | { kind: 'approve' | 'cancel' | 'delete'; row: Row }
    | { kind: 'reject'; row: Row }
    | { kind: 'bulk' }
    | null;

const MODAL_COPY: Record<'approve' | 'cancel' | 'delete', { title: (n: string) => string; message: (n: string) => string; confirm: string; danger: boolean }> = {
    approve: {
        title: (n) => `Setujui booking ${n}?`,
        message: (n) => `Booking "${n}" akan disetujui. Sistem memeriksa bentrok jadwal sebelum menyetujui.`,
        confirm: 'Ya, setujui',
        danger: false,
    },
    cancel: {
        title: (n) => `Batalkan booking ${n}?`,
        message: (n) => `Booking "${n}" akan dibatalkan dan tidak lagi memakai ruangan.`,
        confirm: 'Ya, batalkan',
        danger: true,
    },
    delete: {
        title: (n) => `Hapus booking ${n}?`,
        message: (n) => `Booking "${n}" akan dihapus dari daftar.`,
        confirm: 'Ya, hapus',
        danger: true,
    },
};

export default function FacilityBookingsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const activeFilterCount = [filters.status, filters.organization, filters.date]
        .filter((value) => value !== '').length;

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirm = () => {
        if (!pending || pending.kind === 'reject') return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        if (pending.kind === 'bulk') {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
            return;
        }

        if (pending.kind === 'approve') {
            router.post(pending.row.approveUrl, {}, { preserveScroll: true, onFinish: done });
            return;
        }

        if (pending.kind === 'cancel') {
            router.post(pending.row.cancelUrl, {}, { preserveScroll: true, onFinish: done });
            return;
        }

        router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
    };

    const submitReject = (event: FormEvent) => {
        event.preventDefault();
        if (!pending || pending.kind !== 'reject') return;

        setProcessing(true);
        router.post(
            pending.row.rejectUrl,
            { rejection_reason: rejectionReason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                    setRejectionReason('');
                },
            },
        );
    };

    const isBulk = pending?.kind === 'bulk';
    const actionRow = pending && pending.kind !== 'reject' && pending.kind !== 'bulk' ? pending.row : null;
    const copy = pending && pending.kind !== 'reject' && pending.kind !== 'bulk' ? MODAL_COPY[pending.kind] : null;

    return (
        <AdminShell shell={shell}>
            <Head title={`Booking Fasilitas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Kemahasiswaan"
                        title="Booking Fasilitas"
                        description="Tinjau permohonan pemakaian ruangan oleh organisasi mahasiswa, setujui, atau tolak dengan alasan."
                        actions={(
                            <a className="db-btn ghost" href={urls.campusReservations}>
                                <CalendarDays size={15} /> Reservasi Kampus
                            </a>
                        )}
                    />

                    <StudentAffairsStats
                        label="Statistik booking fasilitas"
                        items={[
                            { label: 'Total booking', value: stats.total, icon: Building2 },
                            { label: 'Menunggu', value: stats.pending, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Ditolak', value: stats.rejected, icon: XCircle, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar Booking</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Booking',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>
                                                    {row.title}
                                                </a>
                                                <small className="db-hint">{row.reservationNumber} · {row.organization}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'requester',
                                        label: 'Pemohon',
                                        render: (row) => <span className="db-hint">{row.requester}</span>,
                                    },
                                    {
                                        key: 'room',
                                        label: 'Ruangan',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.room || '—'}</b>
                                                {row.building && (
                                                    <small className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                                                        <MapPin size={11} /> {row.building}
                                                    </small>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'reservation_date',
                                        label: 'Jadwal',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.date || '—'}</b>
                                                <small className="db-hint">{row.time} WIB</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'attendee_count',
                                        label: 'Peserta',
                                        render: (row) => (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                <Users size={12} /> {row.attendeeCount}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        render: (row) => (
                                            <span>
                                                <span className={`db-badge ${toneClass(row.statusTone)}`}>{row.statusLabel}</span>
                                                {row.rejectionReason && (
                                                    <small className="db-hint" style={{ display: 'block', marginTop: 4 }}>{row.rejectionReason}</small>
                                                )}
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
                                searchPlaceholder="Cari nomor booking, judul, atau organisasi…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ status: '', organization: '', date: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status booking"
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
                                            aria-label="Filter organisasi"
                                        >
                                            <option value="">Semua organisasi</option>
                                            {options.organizations.map((organization) => (
                                                <option key={organization.id} value={organization.id}>{organization.name}</option>
                                            ))}
                                        </select>
                                        <input
                                            className="db-input"
                                            type="date"
                                            value={filters.date}
                                            onChange={(event) => visit({ date: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tanggal booking"
                                        />
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                customActions={(row) => (
                                    <>
                                        <a className="db-btn ghost sm" href={row.showUrl} title="Detail reservasi">
                                            <Eye size={13} />
                                        </a>
                                        {can.approve && row.status === 'pending' && (
                                            <>
                                                <button className="db-btn ghost sm" type="button" title="Setujui" onClick={() => setPending({ kind: 'approve', row })}>
                                                    <CheckCircle2 size={13} />
                                                </button>
                                                <button className="db-btn ghost sm" type="button" title="Tolak" onClick={() => { setRejectionReason(''); setPending({ kind: 'reject', row }); }}>
                                                    <XCircle size={13} />
                                                </button>
                                            </>
                                        )}
                                        {can.approve && ['pending', 'approved'].includes(row.status) && (
                                            <button className="db-btn ghost sm" type="button" title="Batalkan" onClick={() => setPending({ kind: 'cancel', row })}>
                                                <Clock3 size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada booking fasilitas yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null && pending.kind !== 'reject'}
                    title={isBulk ? 'Hapus booking terpilih?' : copy && actionRow ? copy.title(actionRow.reservationNumber) : ''}
                    message={isBulk
                        ? `${selected.length} booking akan dihapus dari daftar.`
                        : copy && actionRow ? copy.message(actionRow.reservationNumber) : ''}
                    confirmLabel={isBulk ? 'Ya, hapus semua' : copy?.confirm ?? 'Lanjutkan'}
                    danger={isBulk ? true : copy?.danger}
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />

                {pending?.kind === 'reject' && (
                    <StudentAffairsModal
                        title={`Tolak booking ${pending.row.reservationNumber}`}
                        description="Berikan alasan penolakan agar organisasi dapat memperbaiki pengajuan."
                        onClose={() => setPending(null)}
                    >
                        <form onSubmit={submitReject} style={{ display: 'grid', gap: 12 }}>
                            <TextareaField
                                label="Alasan penolakan"
                                required
                                rows={3}
                                value={rejectionReason}
                                onChange={(event) => setRejectionReason(event.target.value)}
                                placeholder="Contoh: ruangan sudah dipakai kegiatan akademik pada jam tersebut."
                            />
                            <div className="crud-actions">
                                <button className="db-btn ghost" type="button" onClick={() => setPending(null)}>Batal</button>
                                <button className="db-btn danger" type="submit" disabled={processing || rejectionReason.trim() === ''}>Tolak Booking</button>
                            </div>
                        </form>
                    </StudentAffairsModal>
                )}
            </div>
        </AdminShell>
    );
}
