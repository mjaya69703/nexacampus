import { Head, Link } from '@inertiajs/react';
import { Headset, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = { id: number; ticketNumber: string; subject: string; category: string; priority: string; status: string; statusLabel: string; assignedUnit: string | null; submittedAt: string | null; url: string };

type Props = { shell: ShellProps; hasProfile: boolean; complaints: Row[]; summary: { total: number; active: number; waiting: number; resolved: number }; urls: { index: string; create: string } };

export default function ComplaintsIndex({ shell, hasProfile, complaints, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Headset} eyebrow="Student Services" title="Pengaduan" description="Buat pengaduan dan pantau statusnya." badges={[`${summary.total} pengaduan`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Buat Pengaduan</Link>} />
                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Headset size={16} /> Daftar Pengaduan</h2><span className="db-badge gray">{complaints.length}</span></div>
                        <div className="db-card-body">
                            {complaints.length === 0 ? <EmptyState icon={Inbox} title="Belum ada pengaduan" description="Buat pengaduan baru." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {complaints.map((c) => (
                                        <article key={c.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{c.ticketNumber}</b><small>{c.subject} · {c.category}</small></div>
                                                <span className="db-badge gray">{c.statusLabel}</span>
                                            </div>
                                            <div className="db-class-actions"><Link href={c.url} className="db-btn ghost sm">Detail</Link></div>
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
