// Helper & komponen bersama halaman admin Kepegawaian (Inertia React).
import type { LucideIcon } from 'lucide-react';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

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

export type OrgStatItem = {
    label: string;
    value: number | string;
    icon: LucideIcon;
    tone?: 'green' | 'gold' | 'red';
};

/** Ringkasan statistik bergaya db-stats (mendukung mode gelap). */
export function OrgStats({ items, label = 'Ringkasan kepegawaian' }: { items: OrgStatItem[]; label?: string }) {
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
