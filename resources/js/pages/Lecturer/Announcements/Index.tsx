import { Head, Link } from '@inertiajs/react';
import { Bell, Inbox, Megaphone, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number; title: string; excerpt: string; priority: string | null; priorityLabel: string; isPinned: boolean;
    creator: string; publishedAt: string; hasAttachment: boolean; url: string; isMine: boolean; editUrl: string | null;
};

type Props = {
    shell: ShellProps;
    tab: string;
    rows: Row[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { search: string; priority: string; perPage: number };
    counts: { inbox: number; mine: number; unread: number };
    urls: { index: string; create: string };
};

export default function AnnouncementsIndex({ shell, tab, rows, filters, counts, urls }: Props) {
    const [activeTab, setActiveTab] = useState(tab);
    return (
        <AdminShell shell={shell}>
            <Head title={`Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Megaphone} eyebrow="Publikasi" title="Pengumuman" description="Pengumuman yang diterima dan yang Anda buat." badges={[`${counts.inbox} inbox`, `${counts.mine} milik saya`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Buat Pengumuman</Link>} />
                    <div style={{ display: 'flex', gap: 8 }}>
                        <a href={`${urls.index}?tab=inbox`} className={`db-btn ${activeTab === 'inbox' ? 'primary' : 'ghost'} sm`}>Inbox ({counts.inbox})</a>
                        <a href={`${urls.index}?tab=mine`} className={`db-btn ${activeTab === 'mine' ? 'primary' : 'ghost'} sm`}>Milik Saya ({counts.mine})</a>
                    </div>
                    <section className="db-card">
                        <div className="db-card-body">
                            {rows.length === 0 ? <EmptyState icon={Inbox} title="Belum ada pengumuman" description="Buat pengumuman baru." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {rows.map((r) => (
                                        <article key={r.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{r.title}</b>
                                                    <small>{r.creator} · {r.publishedAt}{r.isPinned ? ' · 📌' : ''}</small>
                                                </div>
                                                <span className="db-badge gray">{r.priorityLabel}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555', marginTop: 4 }}>{r.excerpt}</div>
                                            <div className="db-class-actions">
                                                <Link href={r.url} className="db-btn ghost sm">Detail</Link>
                                                {r.editUrl && <Link href={r.editUrl} className="db-btn ghost sm">Edit</Link>}
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}