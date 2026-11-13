// Daftar reservasi buku perpustakaan (admin): siapkan, serahkan, batalkan.
import { Head, router, useForm } from '@inertiajs/react';
import {
    BookCheck, BookOpen, CalendarCheck2, CheckCircle2, Clock, Hourglass, XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, libraryTone, LibraryModal, LibraryStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    bookTitle: string | null;
    author: string | null;
    member: string;
    username: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    reservedAt: string | null;
    readyAt: string | null;
    expiresAt: string | null;
    copyCode: string | null;
    notes: string | null;
    canReady: boolean;
    canFulfill: boolean;
    canCancel: boolean;
    canDelete: boolean;
    availableCopiesUrl: string;
    readyUrl: string;
    fulfillUrl: string;
    cancelUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    stats: { pending: number; ready: number; fulfilledThisMonth: number; expired: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; status: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    options: { statuses: Record<string, string> };
    urls: { index: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'fulfill'; row: Row } | null;

function ReadyModal({ reservation, onClose }: { reservation: Row; onClose: () => void }) {
    const [options, setOptions] = useState<Array<{ id: number; label: string }>>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const form = useForm({ book_copy_id: '' });

    useEffect(() => {
        let active = true;

        fetch(reservation.availableCopiesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => response.json())
            .then((json: { options?: Array<{ id: number; label: string }> }) => {
                if (!active) return;
                const list = json.options ?? [];
                setOptions(list);
                if (list.length > 0) form.setData('book_copy_id', String(list[0].id));
            })
            .catch(() => {
                if (active) setLoadError('Gagal memuat daftar eksemplar.');
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => { active = false; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reservation.availableCopiesUrl]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reservation.readyUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <LibraryModal
            title="Siapkan Reservasi"
            description={`${reservation.bookTitle ?? 'Buku'} — ${reservation.member}`}
            onClose={onClose}
        >
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                {loading && <p className="db-hint">Memuat eksemplar tersedia…</p>}
                {loadError && <div className="db-error">{loadError}</div>}
                {!loading && !loadError && options.length === 0 && (
                    <div className="db-note warning">
                        <Hourglass size={15} />
                        <span>Tidak ada eksemplar tersedia saat ini. Reservasi tetap menunggu sampai eksemplar dikembalikan.</span>
                    </div>
                )}
                {options.length > 0 && (
                    <div>
                        <label className="crud-label" htmlFor="ready-copy">Eksemplar yang ditahan <span>*</span></label>
                        <select
                            id="ready-copy"
                            className="db-input"
                            value={form.data.book_copy_id}
                            onChange={(event) => form.setData('book_copy_id', event.target.value)}
                        >
                            {options.map((option) => (
                                <option key={option.id} value={option.id}>{option.label}</option>
                            ))}
                        </select>
                        {form.errors.book_copy_id && <div className="db-error">{form.errors.book_copy_id}</div>}
                    </div>
                )}
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing || options.length === 0}>
                        {form.processing ? 'Menyiapkan…' : 'Siapkan & Tahan Eksemplar'}
                    </button>
                </div>
            </form>
        </LibraryModal>
    );
}

function CancelModal({ reservation, onClose }: { reservation: Row; onClose: () => void }) {
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reservation.cancelUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <LibraryModal
            title="Batalkan Reservasi"
            description={`${reservation.bookTitle ?? 'Buku'} — ${reservation.member}`}
            onClose={onClose}
        >
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label" htmlFor="cancel-reason">Alasan pembatalan</label>
                    <textarea
                        id="cancel-reason"
                        className="db-input"
                        rows={3}
                        value={form.data.reason}
                        onChange={(event) => form.setData('reason', event.target.value)}
                        placeholder="Contoh: anggota tidak mengambil dalam masa berlaku."
                    />
                    {form.errors.reason && <div className="db-error">{form.errors.reason}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Kembali</button>
                    <button className="db-btn danger" type="submit" disabled={form.processing}>
                        {form.processing ? 'Membatalkan…' : 'Batalkan Reservasi'}
                    </button>
                </div>
            </form>
        </LibraryModal>
    );
}

export default function ReservationsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [pending, setPending] = useState<Pending>(null);
    const [readyRow, setReadyRow] = useState<Row | null>(null);
    const [cancelRow, setCancelRow] = useState<Row | null>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        router.get(urls.index, buildQuery(filters, search, overrides), { preserveState: true, replace: true });
    };

    const activeFilterCount = [filters.status].filter((value) => value !== '').length;

    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => { setProcessing(false); setPending(null); };

        if (pending.kind === 'fulfill') {
            router.post(pending.row.fulfillUrl, {}, { preserveScroll: true, onFinish: done });
            return;
        }

        router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Reservasi Perpustakaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck2}
                        eyebrow="Perpustakaan"
                        title="Reservasi Buku"
                        description="Antrean permintaan buku dari mahasiswa. Siapkan eksemplar, serahkan sebagai peminjaman, atau batalkan."
                    />

                    <LibraryStats
                        label="Statistik reservasi"
                        items={[
                            { label: 'Menunggu', value: stats.pending, icon: Hourglass, tone: 'gold' },
                            { label: 'Siap diambil', value: stats.ready, icon: Clock },
                            { label: 'Selesai bulan ini', value: stats.fulfilledThisMonth, icon: CheckCircle2, tone: 'green' },
                            { label: 'Batal / kedaluwarsa', value: stats.expired, icon: XCircle, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Reservasi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'book',
                                        label: 'Buku',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.bookTitle ?? '—'}</b>
                                                <small className="db-hint">{row.author || 'Tanpa penulis'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'member',
                                        label: 'Anggota',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.member}</b>
                                                <small className="db-hint">{row.username}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'reserved_at',
                                        label: 'Diajukan',
                                        sortable: true,
                                        render: (row) => row.reservedAt ?? '—',
                                    },
                                    {
                                        key: 'expires_at',
                                        label: 'Masa Berlaku',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                {row.expiresAt ?? '—'}
                                                {row.copyCode && <small className="db-hint" style={{ display: 'block' }}>Eksemplar {row.copyCode}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${libraryTone(row.statusTone)}`}>{row.statusLabel}</span>,
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
                                searchPlaceholder="Cari anggota, judul buku, atau kode eksemplar…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={() => visit({ status: '', page: 1 })}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 })}
                                            aria-label="Filter status reservasi"
                                        >
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                selectable={false}
                                canDelete={can.delete}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => (row.canDelete ? setPending({ kind: 'delete', row }) : undefined) : undefined}
                                customActions={(row) => (
                                    <>
                                        {can.update && row.canReady && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setReadyRow(row)} title="Siapkan eksemplar">
                                                <BookOpen size={13} />
                                            </button>
                                        )}
                                        {can.update && row.canFulfill && (
                                            <button className="db-btn primary sm" type="button" onClick={() => setPending({ kind: 'fulfill', row })} title="Serahkan sebagai peminjaman">
                                                <BookCheck size={13} />
                                            </button>
                                        )}
                                        {can.update && row.canCancel && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setCancelRow(row)} title="Batalkan reservasi">
                                                <XCircle size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada reservasi yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                {readyRow && <ReadyModal reservation={readyRow} onClose={() => setReadyRow(null)} />}
                {cancelRow && <CancelModal reservation={cancelRow} onClose={() => setCancelRow(null)} />}

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'fulfill' ? 'Serahkan reservasi?' : 'Hapus riwayat reservasi?'}
                    message={pending?.kind === 'fulfill'
                        ? `Reservasi ${pending.row.bookTitle ?? ''} untuk ${pending.row.member} akan dicatat sebagai peminjaman.`
                        : pending?.kind === 'delete'
                            ? `Riwayat reservasi ${pending.row.bookTitle ?? ''} oleh ${pending.row.member} akan dihapus.`
                            : ''}
                    confirmLabel={pending?.kind === 'fulfill' ? 'Ya, serahkan' : 'Ya, hapus'}
                    danger={pending?.kind !== 'fulfill'}
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
