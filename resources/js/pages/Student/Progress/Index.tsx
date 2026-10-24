// Progres akademik mahasiswa — kredit, IPK, risiko, dan rekomendasi (Inertia React).
import { Head } from '@inertiajs/react';
import {
    AlertTriangle, BookOpen, CalendarCheck2, CheckCircle2, GraduationCap,
    LineChart, MessageSquareText, ShieldAlert, TrendingUp, UserRound, Wallet,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Numeric = number | string | null;

type Progress = {
    student: {
        name: string; nim: string; study_program: string; faculty: string;
        semester: number | null; status: string; active_year: string;
    };
    advisor: { name: string | null; identity: string | null; academic_year: string; period: string };
    credits: {
        target: number; passed: number; remaining: number; progress: number;
        current: number; current_status: string;
    };
    gpa: { semester: Numeric; cumulative: Numeric; latest_semester: number | null };
    trend: Array<{
        semester: string; semester_gpa: Numeric; cumulative_gpa: Numeric;
        credits_passed: number; status: string;
    }>;
    attendance: { total: number; attended: number; excused: number; absent: number; rate: Numeric; level: string };
    financial: { outstanding: number; overdue_count: number; active_holds: number; level: string };
    failed_courses: Array<{ code: string; name: string; semester: number | null; credits: number; grade: string }>;
    risks: Array<{ key: string; label: string; level: string; reason: string }>;
    recommendations: string[];
    advisor_notes: Array<{
        topic: string; notes: string; recommendation: string | null;
        follow_up_at: string | null; status: string; lecturer: string;
    }>;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    progress: Progress | null;
    urls: Record<string, string>;
};

const toNumber = (value: Numeric): number | null => {
    if (value === null || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const formatScore = (value: Numeric): string => {
    const number = toNumber(value);
    return number === null ? '-' : number.toFixed(2);
};

const formatMoney = (value: number): string => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

const riskTone = (level: string): string => {
    if (level === 'success') return 'green';
    if (level === 'warning') return 'amber';
    if (level === 'danger') return 'red';
    return 'gray';
};

const riskLabel = (level: string): string => {
    if (level === 'success') return 'Aman';
    if (level === 'warning') return 'Pantau';
    if (level === 'danger') return 'Perlu Atensi';
    return 'Belum Cukup Data';
};

const numericLevel = (level: string): string => {
    if (level === 'success') return 'green';
    if (level === 'warning') return 'amber';
    if (level === 'danger') return 'red';
    return 'gray';
};

export default function ProgressIndex({ shell, hasProfile, progress, urls }: Props) {
    if (!hasProfile || !progress) {
        return (
            <AdminShell shell={shell}>
                <Head title={`Progres Akademik · ${shell.appName}`} />
                <div className="db-root">
                    <div className="db-stack">
                        <CrudHero
                            icon={TrendingUp}
                            eyebrow="Akademik"
                            title="Progres Akademik"
                            description="Ringkasan kredit, IPK, kehadiran, dan rekomendasi studi."
                        />
                        <div className="db-note danger">
                            <ShieldAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data.</span>
                        </div>
                    </div>
                </div>
            </AdminShell>
        );
    }

    const { student, advisor, credits, gpa, trend, attendance, financial, failed_courses: failedCourses, risks, recommendations, advisor_notes: advisorNotes } = progress;

    return (
        <AdminShell shell={shell}>
            <Head title={`Progres Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={TrendingUp}
                        eyebrow="Akademik"
                        title={student.name}
                        description={`${student.study_program} · ${student.faculty}`}
                        badges={[
                            `NIM ${student.nim}`,
                            `Semester ${student.semester ?? '-'}`,
                            student.status,
                            `Tahun ${student.active_year}`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.studyPlan}><BookOpen size={15} /> KRS</a>
                                <a className="db-btn light" href={urls.grades}><GraduationCap size={15} /> Nilai</a>
                                <a className="db-btn light" href={urls.transcript}><LineChart size={15} /> Transkrip</a>
                            </>
                        )}
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><GraduationCap size={20} /></span>
                            <div>
                                <span className="db-stat-num">{formatScore(gpa.cumulative)}</span>
                                <span className="db-stat-label">IPK kumulatif</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{credits.passed}/{credits.target}</span>
                                <span className="db-stat-label">SKS lulus</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarCheck2 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{attendance.rate === null ? '-' : `${attendance.rate}%`}</span>
                                <span className="db-stat-label">Kehadiran</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Wallet size={20} /></span>
                            <div>
                                <span className="db-stat-num">{formatMoney(financial.outstanding)}</span>
                                <span className="db-stat-label">Tagihan aktif</span>
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><BookOpen size={16} /> Progres Kredit</h2>
                                <span className={`db-badge ${numericLevel(credits.progress >= 100 ? 'success' : 'warning')}`}>{credits.progress}%</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                <div className="db-progress"><i style={{ width: `${credits.progress}%` }} /></div>
                                <div className="db-summary" style={{ padding: 0 }}>
                                    <div><b>{credits.passed}</b><small>SKS lulus</small></div>
                                    <div><b>{credits.remaining}</b><small>SKS tersisa</small></div>
                                    <div><b>{credits.current}</b><small>SKS semester ini</small></div>
                                </div>
                                <div className="db-hint">
                                    Target kelulusan {credits.target} SKS. Status KRS terakhir: <b>{credits.current_status}</b>.
                                </div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><LineChart size={16} /> Tren IPK</h2>
                                <span className="db-badge gray">{trend.length} semester</span>
                            </div>
                            <div className="db-card-body">
                                {trend.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <LineChart size={22} />
                                        <div><b>Belum ada hasil studi.</b><small>IP semester dan IPK muncul setelah nilai difinalisasi.</small></div>
                                    </div>
                                ) : (
                                    <div className="db-table-wrap">
                                        <table className="db-table">
                                            <thead>
                                                <tr><th>Semester</th><th>IP</th><th>IPK</th><th>SKS lulus</th><th>Status</th></tr>
                                            </thead>
                                            <tbody>
                                                {trend.map((row) => (
                                                    <tr key={row.semester}>
                                                        <td style={{ fontWeight: 600 }}>{row.semester}</td>
                                                        <td>{formatScore(row.semester_gpa)}</td>
                                                        <td>{formatScore(row.cumulative_gpa)}</td>
                                                        <td>{row.credits_passed}</td>
                                                        <td><span className="db-badge gray">{row.status}</span></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><AlertTriangle size={16} /> Peta Risiko</h2>
                            <span className="db-badge gray">4 indikator</span>
                        </div>
                        <div className="db-card-body">
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))', gap: 12 }}>
                                {risks.map((risk) => (
                                    <div key={risk.key} style={{ border: '1px solid var(--db-line)', borderRadius: 12, padding: 14, background: 'var(--db-soft)' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'center' }}>
                                            <b style={{ color: 'var(--db-heading)' }}>{risk.label}</b>
                                            <span className={`db-badge ${riskTone(risk.level)}`}>{riskLabel(risk.level)}</span>
                                        </div>
                                        <p className="db-hint" style={{ margin: '8px 0 0' }}>{risk.reason}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CheckCircle2 size={16} /> Rekomendasi</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                {recommendations.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <CheckCircle2 size={22} />
                                        <div><b>Tidak ada rekomendasi mendesak.</b><small>Pertahankan ritme studi saat ini.</small></div>
                                    </div>
                                ) : recommendations.map((item, index) => (
                                    <div key={index} style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                        <span className="db-badge amber">{index + 1}</span>
                                        <span className="db-hint" style={{ margin: 0 }}>{item}</span>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><UserRound size={16} /> Dosen Pembimbing</h2>
                                <span className="db-badge gray">{advisor.academic_year}</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 6 }}>
                                <b style={{ color: 'var(--db-heading)' }}>{advisor.name ?? 'Belum ada dosen PA'}</b>
                                <span className="db-hint">{advisor.identity ?? 'Hubungi admin akademik bila pembimbing belum tersedia.'}</span>
                                <span className="db-hint">{advisor.period}</span>

                                {advisorNotes.length > 0 && (
                                    <div style={{ display: 'grid', gap: 8, marginTop: 8 }}>
                                        {advisorNotes.map((note, index) => (
                                            <div key={`${note.topic}-${index}`} style={{ borderTop: '1px solid var(--db-line)', paddingTop: 8 }}>
                                                <div style={{ fontWeight: 600, color: 'var(--db-heading)' }}>{note.topic}</div>
                                                <div className="db-hint">{note.notes}</div>
                                                {note.recommendation && (
                                                    <div className="db-hint"><MessageSquareText size={12} /> {note.recommendation}</div>
                                                )}
                                                <div className="db-hint">
                                                    {note.lecturer}
                                                    {note.follow_up_at ? ` · tindak lanjut ${note.follow_up_at}` : ''}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Mata Kuliah Perlu Ulang</h2>
                            <span className="db-badge amber">{failedCourses.length} mata kuliah</span>
                        </div>
                        <div className="db-card-body">
                            {failedCourses.length === 0 ? (
                                <div className="db-empty-calm">
                                    <CheckCircle2 size={22} />
                                    <div><b>Tidak ada mata kuliah gagal.</b><small>Semua mata kuliah pada transkrip berstatus lulus.</small></div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Mata Kuliah</th><th>Semester</th><th>SKS</th><th>Nilai</th></tr>
                                        </thead>
                                        <tbody>
                                            {failedCourses.map((course) => (
                                                <tr key={`${course.code}-${course.semester}`}>
                                                    <td>
                                                        <div style={{ fontWeight: 600 }}>{course.name}</div>
                                                        <div className="db-hint">{course.code}</div>
                                                    </td>
                                                    <td>{course.semester ?? '-'}</td>
                                                    <td>{course.credits}</td>
                                                    <td><span className="db-badge red">{course.grade}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarCheck2 size={16} /> Rincian Kehadiran</h2>
                            <span className={`db-badge ${numericLevel(attendance.level)}`}>
                                {attendance.rate === null ? 'Belum ada data' : `${attendance.rate}%`}
                            </span>
                        </div>
                        <div className="db-card-body">
                            <div className="db-summary" style={{ margin: 0, padding: 0 }}>
                                <div><b>{attendance.total}</b><small>Total rekaman</small></div>
                                <div><b>{attendance.attended}</b><small>Hadir / terlambat</small></div>
                                <div><b>{attendance.excused}</b><small>Izin / sakit</small></div>
                                <div><b>{attendance.absent}</b><small>Alpa</small></div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
