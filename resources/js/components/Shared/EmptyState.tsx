// Keadaan kosong generik — satu sumber untuk seluruh blok adm-empty.
import type { CSSProperties, ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';

type Props = {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
    iconSize?: number;
    style?: CSSProperties;
};

export function EmptyState({ icon: Icon, title, description, action, iconSize = 30, style }: Props) {
    return (
        <div className="adm-empty" style={style}>
            <Icon size={iconSize} />
            <h3>{title}</h3>
            <p>{description}</p>
            {action}
        </div>
    );
}
