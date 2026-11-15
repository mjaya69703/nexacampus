// Helper & komponen bersama modul Kemahasiswaan (admin).
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import '../../../../css/dashboard.css';

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

export function toneClass(tone: string | null | undefined): string {
    return ['green', 'red', 'amber', 'blue', 'gray'].includes(tone ?? '') ? (tone as string) : 'gray';
}

export function StudentAffairsStats({ items, label }: {
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

export function StudentAffairsModal({ title, description, onClose, children }: {
    title: string;
    description?: string;
    onClose: () => void;
    children: ReactNode;
}) {
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

export function initialsFor(name: string | null | undefined): string {
    return (name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
