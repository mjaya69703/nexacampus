// Buku nilai — rekap seluruh kelas dengan filter, distribusi huruf, dan ekspor.
import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Download, FileSpreadsheet, Filter, GraduationCap, Search, TrendingUp, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    study_plan_detail_id: number; nim: string; student_name: string; course: string; class: string;
    academic_year: string; semester_no: number | string; study_program: string;
    final_score: number | null; letter_grade: string; grade_point: number | null;
    grade_status: string; result_status: string; component_count: number;
};

type Statistics = {
    total_students: number; graded_count: number; finalized_count: number;
    average_score: number | null; median_score: number | null; highest_score: number | null;
    lowest_score: number | null; standard_deviation: number | null; pass_rate: number | null;
};

type Option = { id: string; label: string };
type Distribution = { letter: string; count: number; percentage: number };

type Props = {
    shell: ShellProps;
    rows: Row[];
    statistics: Statistics;
    distribution: Distribution[];
    courseOptions: Option[];
    yearOptions: Option[];
    semesterOptions: Option[];
    filters: { search: string; courseOfferingId: string; academicYearId: string; semesterNo: string };
    urls: { index: string; gradesIndex: string; exportCsv: string; exportXlsx: string; exportPdf: string };
};

const tone = (status: string) => {
    if (status === 'Published') return 'green';
    if (status === 'Finalized') return 'amber';
    return 'gray';
};

export default function StudentGradesGradeBook({ shell, rows, statistics, distribution, courseOptions, yearOptions, semesterOptions, filters, urls }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [courseOfferingId, setCourseOfferingId] = useState(filters.courseOfferingId);
    const [academicYearId, setAcademicYearId] = useState(filters.academicYearId);
    const [semesterNo, setSemesterNo] = useState(filters.semesterNo);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(urls.index, {
            search,
            course_offering_id: courseOfferingId,
            academic_year_id: academicYearId,
            semester_no: semesterNo,
        }, { preserveState: true, preserveScroll: true });
    };

    const resetFilters = () => {
        setSearch('');
        setCourseOfferingId('');
        setAcademicYearId('');
        setSemesterNo('');
        router.get(urls.index, {}, { preserveState: true, preserveScroll: true });
    };

    const cards = [
        { label: 'Mahasiswa', value: statistics.total_students, icon: <Users size={20} />, tone: 'db-stat-icon' },
        { label: 'Sudah dinilai', value: `${statistics.graded_count}/${statistics.total_students}`, icon: <GraduationCap size={20} />, tone: 'db-stat-icon green' },
        { label: 'Rata-rata', value: statistics.average_score ?? '-', icon: <TrendingUp size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Finalized', value: statistics.finalized_count, icon: <BookOpen size={20} />, tone: 'db-stat-icon' },
    ];

    const maxDistribution = Math.max(1, ...distribution.map((item) => item.count));

    return (
        <AdminShell shell={shell}>
            <Head title={`Buku Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik · Nilai"
                        title="Buku Nilai"
                        description="Rekap nilai seluruh kelas yang Anda ampu, lengkap dengan distribusi dan ekspor."
                        badges={[`${statistics.total_students} mahasiswa`, `${statistics.graded_count} dinilai`, `Rata-rata ${statistics.average_score ?? '-'}`]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.gradesIndex}><GraduationCap size={15} /> Daftar nilai</Link>
                                <a className="db-btn light" href={urls.exportCsv}><Download size={15} /> CSV</a>
                                <a className="db-btn light" href={urls.exportXlsx}><FileSpreadsheet size={15} /> XLSX</a>
                                <a className="db-btn light" href={urls.exportPdf}><Download size={15} /> PDF</a>
                            </div>
                        }
                    />

                    <section className="db-stats" aria-label="Statistik buku nilai">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Filter size={16} /> Filter nilai</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={applyFilters} className="crud-grid">
                                <div>
                                    <label className="crud-label">Cari mahasiswa / mata kuliah</label>
                                    <input className="db-input" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama, NIM, mata kuliah…" />
                                </div>
                                <div>
                                    <label className="crud-label">Kelas</label>
                                    <select className="db-input" value={courseOfferingId} onChange={(event) => setCourseOfferingId(event.target.value)}>
                                        <option value="">Semua kelas</option>
                                        {courseOptions.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Tahun akademik</label>
                                    <select className="db-input" value={academicYearId} onChange={(event) => setAcademicYearId(event.target.value)}>
                                        <option value="">Semua tahun</option>
                                        {yearOptions.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Semester</label>
                                    <select className="db-input" value={semesterNo} onChange={(event) => setSemesterNo(event.target.value)}>
                                        <option value="">Semua semester</option>
                                        {semesterOptions.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
                                    </select>
                                </div>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end', gridColumn: '1 / -1' }}>
                                    <button className="db-btn primary sm" type="submit"><Search size={13} /> Terapkan</button>
                                    <button className="db-btn ghost sm" type="button" onClick={resetFilters}>Reset</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Distribusi huruf</h2></div>
                            <div className="db-card-body">
                                {distribution.length === 0 ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada data untuk ditampilkan.</p>
                                ) : (
                                    <div style={{ display: 'grid', gap: 10 }}>
                                        {distribution.map((item) => (
                                            <div className="cx-bar-row" key={item.letter}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4, fontSize: 13 }}>
                                                    <b>{item.letter}</b>
                                                    <span className="db-hint">{item.count} mahasiswa · {item.percentage}%</span>
                                                </div>
                                                <div className="cx-bar-track"><i style={{ width: `${(item.count / maxDistribution) * 100}%` }} /></div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Statistik nilai</h2></div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 8, fontSize: 13 }}>
                                <div className="db-summary"><div><b>Rata-rata</b><small>Mean nilai akhir</small></div><span className="db-badge gray">{statistics.average_score ?? '-'}</span></div>
                                <div className="db-summary"><div><b>Median</b><small>Nilai tengah</small></div><span className="db-badge gray">{statistics.median_score ?? '-'}</span></div>
                                <div className="db-summary"><div><b>Tertinggi</b><small>Nilai maksimum</small></div><span className="db-badge green">{statistics.highest_score ?? '-'}</span></div>
                                <div className="db-summary"><div><b>Terendah</b><small>Nilai minimum</small></div><span className="db-badge red">{statistics.lowest_score ?? '-'}</span></div>
                                <div className="db-summary"><div><b>Standar deviasi</b><small>Sebaran nilai</small></div><span className="db-badge gray">{statistics.standard_deviation ?? '-'}</span></div>
                                <div className="db-summary"><div><b>Pass rate</b><small>Persentase lulus</small></div><span className="db-badge amber">{statistics.pass_rate ?? '-'}%</span></div>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><BookOpen size={16} /> Daftar nilai ({rows.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <EmptyState icon={BookOpen} title="Belum ada nilai" description="Nilai akan tampil setelah mahasiswa terdaftar dan dinilai." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>NIM</th><th>Mahasiswa</th><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th>
                                                <th>Nilai</th><th>Huruf</th><th>Point</th><th>Komponen</th><th>Status</th><th>Hasil</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => (
                                                <tr key={row.study_plan_detail_id}>
                                                    <td>{row.nim}</td>
                                                    <td><b>{row.student_name}</b><br /><small>{row.study_program}</small></td>
                                                    <td>{row.course}</td>
                                                    <td>{row.class}</td>
                                                    <td>{row.academic_year}</td>
                                                    <td>{row.final_score ?? '-'}</td>
                                                    <td><b>{row.letter_grade}</b></td>
                                                    <td>{row.grade_point ?? '-'}</td>
                                                    <td>{row.component_count}</td>
                                                    <td><span className={`db-badge ${tone(row.grade_status)}`}>{row.grade_status}</span></td>
                                                    <td>{row.result_status}</td>
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
