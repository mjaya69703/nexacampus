// Transkrip akademik — hasil studi per semester dan seluruh entri nilai (Inertia React).
import { Head } from '@inertiajs/react';
import {
    BookOpen, FileText, GraduationCap, LineChart, Printer, ShieldAlert, Star,
} from 'lucide-react';
import { useMemo } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Numeric = number | string | null;

type Result = {
    id: number;
    academicYear: string;
    semesterNo: number | null;
    totalCourses: number;
    creditsTaken: number;
    creditsPassed: number;
    semesterGpa: Numeric;
    cumulativeGpa: Numeric;
    status: string;
};

type Entry = {
    id: number;
    courseCode: string;
    courseName: string;
    academicYear: string;
    semesterNo: number | null;
    credits: number;
    finalScore: Numeric;
    letterGrade: string | null;
    gradePoint: Numeric;
    resultStatus: string | null;
    countedInGpa: boolean;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: {
        name: string; nim: string; program: string;
        academicStatus: string; semester: number | null;
    } | null;
    results: Result[];
    entries: Entry[];
    summary: { totalCourses: number; totalCredits: number; passedCredits: number; cumulativeGpa: Numeric };
    urls: { index: string; grades: string; progress: string };
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

const statusLabel = (status: string | null): string => {
    const map: Record<string, string> = {
        Passed: 'Lulus', Failed: 'Tidak Lulus', Incomplete: 'Belum Lengkap',
        Withdrawn: 'Mengundurkan Diri', Cancelled: 'Dibatalkan',
        Published: 'Terpublikasi', Finalized: 'Difinalisasi',
    };
    return status ? (map[status] ?? status) : '-';
};

const statusTone = (status: string | null): string => {
    if (status === 'Passed' || status === 'Published' || status === 'Aktif') return 'green';
    if (status === 'Finalized') return '';
    if (status === 'Incomplete') return 'amber';
    if (status === 'Failed' || status === 'Cancelled' || status === 'Withdrawn') return 'red';
    return 'gray';
};

const gradeTone = (point: Numeric): string => {
    const number = toNumber(point);
    if (number === null) return 'gray';
    if (number >= 3) return 'green';
    if (number >= 2) return 'amber';
    return 'red';
};

export default function TranscriptIndex({ shell, hasProfile, student, results, entries, summary, urls }: Props) {
    const grouped = useMemo(() => {
        const buckets = new Map<number | null, Entry[]>();

        entries.forEach((entry) => {
            const key = entry.semesterNo;
            buckets.set(key, [...(buckets.get(key) ?? []), entry]);
        });

        return Array.from(buckets.entries())
            .sort(([a], [b]) => (a ?? 0) - (b ?? 0))
            .map(([semester, items]) => ({ semester, items }));
    }, [entries]);

    return (
        <AdminShell shell={shell}>
            <Head title={`Transkrip Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Akademik"
                        title={student?.name ?? 'Transkrip Akademik'}
                        description={student ? `${student.program} · NIM ${student.nim}` : 'Transkrip nilai lengkap mahasiswa.'}
                        badges={student ? [
                            `Semester ${student.semester ?? '-'}`,
                            student.academicStatus,
                            `${summary.totalCourses} mata kuliah`,
                            `${summary.passedCredits} SKS lulus`,
                        ] : []}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.grades}><GraduationCap size={15} /> Nilai</a>
                                <a className="db-btn light" href={urls.progress}><LineChart size={15} /> Progres</a>
                                <button type="button" className="db-btn light" onClick={() => window.print()}>
                                    <Printer size={15} /> Cetak
                                </button>
                            </>
                        )}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <ShieldAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data.</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Star size={20} /></span>
                            <div>
                                <span className="db-stat-num">{formatScore(summary.cumulativeGpa)}</span>
                                <span className="db-stat-label">IPK kumulatif</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{summary.totalCredits}</span>
                                <span className="db-stat-label">Total SKS</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div>
                                <span className="db-stat-num">{summary.passedCredits}</span>
                                <span className="db-stat-label">SKS lulus</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><FileText size={20} /></span>
                            <div>
                                <span className="db-stat-num">{summary.totalCourses}</span>
                                <span className="db-stat-label">Mata kuliah</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><LineChart size={16} /> Rekap Hasil Studi</h2>
                            <span className="db-badge gray">{results.length} semester</span>
                        </div>
                        <div className="db-card-body">
                            {results.length === 0 ? (
                                <div className="db-empty-calm">
                                    <LineChart size={22} />
                                    <div><b>Belum ada hasil studi.</b><small>Rekap IP semester muncul setelah nilai difinalisasi.</small></div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Semester</th>
                                                <th>Tahun Akademik</th>
                                                <th>Mata Kuliah</th>
                                                <th>SKS Diambil</th>
                                                <th>SKS Lulus</th>
                                                <th>IP</th>
                                                <th>IPK</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {results.map((result) => (
                                                <tr key={result.id}>
                                                    <td style={{ fontWeight: 600 }}>Smt {result.semesterNo ?? '-'}</td>
                                                    <td className="db-hint">{result.academicYear}</td>
                                                    <td>{result.totalCourses}</td>
                                                    <td>{result.creditsTaken}</td>
                                                    <td>{result.creditsPassed}</td>
                                                    <td>{formatScore(result.semesterGpa)}</td>
                                                    <td>{formatScore(result.cumulativeGpa)}</td>
                                                    <td><span className={`db-badge ${statusTone(result.status)}`}>{statusLabel(result.status)}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    {grouped.length === 0 ? (
                        <section className="db-card">
                            <div className="db-card-body">
                                <div className="db-empty-calm">
                                    <FileText size={22} />
                                    <div><b>Belum ada entri transkrip.</b><small>Mata kuliah yang telah dinilai akan tampil di sini.</small></div>
                                </div>
                            </div>
                        </section>
                    ) : grouped.map(({ semester, items }) => (
                        <section className="db-card" key={semester ?? 'unknown'}>
                            <div className="db-card-head">
                                <h2 className="db-card-title"><BookOpen size={16} /> Semester {semester ?? '-'}</h2>
                                <span className="db-badge gray">
                                    {items.length} mata kuliah · {items.reduce((total, item) => total + item.credits, 0)} SKS
                                </span>
                            </div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Mata Kuliah</th>
                                                <th>SKS</th>
                                                <th>Nilai Angka</th>
                                                <th>Huruf</th>
                                                <th>Mutu</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map((entry) => (
                                                <tr key={entry.id}>
                                                    <td>
                                                        <div style={{ fontWeight: 600, color: 'var(--db-heading)' }}>{entry.courseName}</div>
                                                        <div className="db-hint">
                                                            {entry.courseCode} · {entry.academicYear}
                                                            {!entry.countedInGpa && ' · tidak dihitung IPK'}
                                                        </div>
                                                    </td>
                                                    <td>{entry.credits}</td>
                                                    <td>{formatScore(entry.finalScore)}</td>
                                                    <td><span className={`db-badge ${gradeTone(entry.gradePoint)}`}>{entry.letterGrade ?? '-'}</span></td>
                                                    <td>{formatScore(entry.gradePoint)}</td>
                                                    <td><span className={`db-badge ${statusTone(entry.resultStatus)}`}>{statusLabel(entry.resultStatus)}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    ))}
                </div>
            </div>
        </AdminShell>
    );
}
