import { Head, Link } from '@inertiajs/react';
import { Briefcase } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Period = { id: number; name: string; status: string };
type Submission = { id: number; period: string; status: string; teachingSks: number; structuralSks: number; tridharmaSks: number; totalSks: number; updatedAt: string | null; url: string };

type Props = { shell: ShellProps; periods: Period[]; submissions: Submission[]; selectedPeriodId: string; urls: { index: string } };

export default function WorkloadsIndex({ shell, periods, submissions, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Beban Kerja · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Briefcase} eyebrow="Akademik" title="Beban Kerja (BKD)" description="Pengajuan beban kerja dosen." badges={[`${submissions.length} pengajuan`]} />
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Pengajuan</h2></div>
                        <div className="db-card-body">
                            {submissions.length === 0 ? <EmptyState icon={Briefcase} title="Belum ada pengajuan" description="Generate draf BKD dari periode yang tersedia." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {submissions.map((s) => (
                                        <article key={s.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{s.period}</b><small>Total {s.totalSks} SKS</small></div>
                                                <span className="db-badge gray">{s.status}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555' }}>Pengajaran {s.teachingSks} · Struktural {s.structuralSks} · Tridharma {s.tridharmaSks} · {s.updatedAt}</div>
                                            <div className="db-class-actions"><Link href={s.url} className="db-btn ghost sm">Detail</Link></div>
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