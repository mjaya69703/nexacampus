// Daftar nilai mahasiswa pada kelas dosen.
import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, CheckCircle2, GraduationCap, Search, TrendingUp } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    studyPlanDetailId: number; nim: string; studentName: string; course: string; class: string;
    academicYear: string; studyProgram: string; finalScore: number | null; letterGrade: string;
    gradePoint: number | null; gradeStatus: string; componentCount: number; editUrl: string;
};

type Props = { shell: ShellProps; gradeRows: Row[]; search: string; urls: { index: string; gradeBook: string } };

const tone = (status: string) => {
    if (status === 'Published') return 'green';
    if (status === 'Finalized') return 'amber';
    return 'gray';
};

export default function StudentGradesIndex({ shell, gradeRows, search, urls }: Props) {
    const [term, setTerm] = useState(search);

    const applySearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(urls.index, term ? { search: term } : {}, { preserveState: true, preserveScroll: true });
    };

    const graded = gradeRows.filter((row) => row.finalScore !== null).length;
    const published = gradeRows.filter((row) => row.gradeStatus === 'Published').length;
    const scores = gradeRows.map((row) => row.finalScore).filter((score): score is number => score !== null);
    const average = scores.length > 0 ? (scores.reduce((total, score) => total + Number(score), 0) / scores.length).toFixed(2) : '-';

    return (
        <AdminShell shell={shell}>
            <Head title={`Nilai Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title="Nilai Mahasiswa"
                        description="Daftar nilai mahasiswa per kelas yang Anda ampu."
                        badges={[`${gradeRows.length} baris`, `${graded} dinilai`, `${published} published`]}
                        actions={<Link href={urls.gradeBook} className="db-btn primary"><BookOpen size={15} /> Buku Nilai</Link>}
                    />

                    <section className="db-stats" aria-label="Ringkasan nilai">
                        <div className="db-stat"><span className="db-stat-icon"><GraduationCap size={20} /></span><div><span className="db-stat-num">{gradeRows.length}</span><span className="db-stat-label">Baris nilai</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><CheckCircle2 size={20} /></span><div><span className="db-stat-num">{graded}</span><span className="db-stat-label">Sudah dinilai</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon gold"><TrendingUp size={20} /></span><div><span className="db-stat-num">{average}</span><span className="db-stat-label">Rata-rata</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><BookOpen size={20} /></span><div><span className="db-stat-num">{published}</span><span className="db-stat-label">Published</span></div></div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Search size={16} /> Cari nilai</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={applySearch} style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <input className="db-input" value={term} onChange={(event) => setTerm(event.target.value)} placeholder="Nama, NIM, mata kuliah, atau kelas…" style={{ maxWidth: 380 }} />
                                <button className="db-btn primary sm" type="submit"><Search size={13} /> Cari</button>
                                {search && <button className="db-btn ghost sm" type="button" onClick={() => { setTerm(''); router.get(urls.index, {}, { preserveState: true }); }}>Reset</button>}
                            </form>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar nilai ({gradeRows.length})</h2>
                            {search && <span className="db-hint">Hasil untuk “{search}”</span>}
                        </div>
                        <div className="db-card-body">
                            {gradeRows.length === 0 ? (
                                <EmptyState icon={GraduationCap} title="Belum ada data nilai" description={search ? 'Tidak ada data yang cocok dengan pencarian.' : 'Nilai akan muncul setelah mahasiswa terdaftar pada kelas.'} />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>NIM</th><th>Mahasiswa</th><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th><th>Nilai</th><th>Huruf</th><th>Point</th><th>Komponen</th><th>Status</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {gradeRows.map((row) => (
                                                <tr key={row.studyPlanDetailId}>
                                                    <td>{row.nim}</td>
                                                    <td><b>{row.studentName}</b><br /><small>{row.studyProgram}</small></td>
                                                    <td>{row.course}</td>
                                                    <td>{row.class}</td>
                                                    <td>{row.academicYear}</td>
                                                    <td>{row.finalScore ?? '-'}</td>
                                                    <td><b>{row.letterGrade}</b></td>
                                                    <td>{row.gradePoint ?? '-'}</td>
                                                    <td>{row.componentCount}</td>
                                                    <td><span className={`db-badge ${tone(row.gradeStatus)}`}>{row.gradeStatus}</span></td>
                                                    <td><Link href={row.editUrl} className="db-btn ghost sm">Input nilai</Link></td>
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
