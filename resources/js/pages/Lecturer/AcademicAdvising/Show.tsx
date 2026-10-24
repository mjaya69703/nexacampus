import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; assignmentId: number; assignment: any; progress: any; pendingPlans: any[]; notes: any[]; urls: { index: string } };

export default function AcademicAdvisingShow({ shell, assignmentId, assignment, pendingPlans, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Bimbingan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Users} eyebrow="Akademik" title="Detail Bimbingan" description={assignment.studentName ? `${assignment.studentName} (${assignment.nim})` : `Penugasan #${assignmentId}`} badges={[]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">KRS Menunggu Persetujuan</h2></div>
                        <div className="db-card-body">
                            {pendingPlans.length === 0 ? <div>Belum ada KRS menunggu persetujuan.</div> : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {pendingPlans.map((p: any) => (
                                        <div key={p.id} style={{ padding: 8, border: '1px solid #eee', borderRadius: 8 }}>
                                            {p.year} · Sem {p.semester} · {p.courses} mata kuliah · {p.credits} SKS · {p.submittedAt}
                                        </div>
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