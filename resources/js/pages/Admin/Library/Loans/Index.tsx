// Daftar peminjaman perpustakaan (admin) + pengembalian dan pembayaran denda.
import { Head, router, useForm } from '@inertiajs/react';
import {
    BookMarked, BookOpen, CircleDollarSign, Clock, Plus, RotateCcw, Wallet,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, formatRupiah, libraryTone, LibraryModal, LibraryStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    bookTitle: string | null;
    copyCode: string | null;
    member: string;
    username: string | null;
    borrowedAt: string | null;
    dueAt: string | null;
    returnedAt: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    daysLate: number;
    fineAmount: number;
    finePaid: boolean;
    canReturn: boolean;
    canPayFine: boolean;
    canDelete: boolean;
    returnUrl: string;
    payFineUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { active: number; overdue: number; returnedThisMonth: number; outstandingFines: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; status: string; overdue: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    options: { statuses: Record<string, string>; copyStatuses: Record<string, string>; finePerDay: number };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | { kind: 'pay'; row: Row } | null;

function ReturnModal({ loan, copyStatuses, finePerDay, onClose }: {
    loan: Row;
    copyStatuses: Record<string, string>;
    finePerDay: number;
    onClose: () => void;
}) {
    const suggestedFine = loan.daysLate * finePerDay;
    const form = useForm({
        copy_status: 'available',
        fine_amount: suggestedFine,
        fine_paid: false,
        notes: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(loan.returnUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <LibraryModal
            title="Catat Pengembalian"
            description={`${loan.bookTitle ?? 'Buku'} · ${loan.copyCode ?? '-'} — ${loan.member}`}
            onClose={onClose}
        >
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                {loan.daysLate > 0 && (
                    <div className="db-note warning">
                        <Clock size={15} />
                        <span>Terlambat <b>{loan.daysLate} hari</b>. Denda otomatis {formatRupiah(suggestedFine)}.</span>
                    </div>
                )}
                <div>
                    <label className="crud-label" htmlFor="return-status">Kondisi eksemplar <span>*</span></label>
                    <select
                        id="return-status"
                        className="db-input"
                        value={form.data.copy_status}
                        onChange={(event) => form.setData('copy_status', event.target.value)}
                    >
                        {Object.entries(copyStatuses).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    {form.errors.copy_status && <div className="db-error">{form.errors.copy_status}</div>}
                </div>
                <div>
                    <label className="crud-label" htmlFor="return-fine">Denda (Rp)</label>
                    <input
                        id="return-fine"
                        type="number"
                        min={0}
                        className="db-input"
                        value={form.data.fine_amount}
                        onChange={(event) => form.setData('fine_amount', Number(event.target.value))}
                    />
                    {form.errors.fine_amount && <div className="db-error">{form.errors.fine_amount}</div>}
                </div>
                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--db-ink)' }}>
                    <input
                        type="checkbox"
                        checked={form.data.fine_paid}
                        onChange={(event) => form.setData('fine_paid', event.target.checked)}
                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }}
                    />
                    Denda langsung dibayar
                </label>
                <div>
                    <label className="crud-label" htmlFor="return-notes">Catatan</label>
                    <textarea
                        id="return-notes"
                        className="db-input"
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        placeholder="Kondisi buku saat dikembalikan…"
                    />
                    {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : 'Catat Pengembalian'}
                    </button>
                </div>
            </form>
        </LibraryModal>
    );
}

export default function LoansIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [returning, setReturning] = useState<Row | null>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const activeFilterCount = [filters.status, filters.overdue].filter((value) => value !== '').length;

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

        if (pending.kind === 'pay') {
            router.post(pending.row.payFineUrl, {}, { preserveScroll: true, onFinish: done });
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Peminjaman Perpustakaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookMarked}
                        eyebrow="Perpustakaan"
                        title="Peminjaman"
                        description="Catat peminjaman, pengembalian, keterlambatan, dan denda anggota perpustakaan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Catat Peminjaman</a>
                        ) : undefined}
                    />

                    <LibraryStats
                        label="Statistik peminjaman"
                        items={[
                            { label: 'Peminjaman aktif', value: stats.active, icon: BookOpen },
                            { label: 'Terlambat', value: stats.overdue, icon: Clock, tone: 'red' },
                            { label: 'Kembali bulan ini', value: stats.returnedThisMonth, icon: RotateCcw, tone: 'green' },
                            { label: 'Denda belum lunas', value: formatRupiah(stats.outstandingFines), icon: CircleDollarSign, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Peminjaman</h2>
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
                                                <small className="db-hint">{row.copyCode ?? '—'}</small>
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
                                        key: 'borrowed_at',
                                        label: 'Pinjam',
                                        sortable: true,
                                        render: (row) => row.borrowedAt ?? '—',
                                    },
                                    {
                                        key: 'due_at',
                                        label: 'Jatuh Tempo',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                {row.dueAt ?? '—'}
                                                {row.daysLate > 0 && (
                                                    <small className="db-hint" style={{ display: 'block', color: 'var(--db-red)' }}>
                                                        {row.daysLate} hari terlambat
                                                    </small>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${libraryTone(row.statusTone)}`}>{row.statusLabel}</span>,
                                    },
                                    {
                                        key: 'fine',
                                        label: 'Denda',
                                        align: 'right',
                                        render: (row) => row.fineAmount > 0 ? (
                                            <span>
                                                <b>{formatRupiah(row.fineAmount)}</b>
                                                <small className="db-hint" style={{ display: 'block' }}>{row.finePaid ? 'Lunas' : 'Belum lunas'}</small>
                                            </span>
                                        ) : '—',
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
                                searchPlaceholder="Cari anggota, kode eksemplar, atau judul…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ status: '', overdue: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status peminjaman"
                                        >
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.overdue}
                                            onChange={(event) => visit({ overdue: event.target.value, page: 1 }, true)}
                                            aria-label="Filter keterlambatan"
                                        >
                                            <option value="">Semua ketepatan waktu</option>
                                            <option value="1">Hanya terlambat</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus riwayat"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => (row.canDelete ? setPending({ kind: 'delete', row }) : undefined) : undefined}
                                customActions={(row) => (
                                    <>
                                        {can.update && row.canReturn && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setReturning(row)} title="Catat pengembalian">
                                                <RotateCcw size={13} />
                                            </button>
                                        )}
                                        {can.update && row.canPayFine && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setPending({ kind: 'pay', row })} title="Tandai denda lunas">
                                                <Wallet size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada peminjaman yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                {returning && (
                    <ReturnModal
                        loan={returning}
                        copyStatuses={options.copyStatuses}
                        finePerDay={options.finePerDay}
                        onClose={() => setReturning(null)}
                    />
                )}

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus riwayat terpilih?' : pending?.kind === 'pay' ? 'Tandai denda lunas?' : 'Hapus riwayat peminjaman?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} riwayat peminjaman akan dihapus. Peminjaman aktif akan dilewati.`
                        : pending?.kind === 'pay'
                            ? `Denda ${formatRupiah(pending.row.fineAmount)} untuk ${pending.row.member} akan ditandai lunas.`
                            : pending?.kind === 'delete'
                                ? `Riwayat peminjaman ${pending.row.copyCode ?? ''} oleh ${pending.row.member} akan dihapus.`
                                : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : pending?.kind === 'pay' ? 'Ya, tandai lunas' : 'Ya, hapus'}
                    danger={pending?.kind !== 'pay'}
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
