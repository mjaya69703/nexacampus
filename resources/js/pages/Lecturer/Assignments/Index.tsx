import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = { id: number; courseCode: string; courseName: string; label: string; academicYear: string; studyProgram: string; enrolledCount: number };
type Row = { id: number; title: string; course: string; label: string; academicYear: string; dueAt: string; isPublished: boolean; isPastDue: boolean; enrolled: number; submitted: number; graded: number; returned: number; reviewQueue: number; progress: number; url: string; editUrl: string };

type Props = { shell: ShellProps; offerings: Offering[]; rows: Row[]; urls: { index: string; create: string } };

export default function AssignmentsIndex({ shell, offerings, rows, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Tugas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ClipboardList} eyebrow="Akademik" title="Tugas" description="Daftar tugas dan progres pengumpulan." badges={[`${rows.length} tugas`]}
                        actions={<Link href={urls.create} className="db-btn primary"><Plus size={14} /> Buat Tugas</Link>} />
                    <section className="db-card">
                        <div className="db-card-body">
                            {rows.length === 0 ? <EmptyState icon={ClipboardList} title="Belum ada tugas" description="Buat tugas baru." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {rows.map((r) => (
                                        <article key={r.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{r.title}</b><small>{r.course} · {r.label} · {r.academicYear}</small></div>
                                                <span className="db-badge gray">{r.isPublished ? 'Published' : 'Draft'}</span>
                                            </div>
                                            <div style={{ fontSize: 13, color: '#555' }}>Deadline {r.dueAt} · {r.submitted}/{r.enrolled} dikumpulkan · {r.reviewQueue} perlu review · {r.progress}%</div>
                                            <div className="db-class-actions">
                                                <Link href={r.url} className="db-btn ghost sm">Detail</Link>
                                                <Link href={r.editUrl} className="db-btn ghost sm">Edit</Link>
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