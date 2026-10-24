import { Head, Link } from '@inertiajs/react';
import { BookOpen, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = { id: number; courseCode: string; courseName: string; classCode: string; label: string; academicYear: string; studyProgram: string; status: string; lecturerRole: string; scheduleCount: number; sessionCount: number; capacity: number | null; enrolledCount: number; url: string };

type Props = { shell: ShellProps; hasProfile: boolean; lecturerInfo: any; offerings: Offering[]; yearOptions: string[]; statusOptions: string[]; modeOptions: string[]; stats: any; urls: { index: string } };

export default function CourseOfferingsIndex({ shell, hasProfile, lecturerInfo, offerings, stats, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Kelas Saya · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={BookOpen} eyebrow="Akademik" title="Kelas Saya" description={hasProfile ? `${lecturerInfo.name} · ${lecturerInfo.study_program}` : 'Profil dosen belum ditemukan.'} badges={[`${stats.totalOfferings} kelas`, `${stats.totalStudents} mahasiswa`]} />
                    {!hasProfile && <div className="db-note danger">Profil dosen belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {offerings.length === 0 ? <EmptyState icon={Users} title="Belum ada kelas" description="Kelas yang Anda ampu akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {offerings.map((o) => (
                                        <article key={o.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{o.courseCode} - {o.courseName}</b>
                                                    <small>{o.classCode} · {o.academicYear} · {o.studyProgram}</small>
                                                </div>
                                                <span className="db-badge gray">{o.status}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555' }}>
                                                {o.enrolledCount}/{o.capacity ?? '∞'} mahasiswa · {o.scheduleCount} jadwal · {o.sessionCount} sesi · {o.lecturerRole}
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={o.url} className="db-btn ghost sm">Detail</Link>
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