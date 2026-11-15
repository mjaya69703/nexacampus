// Detail dosen — riwayat BKD dan tren performa (Inertia React).
import { Head } from '@inertiajs/react';
import {
    ArrowLeft, BarChart3, BookOpen, GraduationCap, LayoutDashboard,
    Scale, Star, TrendingUp, UserRound,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Workload = {
    id: number;
    status: string;
    statusLabel: string;
    periodName: string;
    teachingSks: number;
    structuralSks: number;
    tridharmaSks: number;
    totalSks: number;
    updatedAt: string | null;
};

type Review = {
    id: number;
    edomPeriod: string;
    finalScore: number | null;
    edomScore: number | null;
    responses: number;
    workloadSks: number | null;
    calculatedAt: string;
};

type Props = {
    shell: ShellProps;
    profile: {
        id: number; name: string; nidn: string; program: string; faculty: string; photo: string | null;
    };
    stats: {
        latestSks: number;
        approvedBkd: number;
        latestScore: number;
        avgScore: number;
        programBenchmark: number;
        facultyBenchmark: number;
    };
    workloads: Workload[];
    reviews: Review[];
    urls: { index: string; dashboard: string };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'in_approval' || status === 'revision') return 'amber';
    if (status === 'cancelled') return 'gray';
    return '';
};

const scoreTone = (score: number | null) => {
    if (score === null) return 'gray';
    if (score >= 80) return 'green';
    if (score >= 60) return 'amber';
    return 'red';
};

const formatScore = (score: number | null) => (score === null ? '-' : score.toFixed(2));

export default function LecturerShow({ shell, profile, stats, workloads, reviews, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`${profile.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UserRound}
                        eyebrow="Detail dosen"
                        title={profile.name}
                        description={`${profile.program} / ${profile.faculty}`}
                        badges={[
                            `NIDN ${profile.nidn}`,
                            `${stats.latestSks} SKS terakhir`,
                            `Skor terakhir ${stats.latestScore.toFixed(2)}`,
                            `Rata-rata ${stats.avgScore.toFixed(2)}`,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar dosen</a>
                                <a className="db-btn light" href={urls.dashboard}><LayoutDashboard size={15} /> Dashboard</a>
                            </>
                        )}
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.approvedBkd}</span>
                                <span className="db-stat-label">BKD approved</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Star size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.latestScore.toFixed(2)}</span>
                                <span className="db-stat-label">Skor terakhir</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.programBenchmark.toFixed(2)}</span>
                                <span className="db-stat-label">Benchmark prodi</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><TrendingUp size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.facultyBenchmark.toFixed(2)}</span>
                                <span className="db-stat-label">Benchmark fakultas</span>
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Scale size={16} /> Riwayat BKD</h2>
                                <span className="db-badge gray">{workloads.length} data</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                {workloads.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <Scale size={22} />
                                        <div><b>Belum ada BKD.</b><small>Dosen ini belum mengirim beban kerja.</small></div>
                                    </div>
                                ) : workloads.map((workload) => (
                                    <div
                                        key={workload.id}
                                        style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 10, padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-soft)' }}
                                    >
                                        <div style={{ minWidth: 0 }}>
                                            <span className={`db-badge ${statusTone(workload.status)}`}>{workload.statusLabel}</span>
                                            <div style={{ color: 'var(--db-heading)', fontWeight: 700, marginTop: 6 }}>{workload.periodName}</div>
                                            <div className="db-hint">
                                                Mengajar {workload.teachingSks} / Jabatan {workload.structuralSks} / Tridharma {workload.tridharmaSks}
                                            </div>
                                            {workload.updatedAt && <div className="db-hint">Diperbarui {workload.updatedAt}</div>}
                                        </div>
                                        <div style={{ textAlign: 'right' }}>
                                            <div style={{ color: 'var(--db-heading)', font: "700 22px/1.1 'Source Serif 4', Georgia, serif" }}>
                                                {workload.totalSks}
                                            </div>
                                            <span className="db-hint">SKS</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><BarChart3 size={16} /> Tren Performa</h2>
                                <span className="db-badge gray">{reviews.length} review</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                {reviews.length === 0 ? (
                                    <div className="db-empty-calm">
                                        <BarChart3 size={22} />
                                        <div><b>Belum ada review performa.</b><small>EDOM dan skor akhir belum dihitung.</small></div>
                                    </div>
                                ) : reviews.map((review) => (
                                    <div
                                        key={review.id}
                                        style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 10, padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-soft)' }}
                                    >
                                        <div style={{ minWidth: 0 }}>
                                            <span className={`db-badge ${scoreTone(review.finalScore)}`}>
                                                Akhir {formatScore(review.finalScore)}
                                            </span>
                                            <div style={{ color: 'var(--db-heading)', fontWeight: 700, marginTop: 6 }}>{review.edomPeriod}</div>
                                            <div className="db-hint">
                                                EDOM {formatScore(review.edomScore)} / Respon {review.responses} / BKD {review.workloadSks ?? '-'}
                                            </div>
                                        </div>
                                        <span className="db-hint">{review.calculatedAt}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
