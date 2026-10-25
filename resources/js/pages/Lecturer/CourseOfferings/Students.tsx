// Daftar mahasiswa terdaftar pada kelas dosen.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Repeat, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Student = {
    studyPlanDetailId: number; nim: string; name: string; studyProgram: string;
    semester: number | string; status: string; credits: number; isRepeat: boolean;
};

type Props = {
    shell: ShellProps;
    offeringId: number;
    classInfo: { course: string; class: string; academicYear: string; studyProgram: string };
    students: Student[];
    stats: { total: number; repeat: number; credits: number };
    urls: { index: string; show: string; grades: string };
};

export default function CourseOfferingsStudents({ shell, classInfo, students, stats, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Mahasiswa ${classInfo.course} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Akademik · Kelas"
                        title="Mahasiswa Kelas"
                        description={`${classInfo.course} · ${classInfo.class} · ${classInfo.academicYear}`}
                        badges={[`${stats.total} mahasiswa`, `${stats.credits} SKS`, `${stats.repeat} mengulang`]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.show}><ArrowLeft size={15} /> Detail kelas</Link>
                                <Link className="db-btn light" href={urls.grades}><GraduationCap size={15} /> Nilai</Link>
                            </div>
                        }
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar peserta ({students.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {students.length === 0 ? (
                                <EmptyState icon={Users} title="Belum ada mahasiswa" description="Mahasiswa dengan KRS Approved akan tampil di sini." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>NIM</th><th>Nama</th><th>Program studi</th><th>Semester</th>
                                                <th>Status</th><th>SKS</th><th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {students.map((student) => (
                                                <tr key={student.studyPlanDetailId}>
                                                    <td>{student.nim}</td>
                                                    <td><b>{student.name}</b></td>
                                                    <td>{student.studyProgram}</td>
                                                    <td>{student.semester}</td>
                                                    <td><span className="db-badge gray">{student.status}</span></td>
                                                    <td>{student.credits}</td>
                                                    <td>{student.isRepeat ? <span className="db-badge amber"><Repeat size={12} /> Mengulang</span> : '-'}</td>
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
