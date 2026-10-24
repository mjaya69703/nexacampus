import { Head, Link } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; offering: any; urls: { index: string; students: string; attendance: string; grades: string } };

export default function CourseOfferingsShow({ shell, offering, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Kelas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={BookOpen} eyebrow="Akademik" title={`${offering.courseCode} - ${offering.courseName}`} description={offering.label} badges={[offering.status]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Tahun Akademik:</b> {offering.academicYear}</div>
                            <div><b>Program Studi:</b> {offering.studyProgram}</div>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 8 }}>
                                <Link href={urls.students} className="db-btn ghost sm">Mahasiswa</Link>
                                <Link href={urls.attendance} className="db-btn ghost sm">Absensi</Link>
                                <Link href={urls.grades} className="db-btn ghost sm">Nilai</Link>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}