// Portal mahasiswa — katalog perpustakaan, pinjaman, dan reservasi.
import { Head, router } from '@inertiajs/react';
import {
    BookMarked, BookOpen, CalendarClock, CheckCircle2, Clock, Eye, FileText, Library,
    RefreshCw, Search, TriangleAlert, Upload, Wallet, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { FinePaymentModal, LibraryStats, ReserveBookModal, formatRupiah, libraryTone } from '../../../components/Shared/Library/LibraryUI';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type BookRow = {
    id: number;
    title: string;
    author: string | null;
    publisher: string | null;
    year: number | null;
    category: string | null;
    shelfLocation: string | null;
    description: string | null;
    coverUrl: string | null;
    copiesCount: number;
    availableCount: number;
    queueCount: number;
    estimatedReadyAt: string | null;
    hasActiveLoan: boolean;
    hasActiveReservation: boolean;
    canReserve: boolean;
    detailUrl: string;
};

type LoanRow = {
    id: number;
    bookTitle: string | null;
    author: string | null;
    copyCode: string | null;
    borrowedAt: string | null;
    dueAt: string | null;
    returnedAt: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    daysLate: number;
    fineAmount: number;
    finePaid: boolean;
    fineStatus: string;
    fineStatusLabel: string;
    fineStatusTone: string;
    fineProofUrl: string | null;
    fineSubmittedAt: string | null;
    fineVerifiedAt: string | null;
    fineRejectionReason: string | null;
    canSubmitFineProof: boolean;
    submitFineProofUrl: string;
    renewalCount: number;
    maxRenewals: number;
    renewalDays: number;
    canRenew: boolean;
    renewBlockReason: string | null;
    renewUrl: string;
};

type ReservationRow = {
    id: number;
    bookTitle: string | null;
    author: string | null;
    copyCode: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    reservedAt: string | null;
    pickupPlannedAt: string | null;
    readyAt: string | null;
    expiresAt: string | null;
    queuePosition: number;
    estimatedReadyAt: string | null;
    loanDueAt: string | null;
    canCancel: boolean;
    cancelUrl: string;
};

type Props = {
    shell: ShellProps;
    stats: { activeLoans: number; overdue: number; outstandingFine: number; activeReservations: number };
    books: { rows: BookRow[]; currentPage: number; lastPage: number; perPage: number; total: number };
    myLoans: LoanRow[];
    myReservations: ReservationRow[];
    filters: { q: string; category_id: string; availability: string; sort: string; direction: 'asc' | 'desc' };
    categories: Array<{ id: number; name: string }>;
    options: { loanDays: number; finePerDay: number; holdDays: number; pickupPlanDays: number; maxRenewals: number; renewalDays: number };
    urls: { index: string; reserve: string };
};

type Tab = 'catalog' | 'loans' | 'reservations';

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>): Record<string, string> {
    const merged: Record<string, unknown> = { ...filters, q: search, ...overrides };

    return Object.fromEntries(
        Object.entries(merged)
            .filter(([, value]) => value !== '' && value !== undefined && value !== null)
            .map(([key, value]) => [key, String(value)]),
    );
}

export default function StudentLibraryIndex({ shell, stats, books, myLoans, myReservations, filters, categories, options, urls }: Props) {
    const [tab, setTab] = useState<Tab>('catalog');
    const [search, setSearch] = useState(filters.q);
    const [reserveBook, setReserveBook] = useState<BookRow | null>(null);
    const [cancelReservation, setCancelReservation] = useState<ReservationRow | null>(null);
    const [renewLoan, setRenewLoan] = useState<LoanRow | null>(null);
    const [fineLoan, setFineLoan] = useState<LoanRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        router.get(urls.index, buildQuery(filters, search, overrides), { preserveState: true, replace: true });
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ page: 1 });
    };

    const confirmCancel = () => {
        if (!cancelReservation) return;
        setProcessing(true);
        router.post(cancelReservation.cancelUrl, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setCancelReservation(null); },
        });
    };

    const confirmRenew = () => {
        if (!renewLoan) return;
        setProcessing(true);
        router.post(renewLoan.renewUrl, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setRenewLoan(null); },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perpustakaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Library}
                        eyebrow="Layanan Kampus"
                        title="Perpustakaan"
                        description="Telusuri katalog, ajukan reservasi, dan pantau pinjaman serta denda Anda."
                        badges={[`Masa pinjam ${options.loanDays} hari`, `Denda ${formatRupiah(options.finePerDay)}/hari`]}
                    />

                    <LibraryStats
                        label="Ringkasan perpustakaan saya"
                        items={[
                            { label: 'Pinjaman aktif', value: stats.activeLoans, icon: BookMarked },
                            { label: 'Terlambat', value: stats.overdue, icon: TriangleAlert, tone: 'red' },
                            { label: 'Denda belum lunas', value: formatRupiah(stats.outstandingFine), icon: Wallet, tone: 'gold' },
                            { label: 'Reservasi aktif', value: stats.activeReservations, icon: Clock, tone: 'green' },
                        ]}
                    />

                    <div className="crud-tabs">
                        <button
                            type="button"
                            className={`crud-tab ${tab === 'catalog' ? 'active' : ''}`}
                            onClick={() => setTab('catalog')}
                        >
                            <BookOpen size={14} /> Katalog <span className="db-badge gray">{books.total}</span>
                        </button>
                        <button
                            type="button"
                            className={`crud-tab ${tab === 'loans' ? 'active' : ''}`}
                            onClick={() => setTab('loans')}
                        >
                            <BookMarked size={14} /> Pinjaman Saya <span className="db-badge gray">{myLoans.length}</span>
                        </button>
                        <button
                            type="button"
                            className={`crud-tab ${tab === 'reservations' ? 'active' : ''}`}
                            onClick={() => setTab('reservations')}
                        >
                            <CalendarClock size={14} /> Reservasi Saya <span className="db-badge gray">{myReservations.length}</span>
                        </button>
                    </div>

                    {tab === 'catalog' && (
                        <>
                            <section className="db-card">
                                <div className="db-card-body">
                                    <form onSubmit={submitSearch} className="crud-toolbar" style={{ flexWrap: 'wrap' }}>
                                        <div className="crud-search">
                                            <input
                                                className="db-input"
                                                type="search"
                                                value={search}
                                                onChange={(event) => setSearch(event.target.value)}
                                                placeholder="Cari judul, penulis, penerbit, atau ISBN…"
                                                aria-label="Cari buku"
                                            />
                                            <button className="db-btn ghost" type="submit"><Search size={14} /> Cari</button>
                                        </div>
                                        <select
                                            className="db-input"
                                            style={{ width: 'auto', minHeight: 36 }}
                                            value={filters.category_id}
                                            onChange={(event) => visit({ category_id: event.target.value, page: 1 })}
                                            aria-label="Filter kategori"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categories.map((category) => (
                                                <option key={category.id} value={category.id}>{category.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            style={{ width: 'auto', minHeight: 36 }}
                                            value={filters.availability}
                                            onChange={(event) => visit({ availability: event.target.value, page: 1 })}
                                            aria-label="Filter ketersediaan"
                                        >
                                            <option value="">Semua ketersediaan</option>
                                            <option value="available">Tersedia</option>
                                            <option value="unavailable">Tidak tersedia</option>
                                        </select>
                                    </form>

                                    {books.rows.length === 0 ? (
                                        <div className="db-empty-calm" style={{ marginTop: 14 }}>
                                            <BookOpen size={26} />
                                            <p>Tidak ada buku yang cocok dengan pencarian.</p>
                                        </div>
                                    ) : (
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: 14, marginTop: 16 }}>
                                            {books.rows.map((book) => (
                                                <article
                                                    key={book.id}
                                                    style={{
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        gap: 10,
                                                        padding: 14,
                                                        border: '1px solid var(--db-line)',
                                                        borderRadius: 12,
                                                        background: 'var(--db-soft)',
                                                    }}
                                                >
                                                    <div style={{ display: 'flex', gap: 12 }}>
                                                        {book.coverUrl ? (
                                                            <img
                                                                src={book.coverUrl}
                                                                alt=""
                                                                style={{ width: 64, height: 88, objectFit: 'cover', borderRadius: 8, border: '1px solid var(--db-line)' }}
                                                            />
                                                        ) : (
                                                            <span className="db-stat-icon" style={{ width: 64, height: 88, borderRadius: 8 }}>
                                                                <BookOpen size={22} />
                                                            </span>
                                                        )}
                                                        <div style={{ minWidth: 0 }}>
                                                            <a
                                                                href={book.detailUrl}
                                                                style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, lineHeight: 1.35, fontWeight: 700, textDecoration: 'none' }}
                                                            >
                                                                {book.title}
                                                            </a>
                                                            <small className="db-hint">{book.author || 'Tanpa penulis'}</small>
                                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 6 }}>
                                                                <span className="db-badge gray">{book.category || 'Umum'}</span>
                                                                {book.year && <span className="db-badge gray">{book.year}</span>}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {book.description && (
                                                        <p className="db-hint" style={{ margin: 0, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                                                            {book.description}
                                                        </p>
                                                    )}

                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                                        <span className={`db-badge ${book.availableCount > 0 ? 'green' : 'amber'}`}>
                                                            {book.availableCount > 0 ? `${book.availableCount} tersedia` : 'Tidak tersedia'}
                                                        </span>
                                                        {book.queueCount > 0 && (
                                                            <span className="db-badge blue"><Clock size={11} /> {book.queueCount} antre</span>
                                                        )}
                                                        {book.shelfLocation && <span className="db-badge gray">Rak {book.shelfLocation}</span>}
                                                    </div>

                                                    {book.availableCount === 0 && book.estimatedReadyAt && (
                                                        <small className="db-hint">Perkiraan tersedia: {book.estimatedReadyAt}</small>
                                                    )}

                                                    <div style={{ marginTop: 'auto', display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                                                        <a className="db-btn ghost sm" href={book.detailUrl}>
                                                            <Eye size={13} /> Detail
                                                        </a>
                                                        {book.hasActiveLoan ? (
                                                            <span className="db-badge blue"><BookMarked size={11} /> Sedang Anda pinjam</span>
                                                        ) : book.hasActiveReservation ? (
                                                            <span className="db-badge amber"><Clock size={11} /> Sudah direservasi</span>
                                                        ) : (
                                                            <button
                                                                className="db-btn primary sm"
                                                                type="button"
                                                                disabled={!book.canReserve}
                                                                onClick={() => setReserveBook(book)}
                                                            >
                                                                <CalendarClock size={13} /> Reservasi
                                                            </button>
                                                        )}
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}

                                    {books.lastPage > 1 && (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: 14, marginTop: 18 }}>
                                            <button className="db-btn ghost sm" type="button" disabled={books.currentPage <= 1} onClick={() => visit({ page: books.currentPage - 1 })}>
                                                Sebelumnya
                                            </button>
                                            <span className="db-hint">Halaman <b>{books.currentPage}</b> dari {books.lastPage} · {books.total} buku</span>
                                            <button className="db-btn ghost sm" type="button" disabled={books.currentPage >= books.lastPage} onClick={() => visit({ page: books.currentPage + 1 })}>
                                                Berikutnya
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}

                    {tab === 'loans' && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Pinjaman Saya</h2>
                            </div>
                            <div className="db-card-body">
                                {myLoans.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <BookMarked size={26} />
                                        <p>Belum ada peminjaman. Ajukan reservasi dari katalog untuk mulai meminjam.</p>
                                    </div>
                                ) : (
                                    <div className="crud-table-wrap">
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Buku</th>
                                                    <th>Eksemplar</th>
                                                    <th>Pinjam</th>
                                                    <th>Jatuh Tempo</th>
                                                    <th>Kembali</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Denda</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {myLoans.map((loan) => (
                                                    <tr key={loan.id}>
                                                        <td>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{loan.bookTitle ?? '—'}</b>
                                                            <small className="db-hint">{loan.author || '—'}</small>
                                                        </td>
                                                        <td>{loan.copyCode ?? '—'}</td>
                                                        <td>{loan.borrowedAt ?? '—'}</td>
                                                        <td>
                                                            {loan.dueAt ?? '—'}
                                                            {loan.daysLate > 0 && <small className="db-hint" style={{ display: 'block', color: 'var(--db-red)' }}>{loan.daysLate} hari terlambat</small>}
                                                            {loan.renewalCount > 0 && (
                                                                <small className="db-hint" style={{ display: 'block' }}>
                                                                    Diperpanjang {loan.renewalCount}× ({loan.renewalDays} hari)
                                                                </small>
                                                            )}
                                                        </td>
                                                        <td>{loan.returnedAt ?? '—'}</td>
                                                        <td><span className={`db-badge ${libraryTone(loan.statusTone)}`}>{loan.statusLabel}</span></td>
                                                        <td style={{ textAlign: 'right' }}>
                                                            {loan.fineAmount > 0 ? (
                                                                <span>
                                                                    <b>{formatRupiah(loan.fineAmount)}</b>
                                                                    <span className={`db-badge ${libraryTone(loan.fineStatusTone)}`} style={{ display: 'inline-block', marginTop: 4 }}>
                                                                        {loan.fineStatusLabel}
                                                                    </span>
                                                                    {loan.fineRejectionReason && (
                                                                        <small className="db-hint" style={{ display: 'block', color: 'var(--db-red)' }}>
                                                                            {loan.fineRejectionReason}
                                                                        </small>
                                                                    )}
                                                                </span>
                                                            ) : '—'}
                                                        </td>
                                                        <td>
                                                            <div className="crud-row-actions" style={{ justifyContent: 'flex-end' }}>
                                                                {loan.fineProofUrl && (
                                                                    <a
                                                                        className="db-btn ghost sm"
                                                                        href={loan.fineProofUrl}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        title="Lihat bukti pembayaran"
                                                                    >
                                                                        <FileText size={13} />
                                                                    </a>
                                                                )}
                                                                {loan.canSubmitFineProof && (
                                                                    <button
                                                                        className="db-btn ghost sm"
                                                                        type="button"
                                                                        onClick={() => setFineLoan(loan)}
                                                                        title="Unggah bukti pembayaran denda"
                                                                    >
                                                                        <Upload size={13} />
                                                                    </button>
                                                                )}
                                                                {loan.canRenew ? (
                                                                    <button
                                                                        className="db-btn ghost sm"
                                                                        type="button"
                                                                        onClick={() => setRenewLoan(loan)}
                                                                        title={`Perpanjang ${loan.renewalDays} hari`}
                                                                    >
                                                                        <RefreshCw size={13} />
                                                                    </button>
                                                                ) : (
                                                                    <span className="db-hint" title={loan.renewBlockReason ?? undefined}>
                                                                        {loan.renewalCount >= loan.maxRenewals ? 'Maks.' : '—'}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </section>
                    )}

                    {tab === 'reservations' && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Reservasi Saya</h2>
                            </div>
                            <div className="db-card-body">
                                {myReservations.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <CalendarClock size={26} />
                                        <p>Belum ada reservasi.</p>
                                    </div>
                                ) : (
                                    <div className="crud-table-wrap">
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th>Buku</th>
                                                    <th>Eksemplar</th>
                                                    <th>Diajukan</th>
                                                    <th>Rencana Ambil</th>
                                                    <th>Siap Diambil</th>
                                                    <th>Batas Ambil</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {myReservations.map((reservation) => (
                                                    <tr key={reservation.id}>
                                                        <td>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{reservation.bookTitle ?? '—'}</b>
                                                            <small className="db-hint">{reservation.author || '—'}</small>
                                                        </td>
                                                        <td>{reservation.copyCode ?? '—'}</td>
                                                        <td>
                                                            {reservation.reservedAt ?? '—'}
                                                            {reservation.queuePosition > 0 && (
                                                                <small className="db-hint" style={{ display: 'block' }}>
                                                                    Antrean ke-{reservation.queuePosition}
                                                                </small>
                                                            )}
                                                        </td>
                                                        <td>{reservation.pickupPlannedAt ?? '—'}</td>
                                                        <td>
                                                            {reservation.readyAt ?? (reservation.estimatedReadyAt ? `~ ${reservation.estimatedReadyAt}` : '—')}
                                                            {!reservation.readyAt && reservation.estimatedReadyAt && (
                                                                <small className="db-hint" style={{ display: 'block' }}>Perkiraan</small>
                                                            )}
                                                        </td>
                                                        <td>{reservation.expiresAt ?? '—'}</td>
                                                        <td>
                                                            <span className={`db-badge ${libraryTone(reservation.statusTone)}`}>{reservation.statusLabel}</span>
                                                            {reservation.loanDueAt && (
                                                                <small className="db-hint" style={{ display: 'block' }}>
                                                                    Jatuh tempo {reservation.loanDueAt}
                                                                </small>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <div className="crud-row-actions" style={{ justifyContent: 'flex-end' }}>
                                                                {reservation.canCancel && (
                                                                    <button className="db-btn ghost sm" type="button" onClick={() => setCancelReservation(reservation)} title="Batalkan reservasi">
                                                                        <XCircle size={13} />
                                                                    </button>
                                                                )}
                                                                {!reservation.canCancel && <CheckCircle2 size={14} className="db-hint" />}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </section>
                    )}
                </div>

                {reserveBook && (
                    <ReserveBookModal
                        book={reserveBook}
                        url={urls.reserve}
                        loanDays={options.loanDays}
                        holdDays={options.holdDays}
                        pickupPlanDays={options.pickupPlanDays}
                        onClose={() => setReserveBook(null)}
                    />
                )}

                {fineLoan && (
                    <FinePaymentModal
                        loan={{
                            bookTitle: fineLoan.bookTitle,
                            fineAmount: fineLoan.fineAmount,
                            fineStatus: fineLoan.fineStatus,
                            fineRejectionReason: fineLoan.fineRejectionReason,
                            submitFineProofUrl: fineLoan.submitFineProofUrl,
                        }}
                        finePerDay={options.finePerDay}
                        onClose={() => setFineLoan(null)}
                    />
                )}

                <ConfirmModal
                    open={cancelReservation !== null}
                    title="Batalkan reservasi?"
                    message={cancelReservation ? `Reservasi "${cancelReservation.bookTitle ?? ''}" akan dibatalkan.` : ''}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={confirmCancel}
                    onCancel={() => setCancelReservation(null)}
                />

                <ConfirmModal
                    open={renewLoan !== null}
                    title="Perpanjang peminjaman?"
                    message={renewLoan
                        ? `"${renewLoan.bookTitle ?? ''}" akan diperpanjang ${renewLoan.renewalDays} hari dari jatuh tempo saat ini (${renewLoan.dueAt ?? '—'}).`
                        : ''}
                    confirmLabel="Ya, perpanjang"
                    processing={processing}
                    onConfirm={confirmRenew}
                    onCancel={() => setRenewLoan(null)}
                />
            </div>
        </AdminShell>
    );
}
