// Hasil EDOM dosen — ringkasan penilaian, rekap per mata kuliah, komentar, dan review kinerja.
import { Head, Link, router } from '@inertiajs/react';
import {
    BarChart3, BookOpen, CalendarDays, EyeOff, GraduationCap, MessageSquareQuote, ShieldCheck, Star, User,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type PeriodOption = { id: number; name: string; status: string };
type Summary = { average: string | null; responses: number; available: boolean; minimum: number; periodStatus: string };
type CourseRow = { course: string; label: string; academicYear: string; responses: number; average: number | null };
type Comment = { course: string; comment: string };
type Review = { finalScore: string; teaching: string; attendance: string; workload: string; status: string; calculatedAt: string };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    lecturerInfo: { name?: string; nidn?: string; studyProgram?: string; faculty?: string } | [];
    periodOptions: PeriodOption[];
    selectedPeriodId: number | null;
    summary: Summary;
    courseRows: CourseRow[];
    comments: Comment[];
    review: Review | [];
    urls: { index: string };
};

const reviewTone = (status: string) => {
    const value = (status ?? '').toLowerCase();
    if (['approved', 'final', 'published'].includes(value)) return 'green';
    if (['draft', 'review', 'calculated'].includes(value)) return 'amber';
    if (['rejected'].includes(value)) return 'red';
    return 'gray';
};

export default function EdomIndex({ shell, hasProfile, lecturerInfo, periodOptions, selectedPeriodId, summary, courseRows, comments, review, urls }: Props) {
    const info = Array.isArray(lecturerInfo) ? {} : lecturerInfo;
    const reviewData = Array.isArray(review) ? null : review;

    const changePeriod = (value: string) => {
        router.get(urls.index, value ? { period: value } : {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`EDOM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Star}
                        eyebrow="Akademik · EDOM"
                        title="Hasil EDOM Saya"
                        description="Evaluasi dosen oleh mahasiswa. Hasil ditampilkan setelah periode ditutup dan ambang respons terpenuhi."
                        badges={[summary.available ? 'Hasil tersedia' : 'Menunggu ambang respons', `${summary.responses} respons`]}
                        actions={<Link className="db-btn light" href={shell.dashboardUrl}><GraduationCap size={15} /> Dasbor</Link>}
                    />

                    {!hasProfile && (
                        <div className="db-note warning"><User size={15} /><span>Profil dosen belum tersedia. Hubungi administrator untuk melengkapi data.</span></div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Periode EDOM</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <label className="crud-label">Pilih periode</label>
                                    <select className="db-input" value={selectedPeriodId ?? ''} onChange={(event) => changePeriod(event.target.value)} disabled={periodOptions.length === 0}>
                                        {periodOptions.length === 0 && <option value="">Belum ada periode</option>}
                                        {periodOptions.map((period) => (
                                            <option key={period.id} value={period.id}>{period.name} · {period.status}</option>
                                        ))}
                                    </select>
                                    <div className="crud-hint">Hanya periode yang memiliki respons Anda yang ditampilkan.</div>
                                </div>
                                <div style={{ display: 'grid', gap: 4, fontSize: 13, alignSelf: 'end' }}>
                                    <div><b>Nama:</b> {info.name ?? '-'}</div>
                                    <div><b>NIDN:</b> {info.nidn ?? '-'}</div>
                                    <div><b>Program studi:</b> {info.studyProgram ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="db-stats" aria-label="Ringkasan EDOM">
                        <div className="db-stat"><span className="db-stat-icon gold"><Star size={20} /></span><div><span className="db-stat-num">{summary.average ?? '-'}</span><span className="db-stat-label">Rata-rata EDOM</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><MessageSquareQuote size={20} /></span><div><span className="db-stat-num">{summary.responses}</span><span className="db-stat-label">Respons</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><ShieldCheck size={20} /></span><div><span className="db-stat-num">{summary.minimum}</span><span className="db-stat-label">Minimum respons</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><CalendarDays size={20} /></span><div><span className="db-stat-num">{summary.periodStatus}</span><span className="db-stat-label">Status periode</span></div></div>
                    </section>

                    {!summary.available && (
                        <div className="db-note muted">
                            <EyeOff size={15} />
                            <span>
                                Hasil detail disembunyikan sampai periode berstatus <b>closed</b> dan jumlah respons mencapai minimal {summary.minimum}.
                                Saat ini {summary.responses} respons terkumpul.
                            </span>
                        </div>
                    )}

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><BookOpen size={16} /> Rekap per mata kuliah</h2></div>
                            <div className="db-card-body">
                                {!summary.available ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Data per mata kuliah akan tampil setelah ambang respons terpenuhi.</p>
                                ) : courseRows.length === 0 ? (
                                    <EmptyState icon={BarChart3} title="Belum ada data" description="Tidak ada respons untuk periode ini." />
                                ) : (
                                    <div className="db-table-wrap">
                                        <table className="db-table">
                                            <thead><tr><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th><th>Respons</th><th>Rata-rata</th></tr></thead>
                                            <tbody>
                                                {courseRows.map((row) => (
                                                    <tr key={`${row.course}-${row.label}`}>
                                                        <td><b>{row.course}</b></td>
                                                        <td>{row.label}</td>
                                                        <td>{row.academicYear}</td>
                                                        <td>{row.responses}</td>
                                                        <td><span className="db-badge amber">{row.average ?? '-'}</span></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><ShieldCheck size={16} /> Review kinerja</h2></div>
                            <div className="db-card-body">
                                {!reviewData ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada review kinerja untuk periode ini.</p>
                                ) : (
                                    <div style={{ display: 'grid', gap: 8, fontSize: 13 }}>
                                        <div className="db-summary"><div><b>Skor akhir</b><small>Gabungan EDOM & kepatuhan</small></div><span className="db-badge gold">{reviewData.finalScore}</span></div>
                                        <div className="db-summary"><div><b>Kepatuhan pengajaran</b><small>Kelengkapan perangkat</small></div><span className="db-badge gray">{reviewData.teaching}</span></div>
                                        <div className="db-summary"><div><b>Kepatuhan kehadiran</b><small>Realisasi pertemuan</small></div><span className="db-badge gray">{reviewData.attendance}</span></div>
                                        <div className="db-summary"><div><b>Beban kerja</b><small>Total SKS</small></div><span className="db-badge gray">{reviewData.workload}</span></div>
                                        <div className="db-summary"><div><b>Status</b><small>Dihitung {reviewData.calculatedAt}</small></div><span className={`db-badge ${reviewTone(reviewData.status)}`}>{reviewData.status}</span></div>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><MessageSquareQuote size={16} /> Komentar mahasiswa</h2></div>
                        <div className="db-card-body">
                            {!summary.available ? (
                                <p className="db-hint" style={{ margin: 0 }}>Komentar akan ditampilkan setelah hasil tersedia.</p>
                            ) : comments.length === 0 ? (
                                <EmptyState icon={MessageSquareQuote} title="Belum ada komentar" description="Mahasiswa tidak meninggalkan komentar tekstual." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {comments.map((comment, index) => (
                                        <article className="db-class" key={`${comment.course}-${index}`}>
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}><b>{comment.course}</b><small>Komentar anonim</small></div>
                                                <MessageSquareQuote size={16} />
                                            </div>
                                            <p style={{ margin: '8px 0 0', whiteSpace: 'pre-wrap', lineHeight: 1.6 }}>{comment.comment}</p>
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
