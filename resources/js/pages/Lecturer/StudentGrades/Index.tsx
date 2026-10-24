import { Head, Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = { studyPlanDetailId: number; nim: string; studentName: string; course: string; class: string; academicYear: string; studyProgram: string; finalScore: number | null; letterGrade: string; gradePoint: number | null; gradeStatus: string; componentCount: number; editUrl: string };

type Props = { shell: ShellProps; gradeRows: Row[]; search: string; urls: { index: string; gradeBook: string } };

export default function StudentGradesIndex({ shell, gradeRows, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Nilai Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={GraduationCap} eyebrow="Akademik" title="Nilai Mahasiswa" description="Daftar nilai mahasiswa per kelas." badges={[`${gradeRows.length} baris`]}
                        actions={<a href={urls.gradeBook} className="db-btn primary">Buku Nilai</a>} />
                    <section className="db-card">
                        <div className="db-card-body">
                            {gradeRows.length === 0 ? <EmptyState icon={GraduationCap} title="Belum ada data nilai" description="Nilai akan muncul di sini." /> : (
                                <div style={{ overflowX: 'auto' }}>
                                    <table className="db-table">
                                        <thead><tr><th>NIM</th><th>Mahasiswa</th><th>Mata Kuliah</th><th>Kelas</th><th>Nilai</th><th>Huruf</th><th>Status</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {gradeRows.map((r) => (
                                                <tr key={r.studyPlanDetailId}>
                                                    <td>{r.nim}</td><td>{r.studentName}</td><td>{r.course}</td><td>{r.class}</td>
                                                    <td>{r.finalScore ?? '-'}</td><td>{r.letterGrade}</td><td>{r.gradeStatus}</td>
                                                    <td><a href={r.editUrl} className="db-btn ghost sm">Edit</a></td>
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