// Detail buku di portal mahasiswa: bibliografi, ketersediaan eksemplar,
// status reservasi/pinjaman, dan aksi reservasi atau perpanjangan.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft, BookMarked, BookOpen, CalendarClock, CheckCircle2, Clock,
    Library, RefreshCw, TriangleAlert, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { LibraryStats, ReserveBookModal, formatRupiah, libraryTone } from '../../../components/Shared/Library/LibraryUI';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type BookDetail = {
    id: number;
    title: string;
    author: string | null;
    publisher: string | null;
    isbn: string | null;
    publicationYear: number | null;
    language: string | null;
    edition: string | null;
    description: string | null;
    shelfLocation: string | null;
    coverUrl: string | null;
    category: { id: number; name: string; code: string } | null;
};

type CopyRow = {
    id: number;
    code: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    dueAt: string | null;
};

type MyLoan = {
    id: number;
    copyCode: string | null;
    borrowedAt: string | null;
    dueAt: string | null;
    statusLabel: string;
    statusTone: string;
    renewalCount: number;
    maxRenewals: number;
    canRenew: boolean;
    renewBlockReason: string | null;
    renewUrl: string;
};

type MyReservation = {
    id: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    queuePosition: number;
    reservedAt: string | null;
    pickupPlannedAt: string | null;
    readyAt: string | null;
    expiresAt: string | null;
    cancelUrl: string;
};

type RelatedBook = {
    id: number;
    title: string;
    author: string | null;
    coverUrl: string | null;
    availableCount: number;
    detailUrl: string;
};

type Props = {
    shell: ShellProps;
    book: BookDetail;
    stats: {
        copies: number;
        available: number;
        borrowed: number;
        reserved: number;
        queue: number;
        estimatedReadyAt: string | null;
    };
    copies: CopyRow[];
    myLoan: MyLoan | null;
    myReservation: MyReservation | null;
    related: RelatedBook[];
    options: { loanDays: number; finePerDay: number; holdDays: number; pickupPlanDays: number; maxRenewals: number; renewalDays: number };
    urls: { index: string; reserve: string };
};

export default function StudentLibraryShow({ shell, book, stats, copies, myLoan, myReservation, related, options, urls }: Props) {
    const [reserveOpen, setReserveOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [renewOpen, setRenewOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const canReserve = !myLoan && !myReservation;

    const confirmCancel = () => {
        if (!myReservation) return;
        setProcessing(true);
        router.post(myReservation.cancelUrl, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setCancelOpen(false); },
        });
    };

    const confirmRenew = () => {
        if (!myLoan) return;
        setProcessing(true);
        router.post(myLoan.renewUrl, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setRenewOpen(false); },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${book.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow={book.category ? `Perpustakaan · ${book.category.name}` : 'Perpustakaan'}
                        title={book.title}
                        description={book.author ? `Karya ${book.author}${book.publisher ? ` · ${book.publisher}` : ''}` : 'Detail koleksi perpustakaan.'}
                        badges={[
                            stats.available > 0 ? `${stats.available} eksemplar tersedia` : 'Sedang tidak tersedia',
                            stats.queue > 0 ? `${stats.queue} anggota dalam antrean` : 'Tanpa antrean',
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Katalog</a>
                                {canReserve && (
                                    <button className="db-btn primary" type="button" onClick={() => setReserveOpen(true)}>
                                        <CalendarClock size={15} /> Ajukan Reservasi
                                    </button>
                                )}
                            </>
                        )}
                    />

                    <LibraryStats
                        label="Ketersediaan eksemplar"
                        items={[
                            { label: 'Total eksemplar', value: stats.copies, icon: Library },
                            { label: 'Tersedia', value: stats.available, icon: CheckCircle2, tone: 'green' },
                            { label: 'Dipinjam', value: stats.borrowed, icon: BookMarked, tone: 'gold' },
                            { label: 'Ditahan reservasi', value: stats.reserved, icon: Clock },
                            { label: 'Antrean aktif', value: stats.queue, icon: Users, tone: 'red' },
                        ]}
                    />

                    <div className="db-layout">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Informasi Bibliografi</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-facts">
                                    <div className="db-fact"><span>Kategori</span><b>{book.category?.name ?? '—'}</b></div>
                                    <div className="db-fact"><span>ISBN</span><b>{book.isbn || '—'}</b></div>
                                    <div className="db-fact"><span>Penerbit</span><b>{book.publisher || '—'}</b></div>
                                    <div className="db-fact"><span>Tahun terbit</span><b>{book.publicationYear ?? '—'}</b></div>
                                    <div className="db-fact"><span>Bahasa</span><b>{book.language || '—'}</b></div>
                                    <div className="db-fact"><span>Edisi</span><b>{book.edition || '—'}</b></div>
                                    <div className="db-fact"><span>Lokasi rak</span><b>{book.shelfLocation || '—'}</b></div>
                                    <div className="db-fact"><span>Masa pinjam</span><b>{options.loanDays} hari</b></div>
                                    <div className="db-fact wide"><span>Deskripsi</span><b>{book.description || 'Belum ada deskripsi.'}</b></div>
                                </div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Sampul</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'flex', justifyContent: 'center' }}>
                                {book.coverUrl ? (
                                    <img
                                        src={book.coverUrl}
                                        alt={`Sampul ${book.title}`}
                                        style={{ width: '100%', maxWidth: 220, borderRadius: 12, border: '1px solid var(--db-line)' }}
                                    />
                                ) : (
                                    <div className="db-empty-calm" style={{ width: '100%' }}>
                                        <BookOpen size={28} />
                                        <p>Belum ada sampul buku.</p>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    {myLoan && (
                        <section className="db-card">
                            <div className="db-card-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                                <h2 className="db-card-title">Peminjaman Anda</h2>
                                <span className={`db-badge ${libraryTone(myLoan.statusTone)}`}>{myLoan.statusLabel}</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                <div className="db-note info">
                                    <BookMarked size={15} />
                                    <span>
                                        Eksemplar <b>{myLoan.copyCode ?? '—'}</b> dipinjam {myLoan.borrowedAt ?? '—'} dan jatuh tempo <b>{myLoan.dueAt ?? '—'}</b>.
                                        {myLoan.renewalCount > 0 && <> Sudah diperpanjang {myLoan.renewalCount}×.</>}
                                    </span>
                                </div>
                                {myLoan.canRenew ? (
                                    <div>
                                        <button className="db-btn ghost" type="button" onClick={() => setRenewOpen(true)}>
                                            <RefreshCw size={15} /> Perpanjang {options.renewalDays} hari
                                        </button>
                                    </div>
                                ) : myLoan.renewBlockReason ? (
                                    <div className="db-note warning">
                                        <TriangleAlert size={15} />
                                        <span>{myLoan.renewBlockReason}</span>
                                    </div>
                                ) : null}
                            </div>
                        </section>
                    )}

                    {myReservation && (
                        <section className="db-card">
                            <div className="db-card-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                                <h2 className="db-card-title">Reservasi Anda</h2>
                                <span className={`db-badge ${libraryTone(myReservation.statusTone)}`}>{myReservation.statusLabel}</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                <div className="db-note info">
                                    <CalendarClock size={15} />
                                    <span>
                                        Diajukan {myReservation.reservedAt ?? '—'}
                                        {myReservation.queuePosition > 0 && <> · posisi antrean <b>ke-{myReservation.queuePosition}</b></>}
                                        {myReservation.pickupPlannedAt && <> · rencana ambil <b>{myReservation.pickupPlannedAt}</b></>}
                                        {myReservation.readyAt && <> · siap diambil <b>{myReservation.readyAt}</b></>}
                                        {myReservation.expiresAt && <> · batas ambil <b>{myReservation.expiresAt}</b></>}
                                    </span>
                                </div>
                                {myReservation.expiresAt && (
                                    <div className="db-note warning">
                                        <Clock size={15} />
                                        <span>Jika tidak diambil sampai batas tersebut, reservasi kedaluwarsa dan eksemplar dialihkan ke antrean berikutnya.</span>
                                    </div>
                                )}
                                <div>
                                    <button className="db-btn danger" type="button" onClick={() => setCancelOpen(true)}>
                                        <XCircle size={15} /> Batalkan Reservasi
                                    </button>
                                </div>
                            </div>
                        </section>
                    )}

                    {!myLoan && !myReservation && (
                        <section className="db-card">
                            <div className="db-card-body">
                                {stats.available > 0 ? (
                                    <div className="db-note success">
                                        <CheckCircle2 size={15} />
                                        <span>Buku tersedia. Ajukan reservasi, lalu ambil di perpustakaan sesuai rencana tanggal ambil Anda.</span>
                                    </div>
                                ) : (
                                    <div className="db-note warning">
                                        <Clock size={15} />
                                        <span>
                                            Semua eksemplar sedang dipinjam/ditahan.
                                            {stats.estimatedReadyAt && <> Perkiraan tersedia <b>{stats.estimatedReadyAt}</b>.</>}
                                            {' '}Anda tetap dapat masuk antrean reservasi.
                                        </span>
                                    </div>
                                )}
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Eksemplar ({copies.length})</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Status</th>
                                            <th>Jatuh Tempo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {copies.length === 0 && (
                                            <tr><td colSpan={3} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada eksemplar.</span></td></tr>
                                        )}
                                        {copies.map((copy) => (
                                            <tr key={copy.id}>
                                                <td><b style={{ color: 'var(--db-heading)' }}>{copy.code}</b></td>
                                                <td><span className={`db-badge ${libraryTone(copy.statusTone)}`}>{copy.statusLabel}</span></td>
                                                <td><span className="db-hint">{copy.dueAt ?? '—'}</span></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <p className="db-hint" style={{ marginTop: 12, marginBottom: 0 }}>
                                Denda keterlambatan {formatRupiah(options.finePerDay)}/hari. Reservasi ditahan {options.holdDays} hari setelah siap diambil.
                            </p>
                        </div>
                    </section>

                    {related.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Buku Lain di Kategori Ini</h2>
                            </div>
                            <div className="db-card-body">
                                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 12 }}>
                                    {related.map((item) => (
                                        <a
                                            key={item.id}
                                            href={item.detailUrl}
                                            style={{
                                                display: 'flex',
                                                gap: 10,
                                                padding: 12,
                                                border: '1px solid var(--db-line)',
                                                borderRadius: 12,
                                                background: 'var(--db-soft)',
                                                textDecoration: 'none',
                                            }}
                                        >
                                            {item.coverUrl ? (
                                                <img src={item.coverUrl} alt="" style={{ width: 48, height: 66, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--db-line)' }} />
                                            ) : (
                                                <span className="db-stat-icon" style={{ width: 48, height: 66, borderRadius: 6 }}><BookOpen size={18} /></span>
                                            )}
                                            <span style={{ minWidth: 0 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5, lineHeight: 1.35 }}>{item.title}</b>
                                                <small className="db-hint">{item.author || 'Tanpa penulis'}</small>
                                                <span className={`db-badge ${item.availableCount > 0 ? 'green' : 'amber'}`} style={{ marginTop: 6 }}>
                                                    {item.availableCount > 0 ? `${item.availableCount} tersedia` : 'Tidak tersedia'}
                                                </span>
                                            </span>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}
                </div>

                {reserveOpen && (
                    <ReserveBookModal
                        book={{
                            id: book.id,
                            title: book.title,
                            author: book.author,
                            availableCount: stats.available,
                            queueCount: stats.queue,
                            estimatedReadyAt: stats.estimatedReadyAt,
                        }}
                        url={urls.reserve}
                        loanDays={options.loanDays}
                        holdDays={options.holdDays}
                        pickupPlanDays={options.pickupPlanDays}
                        onClose={() => setReserveOpen(false)}
                    />
                )}

                <ConfirmModal
                    open={cancelOpen}
                    title="Batalkan reservasi?"
                    message={`Reservasi "${book.title}" akan dibatalkan.`}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={confirmCancel}
                    onCancel={() => setCancelOpen(false)}
                />

                <ConfirmModal
                    open={renewOpen}
                    title="Perpanjang peminjaman?"
                    message={myLoan
                        ? `"${book.title}" akan diperpanjang ${options.renewalDays} hari dari jatuh tempo saat ini (${myLoan.dueAt ?? '—'}).`
                        : ''}
                    confirmLabel="Ya, perpanjang"
                    processing={processing}
                    onConfirm={confirmRenew}
                    onCancel={() => setRenewOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
