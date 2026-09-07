// Paginasi klien generik — dipakai Agenda, Berita, dan daftar lain.
import { ChevronLeft, ChevronRight } from 'lucide-react';

type Props = {
    page: number;
    totalPages: number;
    total: number;
    unit: string;
    onPrev: () => void;
    onNext: () => void;
};

export function Pagination({ page, totalPages, total, unit, onPrev, onNext }: Props) {
    if (totalPages <= 1) return null;

    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: 14, marginTop: 24 }}>
            <button type="button" className="adm-btn ghost sm" disabled={page <= 1} onClick={onPrev}>
                <ChevronLeft size={14} /> Sebelumnya
            </button>
            <span className="adm-hint" style={{ fontSize: 13 }}>
                Halaman <b style={{ color: 'var(--adm-brand)' }}>{page}</b> dari {totalPages} &middot; {total} {unit}
            </span>
            <button type="button" className="adm-btn ghost sm" disabled={page >= totalPages} onClick={onNext}>
                Berikutnya <ChevronRight size={14} />
            </button>
        </div>
    );
}
