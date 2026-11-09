// Helper & komponen bersama halaman admin Layanan Mahasiswa (Inertia React).
import type { ReactNode } from 'react';
import { StatusBadge } from '../../../components/Shared/StudentServices/ServiceUI';
import { statusLabel } from '../../../components/Shared/StudentServices/labels';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

export { StatusBadge, statusLabel };

/**
 * Bangun query string untuk navigasi Inertia: buang nilai kosong dan
 * pastikan seluruh nilai menjadi string agar URL stabil.
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

/** Label status pengaduan versi admin (berbeda dari label portal mahasiswa). */
export const COMPLAINT_STATUS_LABELS: Record<string, string> = {
    submitted: 'Masuk',
    in_review: 'Direview',
    waiting_student: 'Menunggu Mahasiswa',
    responded: 'Sudah Dibalas',
    resolved: 'Selesai',
    closed: 'Ditutup',
    rejected: 'Ditolak',
    reopened: 'Dibuka Lagi',
};

/** Label kategori alasan cuti sesuai pilihan pada form mahasiswa. */
export const LEAVE_REASON_LABELS: Record<string, string> = {
    health: 'Kesehatan',
    financial: 'Keuangan',
    personal: 'Pribadi / Keluarga',
    work: 'Pekerjaan',
    academic: 'Akademik',
    other: 'Lainnya',
};

export function leaveReasonLabel(reason: string | null | undefined): string {
    if (!reason) return '-';
    return LEAVE_REASON_LABELS[reason] ?? reason.replace(/[_-]+/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

type WorkflowModalProps = {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    danger?: boolean;
    processing?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
    children?: ReactNode;
};

/**
 * Modal aksi workflow (catatan / unggah berkas). Memakai token db-* agar
 * tetap konsisten pada mode terang maupun gelap.
 */
export function WorkflowModal({
    open,
    title,
    message,
    confirmLabel,
    danger = false,
    processing = false,
    onConfirm,
    onCancel,
    children,
}: WorkflowModalProps) {
    if (!open) return null;

    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onCancel} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal wide" onClick={(event) => event.stopPropagation()}>
                    <h3>{title}</h3>
                    <p>{message}</p>
                    {children && (
                        <div style={{ display: 'grid', gap: 12, marginTop: 16, textAlign: 'left' }}>
                            {children}
                        </div>
                    )}
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
                            {processing ? 'Memproses…' : confirmLabel}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
