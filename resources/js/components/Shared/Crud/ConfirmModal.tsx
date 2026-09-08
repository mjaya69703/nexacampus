// Modal konfirmasi kit CRUD shared (gaya db-modal dashboard).
import { TriangleAlert } from 'lucide-react';
import '../../../../css/dashboard.css';

type Props = {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    danger?: boolean;
    processing?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
};

export function ConfirmModal({ open, title, message, confirmLabel = 'Ya, lanjutkan', danger = true, processing, onConfirm, onCancel }: Props) {
    if (!open) return null;

    // Bungkus db-root sendiri agar token --db-* selalu tersedia
    // di mana pun modal dipasang (wajib di dalam AdminShell).
    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onCancel} role="dialog" aria-modal="true" aria-label={title}>
            <div className="db-modal" onClick={(e) => e.stopPropagation()}>
                <span className={`db-modal-icon${danger ? ' red' : ''}`}>
                    <TriangleAlert size={22} />
                </span>
                <h3>{title}</h3>
                <p>{message}</p>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onCancel} disabled={processing}>
                        Batal
                    </button>
                    <button
                        className={`db-btn ${danger ? 'danger' : 'primary'}`}
                        type="button"
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
            </div>
        </div>
    );
}
