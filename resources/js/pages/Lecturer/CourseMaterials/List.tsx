import { Head, Link } from '@inertiajs/react';
import { BookOpen, FileText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = { id: number; courseCode: string; courseName: string; label: string; academicYear: string; studyProgram: string; enrolledCount: number; url: string };

type Props = { shell: ShellProps; hasProfile: boolean; offerings: Offering[]; urls: { list: string; index: string } };

export default function CourseMaterialsList({ shell, hasProfile, offerings, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Materi Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Akademik" title="Materi Kuliah" description="Pilih kelas untuk mengelola materi." badges={[`${offerings.length} kelas`]} />
                    {!hasProfile && <div className="db-note danger">Profil dosen belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {offerings.length === 0 ? <EmptyState icon={BookOpen} title="Belum ada kelas" description="Kelas yang Anda ampu akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {offerings.map((o) => (
                                        <article key={o.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{o.courseCode} - {o.courseName}</b><small>{o.label} · {o.academicYear} · {o.studyProgram}</small></div>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555' }}>{o.enrolledCount} mahasiswa</div>
                                            <div className="db-class-actions">
                                                <Link href={urls.index.replace('0', String(o.id))} className="db-btn ghost sm">Materi</Link>
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