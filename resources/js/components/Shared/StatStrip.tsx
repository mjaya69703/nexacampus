// Strip statistik tersambung — satu sumber untuk seluruh blok adm-stats.
import type { CSSProperties } from 'react';
import type { LucideIcon } from 'lucide-react';

export type StatStripItem = {
    icon: LucideIcon;
    value: string | number;
    label: string;
    tone?: '' | 'green' | 'gold' | 'red';
};

type Props = {
    items: StatStripItem[];
    style?: CSSProperties;
};

export function StatStrip({ items, style }: Props) {
    return (
        <div className="adm-stats" style={style}>
            {items.map(({ icon: Icon, value, label, tone = '' }) => (
                <div className="adm-stat" key={label}>
                    <span className={`adm-stat-icon ${tone}`}>
                        <Icon size={18} />
                    </span>
                    <div>
                        <strong className="adm-stat-num">{value}</strong>
                        <span className="adm-stat-label">{label}</span>
                    </div>
                </div>
            ))}
        </div>
    );
}
