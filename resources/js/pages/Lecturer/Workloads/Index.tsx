// BKD dosen — pilih periode, siapkan draf, dan pantau riwayat pengajuan.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Briefcase, CheckCircle2, ClipboardList, Clock, FileSignature, RefreshCw, Scale, TrendingUp,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Period = { id: number; name: string; status: string };
type Submission = {
    id: number; period: string; periodId: number | null; status: string; teachingSks: number;
    structuralSks: number; tridharmaSks: number; totalSks: number; updatedAt: string | null; url: string;
};
type Stats = { total: number; inApproval: number; approved: number; latestSks: number };

type Props = {
    shell: ShellProps;
    periods: Period[];
    submissions: Submission[];
    selectedPeriodId: string;
    stats: Stats;
    canGenerate: boolean;
    urls: { index: string; generate: string };
};

const statusLabel: Record<string, string> = {
    draft: 'Draf',
    in_approval: 'Menunggu approval',
    approved: 'Disetujui',
    revision: 'Perlu revisi',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (['in_approval', 'revision'].includes(status)) return 'amber';
    if (['rejected'].includes(status)) return 'red';
    return 'gray';
};

export default function WorkloadsIndex({ shell, periods, submissions, selectedPeriodId, stats, canGenerate, urls }: Props) {
    const [periodId, setPeriodId] = useState(selectedPeriodId);
    const form = useForm<{ periodId: string }>({ periodId: selectedPeriodId });

    const changePeriod = (value: string) => {
        setPeriodId(value);
        form.setData('periodId', value);
        router.get(urls.index, value ? { period: value } : {}, { preserveState: true, preserveScroll: true });
    };

    const generate = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.generate, { preserveScroll: true });
    };

    const cards = [
        { label: 'Total pengajuan', value: stats.total, icon: <ClipboardList size={20} />, tone: 'db-stat-icon' },
        { label: 'Menunggu approval', value: stats.inApproval, icon: <Clock size={20} />, tone: 'db-stat-icon gold' },
        { label: 'Disetujui', value: stats.approved, icon: <CheckCircle2 size={20} />, tone: 'db-stat-icon green' },
        { label: 'SKS terakhir', value: stats.latestSks, icon: <TrendingUp size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Beban Kerja Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Scale}
                        eyebrow="Kepegawaian Dosen"
                        title="BKD Saya"
                        description="Siapkan beban kerja dari kegiatan mengajar, jabatan struktural, dan tridharma yang sudah tercatat."
                        badges={[`${stats.total} pengajuan`, `${stats.inApproval} approval`, `${stats.approved} disetujui`]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileSignature size={16} /> Siapkan draf BKD</h2>
                        </div>
                        <div className="db-card-body">
                            {periods.length === 0 ? (
                                <div className="db-note muted">
                                    <Clock size={15} />
                                    <span>Tidak ada periode BKD yang sedang dibuka. Hubungi bagian kepegawaian.</span>
                                </div>
                            ) : (
                                <form onSubmit={generate} className="crud-grid">
                                    <div>
                                        <label className="crud-label">Periode BKD</label>
                                        <select className="db-input" value={periodId} onChange={(event) => changePeriod(event.target.value)}>
                                            {periods.map((period) => (
                                                <option key={period.id} value={period.id}>{period.name} · {period.status}</option>
                                            ))}
                                        </select>
                                        {form.errors.periodId && <div className="db-error">{form.errors.periodId}</div>}
                                        {(form.errors as Record<string, string>).period && <div className="db-error">{(form.errors as Record<string, string>).period}</div>}
                                        {(form.errors as Record<string, string>).lecturer && <div className="db-error">{(form.errors as Record<string, string>).lecturer}</div>}
                                        {(form.errors as Record<string, string>).submission && <div className="db-error">{(form.errors as Record<string, string>).submission}</div>}
                                        <div className="crud-hint">Draf dihitung otomatis dari kelas, jabatan, dan kegiatan tridharma yang tercatat.</div>
                                    </div>
                                    <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                        <button className="db-btn primary" type="submit" disabled={!canGenerate || form.processing}>
                                            <RefreshCw size={14} /> {form.processing ? 'Menyiapkan…' : 'Siapkan draf'}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </section>

                    <section className="db-stats" aria-label="Ringkasan BKD">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Briefcase size={16} /> Riwayat BKD ({submissions.length})</h2>
                            <span className="db-hint">Diurutkan dari pembaruan terakhir</span>
                        </div>
                        <div className="db-card-body">
                            {submissions.length === 0 ? (
                                <EmptyState icon={Briefcase} title="Belum ada pengajuan BKD" description="Pilih periode di atas lalu siapkan draf untuk memulai." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {submissions.map((submission) => (
                                        <article key={submission.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{submission.period}</b>
                                                    <small>Diperbarui {submission.updatedAt ?? '-'}</small>
                                                </div>
                                                <span className={`db-badge ${statusTone(submission.status)}`}>{statusLabel[submission.status] ?? submission.status}</span>
                                            </div>
                                            <div style={{ display: 'grid', gap: 6, marginTop: 8, fontSize: 13 }}>
                                                <div className="db-summary"><div><b>Pengajaran</b><small>SKS dari kelas</small></div><span className="db-badge gray">{submission.teachingSks}</span></div>
                                                <div className="db-summary"><div><b>Struktural</b><small>SKS dari jabatan</small></div><span className="db-badge gray">{submission.structuralSks}</span></div>
                                                <div className="db-summary"><div><b>Tridharma</b><small>Penelitian & pengabdian</small></div><span className="db-badge gray">{submission.tridharmaSks}</span></div>
                                            </div>
                                            <div className="db-class-actions">
                                                <span className="db-hint">Total <b>{submission.totalSks} SKS</b></span>
                                                <Link href={submission.url} className="db-btn ghost sm">Lihat detail</Link>
                                            </div>
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
