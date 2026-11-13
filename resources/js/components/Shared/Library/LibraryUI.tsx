// Komponen & helper bersama modul Perpustakaan (admin dan portal mahasiswa).
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
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
