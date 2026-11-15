// Daftar mahasiswa bimbingan akademik dosen.
import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, MessageSquare, Search, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Student = {
    id: number; studentName: string; nim: string; studyProgram: string; semester: number | string;
    academicStatus: string; academicYear: string; startDate: string; endDate: string; url: string;
};

type Stats = { totalStudents: number; generalAssignments: number; yearAssignments: number; studyPrograms: number; openNotes: number };
type Props = { shell: ShellProps; hasAccess: boolean; students: Student[]; stats: Stats; search: string; urls: { index: string } };

export default function AcademicAdvisingIndex({ shell, hasAccess, students, stats, search, urls }: Props) {
    const [term, setTerm] = useState(search);

    const applySearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(urls.index, term ? { search: term } : {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Bimbingan Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Users}
                        eyebrow="Akademik"
                        title="Bimbingan Akademik"
                        description="Mahasiswa bimbingan Anda beserta status akademiknya."
                        badges={[`${stats.totalStudents} mahasiswa`, `${stats.openNotes} catatan aktif`, `${stats.studyPrograms} prodi`]}
                    />

                    {!hasAccess ? (
                        <div className="db-note warning"><Users size={15} /><span>Anda belum memiliki penugasan bimbingan aktif. Hubungi admin akademik bila ini keliru.</span></div>
                    ) : (
                        <>
                            <section className="db-stats" aria-label="Ringkasan bimbingan">
                                <div className="db-stat"><span className="db-stat-icon"><Users size={20} /></span><div><span className="db-stat-num">{stats.totalStudents}</span><span className="db-stat-label">Mahasiswa</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon gold"><BookOpen size={20} /></span><div><span className="db-stat-num">{stats.yearAssignments}</span><span className="db-stat-label">Per tahun akademik</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon green"><BookOpen size={20} /></span><div><span className="db-stat-num">{stats.generalAssignments}</span><span className="db-stat-label">Penugasan umum</span></div></div>
                                <div className="db-stat"><span className="db-stat-icon red"><MessageSquare size={20} /></span><div><span className="db-stat-num">{stats.openNotes}</span><span className="db-stat-label">Catatan terbuka</span></div></div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><Search size={16} /> Cari mahasiswa</h2></div>
                                <div className="db-card-body">
                                    <form onSubmit={applySearch} style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                        <input className="db-input" value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Nama, NIM, atau program studi…" style={{ maxWidth: 360 }} />
                                        <button className="db-btn primary sm" type="submit"><Search size={13} /> Cari</button>
                                        {search && <button className="db-btn ghost sm" type="button" onClick={() => { setTerm(''); router.get(urls.index, {}, { preserveState: true }); }}>Reset</button>}
                                    </form>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Daftar bimbingan ({students.length})</h2>
                                    {search && <span className="db-hint">Hasil untuk “{search}”</span>}
                                </div>
                                <div className="db-card-body">
                                    {students.length === 0 ? (
                                        <EmptyState icon={Users} title="Belum ada mahasiswa bimbingan" description={search ? 'Tidak ada mahasiswa yang cocok dengan pencarian.' : 'Mahasiswa bimbingan akan muncul di sini.'} />
                                    ) : (
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead><tr><th>NIM</th><th>Nama</th><th>Program studi</th><th>Semester</th><th>Status</th><th>Penugasan</th><th>Aksi</th></tr></thead>
                                                <tbody>
                                                    {students.map((student) => (
                                                        <tr key={student.id}>
                                                            <td>{student.nim}</td>
                                                            <td><b>{student.studentName}</b></td>
                                                            <td>{student.studyProgram}</td>
                                                            <td>{student.semester}</td>
                                                            <td><span className="db-badge gray">{student.academicStatus}</span></td>
                                                            <td>{student.academicYear}<br /><small>{student.startDate} — {student.endDate}</small></td>
                                                            <td><Link href={student.url} className="db-btn ghost sm">Buka bimbingan</Link></td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
