// Nilai mahasiswa — daftar nilai terpublikasi beserta komponen penilaian (Inertia React).
import { Head } from '@inertiajs/react';
import {
    ChevronDown, ChevronUp, FileCheck2, GraduationCap, MessageSquareWarning,
    Scale, ShieldAlert, Star,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Component = { name: string; weight: number | null; score: number | null; notes: string | null };

type Row = {
    id: number;
    courseCode: string;
    courseName: string;
    classLabel: string;
    academicYear: string;
    semesterNo: number | null;
    credits: number;
    finalScore: number | null;
    letterGrade: string | null;
    gradePoint: number | null;
    resultStatus: string | null;
    appealStatus: string | null;
    hasActiveAppeal: boolean;
    components: Component[];
    totalWeight: number;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    rows: Row[];
    stats: { courses: number; credits: number; avgPoint: number | string | null; activeAppeals: number };
    urls: { index: string; appeals: string; transcript: string; progress: string };
};

const statusLabel = (status: string | null): string => {
    const map: Record<string, string> = {
        Passed: 'Lulus', Failed: 'Tidak Lulus', Incomplete: 'Belum Lengkap',
        Withdrawn: 'Mengundurkan Diri', Cancelled: 'Dibatalkan',
    };
    return status ? (map[status] ?? status) : '-';
};

const statusTone = (status: string | null): string => {
    if (status === 'Passed') return 'green';
    if (status === 'Incomplete') return 'amber';
    if (status === 'Failed' || status === 'Cancelled' || status === 'Withdrawn') return 'red';
    return 'gray';
};

const appealLabel = (status: string | null): string => {
    const map: Record<string, string> = {
        submitted: 'Keberatan diajukan', under_review: 'Sedang direview',
        approved: 'Koreksi disetujui', rejected: 'Keberatan ditolak', closed: 'Ditutup',
    };
    return status ? (map[status] ?? status) : 'Belum ada keberatan';
};

const appealTone = (status: string | null): string => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'submitted' || status === 'under_review') return 'amber';
    return 'gray';
};

const scoreTone = (point: number | null): string => {
    if (point === null) return 'gray';
    if (point >= 3) return 'green';
    if (point >= 2) return 'amber';
    return 'red';
};

const formatNumber = (value: number | null, digits = 2): string => (value === null ? '-' : value.toFixed(digits));

export default function GradesIndex({ shell, hasProfile, rows, stats, urls }: Props) {
    const [expanded, setExpanded] = useState<number[]>([]);
    const avgPoint = stats.avgPoint === null ? null : Number(stats.avgPoint);

    const toggle = (id: number) => {
        setExpanded((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Nilai Saya · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title="Nilai Saya"
                        description="Nilai terpublikasi beserta komponen penilaian dan status banding nilai."
                        badges={[
                            `${stats.courses} mata kuliah`,
                            `${stats.credits} SKS`,
                            `Rata-rata mutu ${formatNumber(avgPoint)}`,
                            `${stats.activeAppeals} banding aktif`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.transcript}><FileCheck2 size={15} /> Transkrip</a>
                                <a className="db-btn light" href={urls.appeals}><Scale size={15} /> Banding Nilai</a>
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
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.courses}</span>
                                <span className="db-stat-label">Mata kuliah</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Star size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.credits}</span>
                                <span className="db-stat-label">SKS dinilai</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><FileCheck2 size={20} /></span>
                            <div>
                                <span className="db-stat-num">{formatNumber(avgPoint)}</span>
                                <span className="db-stat-label">Rata-rata mutu</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><MessageSquareWarning size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.activeAppeals}</span>
                                <span className="db-stat-label">Banding aktif</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Daftar Nilai</h2>
                            <span className="db-badge gray">{rows.length} baris</span>
                        </div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <div className="db-empty-calm">
                                    <GraduationCap size={22} />
                                    <div><b>Belum ada nilai terpublikasi.</b><small>Nilai muncul setelah dosen mempublikasikan hasil penilaian.</small></div>
                                </div>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Mata Kuliah</th>
                                                <th>Kelas</th>
                                                <th>SKS</th>
                                                <th>Nilai</th>
                                                <th>Status</th>
                                                <th>Banding</th>
                                                <th />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row) => {
                                                const isOpen = expanded.includes(row.id);

                                                return (
                                                    <Fragment key={row.id}>
                                                        <tr>
                                                            <td>
                                                                <div style={{ fontWeight: 600, color: 'var(--db-heading)' }}>{row.courseName}</div>
                                                                <div className="db-hint">{row.courseCode} · {row.academicYear} · Smt {row.semesterNo ?? '-'}</div>
                                                            </td>
                                                            <td className="db-hint">{row.classLabel}</td>
                                                            <td>{row.credits}</td>
                                                            <td>
                                                                <span className={`db-badge ${scoreTone(row.gradePoint)}`}>
                                                                    {row.letterGrade ?? '-'} / {formatNumber(row.gradePoint)}
                                                                </span>
                                                                <div className="db-hint">Angka {formatNumber(row.finalScore)}</div>
                                                            </td>
                                                            <td><span className={`db-badge ${statusTone(row.resultStatus)}`}>{statusLabel(row.resultStatus)}</span></td>
                                                            <td><span className={`db-badge ${appealTone(row.appealStatus)}`}>{appealLabel(row.appealStatus)}</span></td>
                                                            <td style={{ textAlign: 'right' }}>
                                                                {row.components.length > 0 && (
                                                                    <button
                                                                        type="button"
                                                                        className="db-btn ghost sm"
                                                                        onClick={() => toggle(row.id)}
                                                                        aria-label={isOpen ? 'Sembunyikan komponen' : 'Tampilkan komponen'}
                                                                    >
                                                                        {isOpen ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                                                                    </button>
                                                                )}
                                                            </td>
                                                        </tr>
                                                        {isOpen && (
                                                            <tr key={`${row.id}-components`}>
                                                                <td colSpan={7} style={{ background: 'var(--db-soft)' }}>
                                                                    <div className="db-table-wrap">
                                                                        <table className="db-table">
                                                                            <thead>
                                                                                <tr><th>Komponen</th><th>Bobot</th><th>Nilai</th><th>Catatan</th></tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                {row.components.map((component, index) => (
                                                                                    <tr key={`${component.name}-${index}`}>
                                                                                        <td>{component.name}</td>
                                                                                        <td>{component.weight === null ? '-' : `${component.weight}%`}</td>
                                                                                        <td>{formatNumber(component.score)}</td>
                                                                                        <td className="db-hint">{component.notes ?? '-'}</td>
                                                                                    </tr>
                                                                                ))}
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div className="db-hint" style={{ marginTop: 8 }}>
                                                                        Total bobot komponen: <b>{row.totalWeight}%</b>
                                                                        {row.hasActiveAppeal && ' · Banding nilai sedang diproses.'}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </Fragment>
                                                );
                                            })}
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
