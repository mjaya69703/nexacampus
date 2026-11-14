// Komponen & helper bersama modul Perpustakaan (admin dan portal mahasiswa).
import { useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { CalendarClock, Clock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

/**
 * Bangun query string Inertia: buang nilai kosong dan ubah semua nilai
 * menjadi string agar URL stabil saat filter berubah.
 */
export function buildQuery<F extends object>(
    filters: F,
    search: string,
    overrides: Record<string, string | number | undefined>,
): Record<string, string> {
    const merged: Record<string, unknown> = {
        ...(filters as Record<string, unknown>),
        q: search,
        ...overrides,
    };

    return Object.fromEntries(
        Object.entries(merged)
            .filter(([, value]) => value !== '' && value !== undefined && value !== null)
            .map(([key, value]) => [key, String(value)]),
    );
}

export function formatRupiah(value: number): string {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.max(0, Math.round(value)));
}

export function libraryTone(tone: string | null | undefined): string {
    switch (tone) {
        case 'green':
        case 'red':
        case 'amber':
        case 'blue':
            return tone;
        case 'purple':
            return 'blue';
        default:
            return 'gray';
    }
}

export function LibraryStats({ items, label }: {
    items: Array<{ label: string; value: number | string; icon: LucideIcon; tone?: '' | 'green' | 'gold' | 'red' }>;
    label: string;
}) {
    return (
        <section className="db-stats" aria-label={label}>
            {items.map((item) => {
                const Icon = item.icon;

                return (
                    <div className="db-stat" key={item.label}>
                        <span className={`db-stat-icon ${item.tone ?? ''}`.trim()}><Icon size={20} /></span>
                        <div>
                            <span className="db-stat-num">{item.value}</span>
                            <span className="db-stat-label">{item.label}</span>
                        </div>
                    </div>
                );
            })}
        </section>
    );
}

type LibraryModalProps = {
    title: string;
    description?: string;
    onClose: () => void;
    children: ReactNode;
};

/** Modal ringkas bertoken db-* agar aman di mode terang maupun gelap. */
export function LibraryModal({ title, description, onClose, children }: LibraryModalProps) {
    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal wide" onClick={(event) => event.stopPropagation()}>
                    <h3>{title}</h3>
                    {description && <p>{description}</p>}
                    <div style={{ display: 'grid', gap: 12, marginTop: 16, textAlign: 'left' }}>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}

export type ReserveBookInfo = {
    id: number;
    title: string;
    author: string | null;
    availableCount: number;
    queueCount: number;
    estimatedReadyAt: string | null;
};

function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function addDays(date: Date, days: number): Date {
    const result = new Date(date);
    result.setDate(result.getDate() + days);

    return result;
}

function formatDate(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(`${value}T00:00:00`) : value;

    return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * Modal pengajuan reservasi dengan rencana tanggal ambil, info antrean,
 * estimasi ketersediaan, dan perkiraan jatuh tempo peminjaman.
 */
export function ReserveBookModal({ book, url, loanDays, holdDays, pickupPlanDays, onClose }: {
    book: ReserveBookInfo;
    url: string;
    loanDays: number;
    holdDays: number;
    pickupPlanDays: number;
    onClose: () => void;
}) {
    const today = new Date();
    const maxDate = toIsoDate(addDays(today, pickupPlanDays));
    const form = useForm({
        book_id: book.id,
        pickup_planned_at: toIsoDate(today),
        notes: '',
    });

    const plannedDate = form.data.pickup_planned_at || toIsoDate(today);
    const estimatedDueDate = formatDate(addDays(new Date(`${plannedDate}T00:00:00`), loanDays));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <LibraryModal
            title="Ajukan Reservasi"
            description={`${book.title}${book.author ? ` — ${book.author}` : ''}`}
            onClose={onClose}
        >
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                {book.availableCount === 0 ? (
                    <div className="db-note warning">
                        <Clock size={15} />
                        <span>
                            Semua eksemplar sedang dipinjam/ditahan.
                            {book.queueCount > 0 && <> Antrean saat ini <b>{book.queueCount} anggota</b>.</>}
                            {book.estimatedReadyAt && <> Perkiraan tersedia <b>{book.estimatedReadyAt}</b>.</>}
                        </span>
                    </div>
                ) : (
                    <div className="db-note info">
                        <CalendarClock size={15} />
                        <span><b>{book.availableCount} eksemplar tersedia.</b> Petugas akan menyiapkan buku sesuai rencana tanggal ambil Anda.</span>
                    </div>
                )}

                <div>
                    <label className="crud-label" htmlFor="reserve-pickup">Rencana tanggal ambil <span>*</span></label>
                    <input
                        id="reserve-pickup"
                        type="date"
                        className="db-input"
                        min={toIsoDate(today)}
                        max={maxDate}
                        value={form.data.pickup_planned_at}
                        onChange={(event) => form.setData('pickup_planned_at', event.target.value)}
                    />
                    {form.errors.pickup_planned_at && <div className="db-error">{form.errors.pickup_planned_at}</div>}
                </div>

                <div className="db-note muted">
                    <CalendarClock size={15} />
                    <span>
                        Masa pinjam <b>{loanDays} hari</b>, perkiraan jatuh tempo <b>{estimatedDueDate}</b>.
                        Reservasi ditahan <b>{holdDays} hari</b> setelah dinyatakan siap diambil.
                    </span>
                </div>

                <div>
                    <label className="crud-label" htmlFor="reserve-notes">Catatan (opsional)</label>
                    <textarea
                        id="reserve-notes"
                        className="db-input"
                        rows={3}
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        placeholder="Contoh: referensi untuk tugas mata kuliah…"
                    />
                    {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                    {form.errors.book_id && <div className="db-error">{form.errors.book_id}</div>}
                </div>

                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>
                        {form.processing ? 'Mengajukan…' : 'Ajukan Reservasi'}
                    </button>
                </div>
            </form>
        </LibraryModal>
    );
}
