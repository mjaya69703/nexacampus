import { Head, Link } from '@inertiajs/react';
import { GraduationCap, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = { id: number; number: string; status: string; graduationPeriod: string | null; graduationDate: string | null; submittedAt: string | null; url: string };

type Props = { shell: ShellProps; hasProfile: boolean; applications: Row[]; summary: { total: number; in_progress: number; approved: number; finalized: number }; urls: { index: string; create: string } };

export default function GraduationsIndex({ shell, hasProfile, applications, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={GraduationCap} eyebrow="Student Services" title="Yudisium" description="Pengajuan yudisium dan riwayatnya." badges={[`${summary.total} pengajuan`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Ajukan Yudisium</Link>} />
                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Daftar Pengajuan</h2><span className="db-badge gray">{applications.length}</span></div>
                        <div className="db-card-body">
                            {applications.length === 0 ? <EmptyState icon={Inbox} title="Belum ada pengajuan yudisium" description="Ajukan yudisium baru." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {applications.map((a) => (
                                        <article key={a.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{a.number}</b><small>{a.graduationPeriod ?? '-'} · {a.graduationDate ?? '-'}</small></div>
                                                <span className="db-badge gray">{a.status}</span>
                                            </div>
                                            <div className="db-class-actions"><Link href={a.url} className="db-btn ghost sm">Detail</Link></div>
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
