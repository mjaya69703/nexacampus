// Daftar kelas untuk mengelola materi kuliah.
import { Head, Link } from '@inertiajs/react';
import { BookOpen, FileText, MapPin, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = {
    id: number; courseCode: string; courseName: string; label: string; academicYear: string;
    studyProgram: string; deliveryMode: string; room: string; building: string; enrolledCount: number; url: string;
};

type Props = { shell: ShellProps; hasProfile: boolean; offerings: Offering[]; urls: { list: string; index: string } };

export default function CourseMaterialsList({ shell, hasProfile, offerings }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Materi Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Akademik"
                        title="Materi Kuliah"
                        description="Pilih kelas untuk mengelola materi, berkas, dan publikasinya."
                        badges={[`${offerings.length} kelas`]}
                    />
                    {!hasProfile && <div className="db-note danger"><FileText size={15} /><span>Profil dosen belum ditemukan.</span></div>}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><BookOpen size={16} /> Kelas Anda</h2>
                        </div>
                        <div className="db-card-body">
                            {offerings.length === 0 ? (
                                <EmptyState icon={BookOpen} title="Belum ada kelas" description="Kelas yang Anda ampu akan muncul di sini." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th><th>Mode</th><th>Ruang</th><th>Mahasiswa</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {offerings.map((offering) => (
                                                <tr key={offering.id}>
                                                    <td><b>{offering.courseCode} - {offering.courseName}</b><br /><small>{offering.studyProgram}</small></td>
                                                    <td>{offering.label}</td>
                                                    <td>{offering.academicYear}</td>
                                                    <td>{offering.deliveryMode}</td>
                                                    <td><MapPin size={12} /> {offering.room} ({offering.building})</td>
                                                    <td><Users size={12} /> {offering.enrolledCount}</td>
                                                    <td><Link href={offering.url} className="db-btn ghost sm"><FileText size={12} /> Kelola materi</Link></td>
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
