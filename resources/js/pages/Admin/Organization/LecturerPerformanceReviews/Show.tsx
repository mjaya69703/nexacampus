// Detail hasil review performa dosen & publikasi hasil evaluasi.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Award,
    Briefcase,
    CheckCircle2,
    Clock3,
    GraduationCap,
    Info,
    MessageSquare,
    Star,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Review = {
    id: number;
    ownerName: string;
    ownerEmail: string | null;
    periodName: string;
    periodCode: string | null;
    workloadPeriodName: string;
    programName: string;
    status: string;
    statusLabel: string;
    edomScore: number | null;
    responseCount: number;
    teachingComplianceScore: number | null;
    attendanceComplianceScore: number | null;
    workloadTotalSks: number | null;
    finalScore: number | null;
    calculatedAt: string | null;
    publishedAt: string | null;
    canPublish: boolean;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    review: Review;
    urls: { index: string; publish: string };
};

const statusTone = (status: string) => {
    if (status === 'published') return 'green';
    if (status === 'calculated') return 'amber';
    return 'gray';
};

const formatScore = (value: number | null, digits = 2) => (value === null ? '-' : value.toFixed(digits));

export default function LecturerPerformanceReviewShow({ shell, can, review, urls }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const publish = () => {
        setProcessing(true);
        router.post(urls.publish, {}, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    const cards = [
        {
            label: 'Skor Evaluasi Dosen oleh Mahasiswa (EDOM)',
            value: formatScore(review.edomScore),
            icon: GraduationCap,
            desc: `Rata-rata skor kuesioner dari ${review.responseCount} mahasiswa.`,
        },
        {
            label: 'Kepatuhan Jadwal Mengajar',
            value: review.teachingComplianceScore === null ? '-' : `${review.teachingComplianceScore.toFixed(1)}%`,
            icon: GraduationCap,
            desc: 'Persentase kehadiran dan ketepatan waktu dalam realisasi perkuliahan.',
        },
        {
            label: 'Kedisiplinan Absensi Kepegawaian',
            value: review.attendanceComplianceScore === null ? '-' : `${review.attendanceComplianceScore.toFixed(1)}%`,
            icon: Clock3,
            desc: 'Tingkat kepatuhan jam kerja dan rekapitulasi kehadiran harian.',
        },
        {
            label: 'Realisasi Beban Kerja Dosen (BKD)',
            value: review.workloadTotalSks === null ? '-' : `${review.workloadTotalSks.toFixed(1)} SKS`,
            icon: Briefcase,
            desc: 'Total SKS kegiatan tri dharma yang telah disetujui.',
        },
        {
            label: 'Jumlah Respon Kuesioner',
            value: String(review.responseCount),
            icon: MessageSquare,
            desc: 'Total pengisian EDOM yang valid dari mahasiswa.',
        },
        {
            label: 'Skor Akhir Gabungan (Final Score)',
            value: formatScore(review.finalScore),
            icon: Award,
            desc: 'Kalkulasi akhir dari bobot EDOM, kepatuhan mengajar, absensi, dan BKD.',
        },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Review Performa ${review.ownerName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Star}
                        eyebrow="Kepegawaian · Review Performa"
                        title={`Detail Review: ${review.ownerName}`}
                        description={`Periode EDOM: ${review.periodName} · Program Studi: ${review.programName} · Periode BKD: ${review.workloadPeriodName}`}
                        badges={[review.statusLabel]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali ke daftar</a>
                                {can.update && review.canPublish && (
                                    <button className="db-btn primary" type="button" onClick={() => setConfirming(true)}>
                                        <CheckCircle2 size={15} /> Publikasikan Review
                                    </button>
                                )}
                            </>
                        )}
                    />

                    <OrgStats
                        label="Statistik review performa"
                        items={[
                            { label: 'Status Review', value: review.statusLabel, icon: Info },
                            { label: 'Nilai Akhir', value: formatScore(review.finalScore), icon: Star, tone: 'gold' },
                            {
                                label: 'Total SKS BKD',
                                value: review.workloadTotalSks === null ? '-' : `${review.workloadTotalSks.toFixed(1)} SKS`,
                                icon: Briefcase,
                            },
                        ]}
                    />

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: 14 }}>
                        {cards.map((card) => {
                            const Icon = card.icon;

                            return (
                                <section className="db-card" key={card.label}>
                                    <div className="db-card-body">
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginBottom: 12 }}>
                                            <span className="db-stat-icon"><Icon size={20} /></span>
                                            <b style={{ fontSize: 24, color: 'var(--db-heading)', lineHeight: 1 }}>{card.value}</b>
                                        </div>
                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, marginBottom: 5 }}>{card.label}</b>
                                        <span className="db-hint" style={{ marginTop: 0 }}>{card.desc}</span>
                                    </div>
                                </section>
                            );
                        })}
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Info size={16} /> Informasi Evaluasi</h2>
                            <span className={`db-badge ${statusTone(review.status)}`.trim()}>{review.statusLabel}</span>
                        </div>
                        <div className="db-card-body">
                            <div className="db-facts">
                                <div className="db-fact"><span>Dosen</span><b>{review.ownerName}</b></div>
                                <div className="db-fact"><span>Email</span><b>{review.ownerEmail ?? '-'}</b></div>
                                <div className="db-fact"><span>Periode EDOM</span><b>{review.periodName}</b></div>
                                <div className="db-fact"><span>Kode Periode</span><b>{review.periodCode ?? '-'}</b></div>
                                <div className="db-fact"><span>Program Studi</span><b>{review.programName}</b></div>
                                <div className="db-fact"><span>Dihitung Pada</span><b>{review.calculatedAt ?? '-'}</b></div>
                                <div className="db-fact"><span>Dipublikasikan Pada</span><b>{review.publishedAt ?? '-'}</b></div>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirming}
                    title="Publikasikan review performa?"
                    message="Hasil review performa ini akan dipublikasikan kepada dosen yang bersangkutan."
                    confirmLabel="Ya, publikasikan"
                    processing={processing}
                    onConfirm={publish}
                    onCancel={() => setConfirming(false)}
                />
            </div>
        </AdminShell>
    );
}
