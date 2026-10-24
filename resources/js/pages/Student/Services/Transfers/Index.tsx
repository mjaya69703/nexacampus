import { Head, Link } from '@inertiajs/react';
import { ArrowRightLeft, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = { id: number; number: string; fromProgram: string; toProgram: string; type: string; status: string; submittedAt: string | null; url: string };

type Props = { shell: ShellProps; hasProfile: boolean; requests: Row[]; summary: { total: number; in_progress: number; approved: number; applied: number }; urls: { index: string; create: string } };

export default function TransfersIndex({ shell, hasProfile, requests, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Pindah Program · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ArrowRightLeft} eyebrow="Student Services" title="Pindah Program" description="Pengajuan pindah program studi/fakultas/kelas." badges={[`${summary.total} pengajuan`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Ajukan Pindah</Link>} />
                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><ArrowRightLeft size={16} /> Daftar Pengajuan</h2><span className="db-badge gray">{requests.length}</span></div>
                        <div className="db-card-body">
                            {requests.length === 0 ? <EmptyState icon={Inbox} title="Belum ada pengajuan pindah" description="Ajukan pindah baru." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {requests.map((r) => (
                                        <article key={r.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{r.number}</b><small>{r.fromProgram} → {r.toProgram} · {r.type}</small></div>
                                                <span className="db-badge gray">{r.status}</span>
                                            </div>
                                            <div className="db-class-actions"><Link href={r.url} className="db-btn ghost sm">Detail</Link></div>
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
