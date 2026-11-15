// Nilai kelas — rekap nilai per mahasiswa dan pintasan input nilai.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, CheckCircle2, GraduationCap, TrendingUp, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type GradeRow = {
    studyPlanDetailId: number; nim: string; name: string; finalScore: number | null;
    letterGrade: string; gradePoint: number | null; gradeStatus: string;
    componentCount: number; editUrl: string;
};

type Props = {
    shell: ShellProps;
    offeringId: number;
    classInfo: { course: string; class: string; academicYear: string; studyProgram: string };
    gradeRows: GradeRow[];
    stats: { total: number; graded: number; average: number | null; published: number; draft: number };
    urls: { index: string; show: string; gradeBook: string };
};

const tone = (status: string) => {
    if (status === 'Published') return 'green';
    if (status === 'Finalized') return 'amber';
    return 'gray';
};

export default function CourseOfferingsGrades({ shell, classInfo, gradeRows, stats, urls }: Props) {
    const cards = [
        { label: 'Mahasiswa', value: stats.total, icon: <Users size={20} />, tone: 'db-stat-icon' },
        { label: 'Sudah dinilai', value: stats.graded, icon: <CheckCircle2 size={20} />, tone: 'db-stat-icon green' },
        { label: 'Rata-rata', value: stats.average ?? '-', icon: <TrendingUp size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Published', value: stats.published, icon: <GraduationCap size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Nilai ${classInfo.course} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik · Kelas"
                        title="Nilai Kelas"
                        description={`${classInfo.course} · ${classInfo.class} · ${classInfo.academicYear}`}
                        badges={[`${stats.graded}/${stats.total} dinilai`, `${stats.draft} draft`, `${stats.published} published`]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.show}><ArrowLeft size={15} /> Detail kelas</Link>
                                <Link className="db-btn light" href={urls.gradeBook}><BookOpen size={15} /> Buku nilai</Link>
                            </div>
                        }
                    />

                    <section className="db-stats" aria-label="Ringkasan nilai">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar nilai</h2>
                        </div>
                        <div className="db-card-body">
                            {gradeRows.length === 0 ? (
                                <EmptyState icon={GraduationCap} title="Belum ada mahasiswa" description="Nilai akan muncul setelah mahasiswa terdaftar pada kelas." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>NIM</th><th>Mahasiswa</th><th>Nilai akhir</th><th>Huruf</th>
                                                <th>Grade point</th><th>Komponen</th><th>Status</th><th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {gradeRows.map((row) => (
                                                <tr key={row.studyPlanDetailId}>
                                                    <td>{row.nim}</td>
                                                    <td><b>{row.name}</b></td>
                                                    <td>{row.finalScore ?? '-'}</td>
                                                    <td><b>{row.letterGrade}</b></td>
                                                    <td>{row.gradePoint ?? '-'}</td>
                                                    <td>{row.componentCount}</td>
                                                    <td><span className={`db-badge ${tone(row.gradeStatus)}`}>{row.gradeStatus}</span></td>
                                                    <td><a className="db-btn ghost sm" href={row.editUrl}>Input nilai</a></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
