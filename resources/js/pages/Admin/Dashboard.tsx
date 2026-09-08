// Kokpit operasional admin — adaptif berbasis izin (Inertia React).
// Backend merakit sections[] dinamis dari cek ActivePermission; halaman ini
// hanya me-render blok generik (stat, bar, donut, progress, rows, alert).
// Role baru otomatis mendapat isi sesuai haknya tanpa perubahan frontend.
import { Head } from '@inertiajs/react';
import { ArrowUpRight, LayoutDashboard, LockKeyhole } from 'lucide-react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import { EmptyState } from '../../components/Shared/EmptyState';
import { FaIcon } from '../../components/Shared/FaIcon';
import '../../../css/dashboard.css';

type Stat = { label: string; value: string | number; tone: string };
type QuickLink = { label: string; url: string };
type Bar = { title: string; labels: string[]; series: number[] };
type Donut = { title: string; labels: string[]; series: number[] };
type ProgressItem = { label: string; sub: string | null; value: number; max: number; display: string };
type ProgressBlock = { title: string; items: ProgressItem[] };
type RowItem = { title: string; sub: string | null; badge: string | null; badgeTone: string };
type RowBlock = { title: string; actionLabel: string | null; actionUrl: string | null; items: RowItem[] };
type Alert = { tone: string; title: string; message: string; items: string[] };

type Section = {
    key: string; title: string; subtitle: string; icon: string; accent: string;
    stats: Stat[]; quickLinks: QuickLink[];
    bars: Bar[]; donuts: Donut[]; progress: ProgressBlock[]; rows: RowBlock[];
    alerts: Alert[];
};

type Props = {
    shell: ShellProps;
    hero: { name: string; lastLogin: string | null; roleLabel: string | null; sectionCount: number };
    sections: Section[];
};

const CHART_TOKENS = [
    'var(--db-chart-1)', 'var(--db-chart-2)', 'var(--db-chart-3)',
    'var(--db-chart-4)', 'var(--db-chart-5)', 'var(--db-chart-6)',
];

function BarChart({ block }: { block: Bar }) {
    const max = Math.max(1, ...block.series);
    if (block.series.length === 0) return <p className="db-hint" style={{ margin: 0 }}>Belum ada data.</p>;
    return (
        <div className="cx-bars">
            {block.labels.map((label, i) => (
                <div className="cx-bar-row" key={`${label}-${i}`}>
                    <div className="cx-bar-meta"><span>{label}</span><b>{block.series[i] ?? 0}</b></div>
                    <div className="cx-bar-track">
                        <i style={{ width: `${(((block.series[i] ?? 0) / max) * 100).toFixed(1)}%`, background: CHART_TOKENS[i % CHART_TOKENS.length] }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

function DonutChart({ block }: { block: Donut }) {
    const total = block.series.reduce((sum, v) => sum + (v || 0), 0);
    if (total === 0) return <p className="db-hint" style={{ margin: 0 }}>Belum ada data.</p>;
    let acc = 0;
    const stops = block.series.map((v, i) => {
        const from = (acc / total) * 100;
        acc += v || 0;
        const to = (acc / total) * 100;
        return `${CHART_TOKENS[i % CHART_TOKENS.length]} ${from.toFixed(1)}% ${to.toFixed(1)}%`;
    });
    return (
        <div className="cx-donut-wrap">
            <span className="cx-donut" style={{ background: `conic-gradient(${stops.join(', ')})` }}>
                <span className="cx-donut-center"><b>{total}</b><small>total</small></span>
            </span>
            <div className="cx-legend">
                {block.labels.map((label, i) => (
                    <div key={`${label}-${i}`}>
                        <i style={{ background: CHART_TOKENS[i % CHART_TOKENS.length] }} />
                        <span>{label}</span>
                        <b>{block.series[i] ?? 0}</b>
                    </div>
                ))}
            </div>
        </div>
    );
}

function ProgressList({ block }: { block: ProgressBlock }) {
    return (
        <div className="cx-progress">
            {block.items.map((item) => (
                <div key={item.label}>
                    <div className="cx-bar-meta"><span>{item.label}{item.sub ? ` · ${item.sub}` : ''}</span><b>{item.display}</b></div>
                    <div className="cx-bar-track"><i style={{ width: `${Math.min(100, (item.value / Math.max(item.max, 1)) * 100).toFixed(1)}%` }} /></div>
                </div>
            ))}
        </div>
    );
}

function RowList({ block }: { block: RowBlock }) {
    return (
        <div>
            {block.items.length === 0 && <p className="db-hint" style={{ margin: 0 }}>Tidak ada antrean.</p>}
            {block.items.map((item, i) => (
                <div className="db-summary" key={`${item.title}-${i}`}>
                    <div>
                        <b>{item.title}</b>
                        {item.sub && <small>{item.sub}</small>}
                    </div>
                    {item.badge && <span className={`db-badge ${item.badgeTone}`.trim()}>{item.badge}</span>}
                </div>
            ))}
            {block.actionLabel && block.actionUrl && (
                <div style={{ marginTop: 10 }}>
                    <a className="db-btn ghost sm" href={block.actionUrl}>{block.actionLabel} <ArrowUpRight size={13} /></a>
                </div>
            )}
        </div>
    );
}

function SectionBlock({ section }: { section: Section }) {
    const blocks = [
        ...section.bars.map((b, i) => ({ kind: 'bar' as const, el: <BarChart key={`b-${i}`} block={b} />, title: b.title })),
        ...section.donuts.map((d, i) => ({ kind: 'donut' as const, el: <DonutChart key={`d-${i}`} block={d} />, title: d.title })),
        ...section.progress.map((p, i) => ({ kind: 'progress' as const, el: <ProgressList key={`p-${i}`} block={p} />, title: p.title })),
        ...section.rows.map((r, i) => ({ kind: 'rows' as const, el: <RowList key={`r-${i}`} block={r} />, title: r.title })),
    ];

    return (
        <section className="db-card" aria-label={section.title}>
            <div className="db-card-head">
                <h2 className="db-card-title"><FaIcon name={section.icon} size={16} /> {section.title}</h2>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {section.quickLinks.map((link) => (
                        <a className="db-btn ghost sm" key={link.url} href={link.url}>
                            {link.label} <ArrowUpRight size={13} />
                        </a>
                    ))}
                </div>
            </div>
            <div className="db-card-body" style={{ display: 'grid', gap: 16 }}>
                <p className="db-hint" style={{ margin: 0 }}>{section.subtitle}</p>

                {section.stats.length > 0 && (
                    <div className="db-stats" aria-label={`Statistik ${section.title}`}>
                        {section.stats.map((stat) => (
                            <div className="db-stat" key={stat.label}>
                                <div>
                                    <span className="db-stat-num" style={{ fontSize: 19 }}>{stat.value}</span>
                                    <span className="db-stat-label">{stat.label}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {section.alerts.map((alert) => (
                    <div className={`db-note ${alert.tone === 'red' ? '' : 'info'}`} key={alert.title}
                        style={alert.tone === 'red' ? { color: 'var(--db-red)', background: 'var(--db-red-soft)' } : alert.tone === 'green' ? { color: 'var(--db-green)', background: 'var(--db-green-soft)' } : undefined}>
                        <span>
                            <b>{alert.title}.</b> {alert.message}
                            {alert.items.length > 0 && (
                                <span style={{ display: 'block', marginTop: 4 }}>{alert.items.join(' · ')}</span>
                            )}
                        </span>
                    </div>
                ))}

                {blocks.length > 0 && (
                    <div className="cx-blocks">
                        {blocks.map((block, i) => (
                            <div className="cx-block" key={`${block.kind}-${i}`}>
                                <h3>{block.title}</h3>
                                {block.el}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}

export default function AdminDashboard({ shell, hero, sections }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Kokpit Operasional · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <section className="db-hero">
                        <div className="db-hero-inner">
                            <div className="db-hero-copy">
                                <span className="db-hero-icon"><LayoutDashboard size={26} /></span>
                                <div>
                                    <small>Kokpit operasional</small>
                                    <h1>Selamat datang, {hero.name}</h1>
                                    <p>
                                        {hero.sectionCount > 0
                                            ? `${hero.sectionCount} area kerja terlihat berdasarkan izin peran Anda.`
                                            : 'Peran Anda belum memiliki akses ke area operasional mana pun.'}
                                        {hero.lastLogin ? ` Login terakhir ${hero.lastLogin}.` : ''}
                                    </p>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                        {hero.roleLabel && (
                                            <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>
                                                Peran aktif: {hero.roleLabel}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {sections.length === 0 && (
                        <EmptyState
                            icon={LockKeyhole}
                            title="Belum ada area yang dapat diakses"
                            description="Akun Anda belum diberi izin operasional (misalnya kelola pendaftar, tagihan, kelas, atau pengumuman). Hubungi pengelola akses agar izin yang sesuai ditambahkan ke peran Anda."
                        />
                    )}

                    {sections.map((section) => (
                        <SectionBlock key={section.key} section={section} />
                    ))}
                </div>
            </div>
        </AdminShell>
    );
}
