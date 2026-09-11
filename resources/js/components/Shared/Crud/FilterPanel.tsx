// Panel filter lipat kit CRUD shared — toolbar ramping, filter lanjut di dropdown.
import { SlidersHorizontal } from 'lucide-react';
import { ReactNode } from 'react';
import '../../../../css/crud.css';

type Props = {
    /** Jumlah filter aktif (badge). */
    count?: number;
    /** Dipanggil tombol reset; panel tanpa reset bila undefined. */
    onReset?: () => void;
    resetLabel?: string;
    label?: string;
    children: ReactNode;
};

export function FilterPanel({ count = 0, onReset, resetLabel = 'Reset filter', label = 'Filter', children }: Props) {
    return (
        <details className="crud-filter">
            <summary className="db-btn ghost sm" title="Filter lanjutan">
                <SlidersHorizontal size={13} /> {label}
                {count > 0 && <span className="db-badge green">{count}</span>}
            </summary>
            <div className="crud-filter-menu">
                <div className="crud-filter-grid">{children}</div>
                {onReset && (
                    <div className="crud-filter-foot">
                        <button type="button" className="db-btn ghost sm" onClick={onReset}>
                            {resetLabel}
                        </button>
                    </div>
                )}
            </div>
        </details>
    );
}
