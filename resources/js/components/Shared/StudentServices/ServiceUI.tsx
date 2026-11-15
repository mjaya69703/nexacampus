// Komponen UI bersama halaman layanan mahasiswa (status, statistik, detail, riwayat).
import { CheckCircle2, Circle, Clock3, Info, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { BadgeTone, priorityLabel, priorityTone, statusLabel, statusTone } from './labels';

export function StatusBadge({ status, label }: { status: string; label?: string | null }) {
    return <span className={`db-badge ${statusTone(status)}`}>{label || statusLabel(status)}</span>;
}

export function PriorityBadge({ priority }: { priority: string }) {
    return <span className={`db-badge ${priorityTone(priority)}`}>{priorityLabel(priority)}</span>;
}

export type StatItem = {
    label: string;
    value: number | string;
    icon: LucideIcon;
    tone?: 'gold' | 'green' | 'red' | '';
};

export function ServiceStats({ items, label = 'Ringkasan layanan' }: { items: StatItem[]; label?: string }) {
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

export type DetailItem = {
    label: string;
    value: ReactNode;
    wide?: boolean;
};

export function DetailGrid({ items }: { items: DetailItem[] }) {
    return (
        <div className="db-facts">
            {items.map((item) => (
                <div className={`db-fact${item.wide ? ' wide' : ''}`} key={item.label}>
                    <span>{item.label}</span>
                    <b>{item.value ?? '-'}</b>
                </div>
            ))}
        </div>
    );
}

export type HistoryItem = {
    id: number;
    from: string | null;
    to: string;
    notes: string | null;
    changedBy: string | null;
    changedAt: string | null;
};

export function HistoryTimeline({ histories }: { histories: HistoryItem[] }) {
    if (histories.length === 0) {
        return <p className="db-hint">Belum ada riwayat status.</p>;
    }

    return (
        <div className="db-timeline">
            {histories.map((history) => (
                <div className="db-timeline-item" key={history.id}>
                    <span className={`db-timeline-dot ${statusTone(history.to)}`.trim()} />
                    <div className="db-timeline-main">
                        <div>
                            <b>{statusLabel(history.to)}</b>
                            {history.notes && <small>{history.notes}</small>}
                        </div>
                        <div className="db-timeline-when">
                            <b>{history.changedAt ?? '-'}</b>
                            <small>{history.changedBy ?? 'Sistem'}</small>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

export function ApprovalNote({ approval }: { approval: { status: string; waitingMessage: string | null } | null }) {
    if (!approval) return null;

    const message = approval.waitingMessage
        ?? (approval.status === 'pending'
            ? 'Pengajuan sedang menunggu persetujuan pejabat berwenang.'
            : null);

    if (!message) return null;

    return (
        <div className="db-note info">
            <Clock3 size={15} />
            <span>
                <b>Alur persetujuan:</b> {message}
            </span>
        </div>
    );
}

export function NotesBlock({ title, notes }: { title: string; notes: string | null | undefined }) {
    if (!notes) return null;

    return (
        <div>
            <span className="db-label">{title}</span>
            <div className="db-note muted" style={{ whiteSpace: 'pre-line' }}>{notes}</div>
        </div>
    );
}

export function ChecklistItem({
    checked,
    label,
    meta,
}: {
    checked: boolean;
    label: string;
    meta?: string | null;
}) {
    return (
        <div className="db-check">
            <span className={`db-check-icon ${checked ? 'ok' : ''}`.trim()}>
                {checked ? <CheckCircle2 size={15} /> : <Circle size={15} />}
            </span>
            <div>
                <b>{label}</b>
                {meta && <small>{meta}</small>}
            </div>
        </div>
    );
}

export function InlineNote({ children, tone = 'info' }: { children: ReactNode; tone?: 'info' | 'warning' | 'success' | 'danger' | 'muted' }) {
    return (
        <div className={`db-note ${tone}`}>
            <Info size={15} />
            <span>{children}</span>
        </div>
    );
}
