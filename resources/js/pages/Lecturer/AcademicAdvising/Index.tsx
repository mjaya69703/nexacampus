import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Student = { id: number; studentName: string; nim: string; studyProgram: string; semester: any; academicStatus: string; academicYear: string; startDate: string; endDate: string; url: string };

type Props = { shell: ShellProps; hasAccess: boolean; students: Student[]; stats: any; search: string; urls: { index: string } };

export default function AcademicAdvisingIndex({ shell, hasAccess, students, stats, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Bimbingan Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Users} eyebrow="Akademik" title="Bimbingan Akademik" description="Mahasiswa bimbingan Anda." badges={[`${stats.totalStudents} mahasiswa`]} />
                    {!hasAccess && <div className="db-note danger">Anda belum memiliki penugasan bimbingan aktif.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {students.length === 0 ? <EmptyState icon={Users} title="Belum ada mahasiswa bimbingan" description="Mahasiswa bimbingan akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {students.map((s) => (
                                        <article key={s.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{s.studentName}</b><small>{s.nim} · {s.studyProgram} · Sem {s.semester}</small></div>
                                                <span className="db-badge gray">{s.academicStatus}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555' }}>{s.academicYear} · {s.startDate} - {s.endDate}</div>
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