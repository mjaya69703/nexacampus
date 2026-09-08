// Hero kompak halaman CRUD — eyecatch di atas tabel (gaya db-hero).
import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import '../../../../css/dashboard.css';

type Props = {
    icon: LucideIcon;
    eyebrow: string;
    title: string;
    description: string;
    badges?: string[];
    actions?: ReactNode;
};

export function CrudHero({ icon: Icon, eyebrow, title, description, badges = [], actions }: Props) {
    return (
        <section className="db-hero">
            <div className="db-hero-inner">
                <div className="db-hero-copy">
                    <span className="db-hero-icon"><Icon size={24} /></span>
                    <div>
                        <small>{eyebrow}</small>
                        <h1>{title}</h1>
                        <p>{description}</p>
                        {badges.length > 0 && (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                {badges.map((badge) => (
                                    <span
                                        key={badge}
                                        className="db-badge gray"
                                        style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}
                                    >
                                        {badge}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
                {actions && <div className="db-hero-actions">{actions}</div>}
            </div>
        </section>
    );
}
