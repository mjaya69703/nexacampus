// Detail buku perpustakaan: data bibliografi, eksemplar, riwayat pinjam, dan reservasi.
import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, BookMarked, BookOpen, CheckCircle2, Clock, Library,
    Pencil, Plus, Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { LibraryModal, LibraryStats, formatRupiah, libraryTone } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Copy = {
    id: number;
    code: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    conditionNotes: string | null;
    acquiredAt: string | null;
    acquiredAtIso: string | null;
    activeLoan: { id: number; borrower: string; dueAt: string | null } | null;
    updateUrl: string;
    statusUrl: string;
    deleteUrl: string;
    createLoanUrl: string;
};

type Loan = {
    id: number;
    borrower: string;
    username: string | null;
    copyCode: string | null;
    borrowedAt: string | null;
    dueAt: string | null;
    returnedAt: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    fineAmount: number;
    finePaid: boolean;
};

type Reservation = {
    id: number;
    member: string;
    username: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    reservedAt: string | null;
    readyAt: string | null;
    expiresAt: string | null;
    copyCode: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; createLoan: boolean };
    book: {
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
        isActive: boolean;
        coverUrl: string | null;
        category: { id: number; name: string; code: string } | null;
        createdAt: string | null;
        updatedAt: string | null;
    };
    stats: { copies: number; available: number; borrowed: number; reserved: number; activeReservations: number };
    copies: Copy[];
    loans: Loan[];
    reservations: Reservation[];
    options: { copyStatuses: Record<string, string> };
    urls: { index: string; edit: string; delete: string; copyStore: string; loanCreate: string };
};

function CopyFormModal({ title, actionUrl, method, initial, onClose }: {
    title: string;
    actionUrl: string;
    method: 'post' | 'put';
    initial: { code: string; condition_notes: string; acquired_at: string };
    onClose: () => void;
}) {
    const form = useForm({ ...initial });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (method === 'put') {
            form.put(actionUrl, { preserveScroll: true, onSuccess: onClose });
            return;
        }

        form.post(actionUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <LibraryModal title={title} onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label" htmlFor="copy-code">Kode eksemplar <span>*</span></label>
                    <input
                        id="copy-code"
                        className="db-input"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                        placeholder="BK-0001-01"
                    />
                    {form.errors.code && <div className="db-error">{form.errors.code}</div>}
                </div>
                <div>
                    <label className="crud-label" htmlFor="copy-acquired">Tanggal perolehan</label>
                    <input
                        id="copy-acquired"
                        type="date"
                        className="db-input"
                        value={form.data.acquired_at}
                        onChange={(event) => form.setData('acquired_at', event.target.value)}
                    />
                    {form.errors.acquired_at && <div className="db-error">{form.errors.acquired_at}</div>}
                </div>
                <div>
                    <label className="crud-label" htmlFor="copy-notes">Catatan kondisi</label>
                    <textarea
                        id="copy-notes"
                        className="db-input"
                        rows={3}
                        value={form.data.condition_notes}
                        onChange={(event) => form.setData('condition_notes', event.target.value)}
                        placeholder="Contoh: sampul lecet, halaman lengkap."
                    />
                    {form.errors.condition_notes && <div className="db-error">{form.errors.condition_notes}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>
                        {form.processing ? 'Menyimpan…' : 'Simpan'}
                    </button>
                </div>
            </form>
        </LibraryModal>
    );
}

export default function BookShow({ shell, can, book, stats, copies, loans, reservations, options, urls }: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const [editCopy, setEditCopy] = useState<Copy | null>(null);
    const [deleteCopy, setDeleteCopy] = useState<Copy | null>(null);
    const [deleteBook, setDeleteBook] = useState(false);
    const [processing, setProcessing] = useState(false);

    const updateStatus = (copy: Copy, status: string) => {
        router.post(copy.statusUrl, { status }, { preserveScroll: true });
    };

    const confirmDeleteCopy = () => {
        if (!deleteCopy) return;
        setProcessing(true);
        router.delete(deleteCopy.deleteUrl, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setDeleteCopy(null); },
        });
    };

    const confirmDeleteBook = () => {
        setProcessing(true);
        router.delete(urls.delete, {
            onFinish: () => { setProcessing(false); setDeleteBook(false); },
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
                            book.isActive ? 'Buku Aktif' : 'Buku Nonaktif',
                            `${stats.available} dari ${stats.copies} eksemplar tersedia`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar Buku</a>
                                {can.update && <a className="db-btn ghost" href={urls.edit}><Pencil size={15} /> Edit</a>}
                                {can.delete && <button className="db-btn danger" type="button" onClick={() => setDeleteBook(true)}><Trash2 size={15} /> Hapus</button>}
                            </>
                        )}
                    />

                    <LibraryStats
                        label="Statistik eksemplar"
                        items={[
                            { label: 'Total eksemplar', value: stats.copies, icon: Library },
                            { label: 'Tersedia', value: stats.available, icon: CheckCircle2, tone: 'green' },
                            { label: 'Dipinjam', value: stats.borrowed, icon: BookMarked, tone: 'gold' },
                            { label: 'Direservasi', value: stats.reserved, icon: Clock },
                            { label: 'Reservasi aktif', value: stats.activeReservations, icon: Clock, tone: 'red' },
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
                                    <div className="db-fact"><span>Status</span><b>{book.isActive ? 'Aktif' : 'Nonaktif'}</b></div>
                                    <div className="db-fact wide"><span>Deskripsi</span><b>{book.description || 'Belum ada deskripsi.'}</b></div>
                                </div>
                                <p className="db-hint" style={{ marginTop: 12, marginBottom: 0 }}>
                                    Ditambahkan {book.createdAt ?? '—'} · Diperbarui {book.updatedAt ?? '—'}
                                </p>
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

                    <section className="db-card">
                        <div className="db-card-head" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
                            <h2 className="db-card-title">Eksemplar ({copies.length})</h2>
                            {can.update && (
                                <button className="db-btn primary sm" type="button" onClick={() => setAddOpen(true)}>
                                    <Plus size={14} /> Tambah Eksemplar
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Status</th>
                                            <th>Kondisi</th>
                                            <th>Diperoleh</th>
                                            <th>Peminjam Aktif</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {copies.length === 0 && (
                                            <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada eksemplar.</span></td></tr>
                                        )}
                                        {copies.map((copy) => {
                                            const locked = copy.status === 'borrowed' || copy.status === 'reserved';

                                            return (
                                                <tr key={copy.id}>
                                                    <td><b style={{ color: 'var(--db-heading)' }}>{copy.code}</b></td>
                                                    <td>
                                                        {locked || !can.update ? (
                                                            <span className={`db-badge ${libraryTone(copy.statusTone)}`}>{copy.statusLabel}</span>
                                                        ) : (
                                                            <select
                                                                className="db-input"
                                                                style={{ minHeight: 34, width: 140 }}
                                                                value={copy.status}
                                                                onChange={(event) => updateStatus(copy, event.target.value)}
                                                                aria-label={`Status eksemplar ${copy.code}`}
                                                            >
                                                                {Object.entries(options.copyStatuses).map(([value, label]) => (
                                                                    <option key={value} value={value}>{label}</option>
                                                                ))}
                                                            </select>
                                                        )}
                                                    </td>
                                                    <td><span className="db-hint">{copy.conditionNotes || '—'}</span></td>
                                                    <td><span className="db-hint">{copy.acquiredAt || '—'}</span></td>
                                                    <td>
                                                        {copy.activeLoan ? (
                                                            <span>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{copy.activeLoan.borrower}</b>
                                                                <small className="db-hint">Jatuh tempo {copy.activeLoan.dueAt ?? '—'}</small>
                                                            </span>
                                                        ) : (
                                                            <span className="db-hint">—</span>
                                                        )}
                                                    </td>
                                                    <td>
                                                        <div className="crud-row-actions" style={{ justifyContent: 'flex-end' }}>
                                                            {can.createLoan && copy.status === 'available' && (
                                                                <a className="db-btn ghost sm" href={copy.createLoanUrl} title="Catat peminjaman">
                                                                    <BookMarked size={13} />
                                                                </a>
                                                            )}
                                                            {can.update && (
                                                                <button className="db-btn ghost sm" type="button" onClick={() => setEditCopy(copy)} title="Ubah eksemplar">
                                                                    <Pencil size={13} />
                                                                </button>
                                                            )}
                                                            {can.delete && (
                                                                <button className="db-btn danger sm" type="button" onClick={() => setDeleteCopy(copy)} title="Hapus eksemplar">
                                                                    <Trash2 size={13} />
                                                                </button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Riwayat Peminjaman</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Peminjam</th>
                                            <th>Eksemplar</th>
                                            <th>Pinjam</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Kembali</th>
                                            <th>Status</th>
                                            <th style={{ textAlign: 'right' }}>Denda</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loans.length === 0 && (
                                            <tr><td colSpan={7} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada riwayat peminjaman.</span></td></tr>
                                        )}
                                        {loans.map((loan) => (
                                            <tr key={loan.id}>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{loan.borrower}</b>
                                                    <small className="db-hint">{loan.username}</small>
                                                </td>
                                                <td>{loan.copyCode}</td>
                                                <td>{loan.borrowedAt ?? '—'}</td>
                                                <td>{loan.dueAt ?? '—'}</td>
                                                <td>{loan.returnedAt ?? '—'}</td>
                                                <td><span className={`db-badge ${libraryTone(loan.statusTone)}`}>{loan.statusLabel}</span></td>
                                                <td style={{ textAlign: 'right' }}>
                                                    {loan.fineAmount > 0 ? (
                                                        <span>
                                                            <b>{formatRupiah(loan.fineAmount)}</b>
                                                            <small className="db-hint" style={{ display: 'block' }}>{loan.finePaid ? 'Lunas' : 'Belum lunas'}</small>
                                                        </span>
                                                    ) : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Reservasi</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Anggota</th>
                                            <th>Eksemplar</th>
                                            <th>Diajukan</th>
                                            <th>Siap</th>
                                            <th>Kedaluwarsa</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {reservations.length === 0 && (
                                            <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada reservasi.</span></td></tr>
                                        )}
                                        {reservations.map((reservation) => (
                                            <tr key={reservation.id}>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{reservation.member}</b>
                                                    <small className="db-hint">{reservation.username}</small>
                                                </td>
                                                <td>{reservation.copyCode ?? '—'}</td>
                                                <td>{reservation.reservedAt ?? '—'}</td>
                                                <td>{reservation.readyAt ?? '—'}</td>
                                                <td>{reservation.expiresAt ?? '—'}</td>
                                                <td><span className={`db-badge ${libraryTone(reservation.statusTone)}`}>{reservation.statusLabel}</span></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                {addOpen && (
                    <CopyFormModal
                        title="Tambah Eksemplar"
                        actionUrl={urls.copyStore}
                        method="post"
                        initial={{ code: '', condition_notes: '', acquired_at: '' }}
                        onClose={() => setAddOpen(false)}
                    />
                )}

                {editCopy && (
                    <CopyFormModal
                        title={`Ubah Eksemplar ${editCopy.code}`}
                        actionUrl={editCopy.updateUrl}
                        method="put"
                        initial={{
                            code: editCopy.code,
                            condition_notes: editCopy.conditionNotes ?? '',
                            acquired_at: editCopy.acquiredAtIso ?? '',
                        }}
                        onClose={() => setEditCopy(null)}
                    />
                )}

                <ConfirmModal
                    open={deleteCopy !== null}
                    title="Hapus eksemplar?"
                    message={deleteCopy ? `Eksemplar ${deleteCopy.code} akan dihapus.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteCopy}
                    onCancel={() => setDeleteCopy(null)}
                />

                <ConfirmModal
                    open={deleteBook}
                    title="Hapus buku?"
                    message={`"${book.title}" beserta seluruh eksemplarnya akan dihapus.`}
                    confirmLabel="Ya, hapus buku"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteBook}
                    onCancel={() => setDeleteBook(false)}
                />
            </div>
        </AdminShell>
    );
}
