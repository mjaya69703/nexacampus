// Analytics hasil tracer study per kampanye.
import { Head } from '@inertiajs/react';
import { BarChart3, Briefcase, Download, GraduationCap, TrendingUp, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type DistributionItem = { label: string; count: number; percentage: number };
type SalaryItem = { label: string; count: number };
type EmploymentSummary = { responses: number; working: number; entrepreneur: number; studying: number; unemployed: number; other: number };

type Analytics = {
    response_rate: number;
    total_responses: number;
    employment_distribution: Record<string, DistributionItem>;
    job_relevance_breakdown: Record<string, DistributionItem>;
    avg_time_to_employment: number | null;
    by_study_program: Record<string, EmploymentSummary>;
    by_graduation_year: Record<string, EmploymentSummary>;
    further_study_count: number;
    salary_distribution: Record<string, SalaryItem>;
};

type Props = {
    shell: ShellProps;
    can: { viewCampaign: boolean; viewResponses: boolean };
    campaign: {
        id: number;
        title: string;
        academicYear: string | null;
        status: string;
        statusLabel: string;
        startDate: string | null;
        endDate: string | null;
        totalSent: number;
        totalResponded: number;
        responseRate: number;
        responsesCount: number;
    };
    analytics: Analytics;
    urls: { show: string; responses: string; exportCsv: string; exportXlsx: string; exportPdf: string };
};

function percentageWidth(value: number) {
    return `${Math.max(0, Math.min(value, 100))}%`;
}

function DistributionTable({ title, items }: { title: string; items: Record<string, DistributionItem> }) {
    const entries = Object.entries(items);

    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><BarChart3 size={16} /> {title}</h2>
            </div>
            <div className="db-card-body">
                {entries.length === 0 ? (
                    <p className="db-hint">Belum ada data.</p>
                ) : (
                    <div className="crud-table-wrap">
                        <table className="crud-table">
                            <thead><tr><th>Kategori</th><th style={{ textAlign: 'right' }}>Jumlah</th><th style={{ width: '45%' }}>Proporsi</th></tr></thead>
                            <tbody>
                                {entries.map(([key, item]) => (
                                    <tr key={key}>
                                        <td><b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{item.label}</b></td>
                                        <td style={{ textAlign: 'right' }}>{item.count}</td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                <div style={{ flex: 1, height: 8, borderRadius: 999, background: 'var(--db-soft)', overflow: 'hidden' }}>
                                                    <div style={{ height: '100%', width: percentageWidth(item.percentage), background: 'var(--db-brand)' }} />
                                                </div>
                                                <span className="db-badge gray">{item.percentage}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}

function SalaryTable({ items }: { items: Record<string, SalaryItem> }) {
    const entries = Object.entries(items);
    const total = entries.reduce((sum, [, item]) => sum + item.count, 0);

    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><Briefcase size={16} /> Distribusi Rentang Gaji</h2>
            </div>
            <div className="db-card-body">
                {entries.length === 0 ? (
                    <p className="db-hint">Belum ada data gaji yang diisi.</p>
                ) : (
                    <div className="crud-table-wrap">
                        <table className="crud-table">
                            <thead><tr><th>Rentang</th><th style={{ textAlign: 'right' }}>Jumlah</th><th style={{ textAlign: 'right' }}>Proporsi</th></tr></thead>
                            <tbody>
                                {entries.map(([key, item]) => (
                                    <tr key={key}>
                                        <td>{item.label}</td>
                                        <td style={{ textAlign: 'right' }}>{item.count}</td>
                                        <td style={{ textAlign: 'right' }}>{total > 0 ? Math.round((item.count / total) * 1000) / 10 : 0}%</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}

function GroupTable({ title, groups }: { title: string; groups: Record<string, EmploymentSummary> }) {
    const entries = Object.entries(groups);

    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><GraduationCap size={16} /> {title}</h2>
            </div>
            <div className="db-card-body">
                {entries.length === 0 ? (
                    <p className="db-hint">Belum ada data.</p>
                ) : (
                    <div className="crud-table-wrap">
                        <table className="crud-table">
                            <thead>
                                <tr>
                                    <th>Kelompok</th>
                                    <th style={{ textAlign: 'right' }}>Respon</th>
                                    <th style={{ textAlign: 'right' }}>Bekerja</th>
                                    <th style={{ textAlign: 'right' }}>Wirausaha</th>
                                    <th style={{ textAlign: 'right' }}>Studi</th>
                                    <th style={{ textAlign: 'right' }}>Belum kerja</th>
                                    <th style={{ textAlign: 'right' }}>Lainnya</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map(([name, group]) => (
                                    <tr key={name}>
                                        <td><b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{name}</b></td>
                                        <td style={{ textAlign: 'right' }}>{group.responses}</td>
                                        <td style={{ textAlign: 'right' }}>{group.working}</td>
                                        <td style={{ textAlign: 'right' }}>{group.entrepreneur}</td>
                                        <td style={{ textAlign: 'right' }}>{group.studying}</td>
                                        <td style={{ textAlign: 'right' }}>{group.unemployed}</td>
                                        <td style={{ textAlign: 'right' }}>{group.other}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function TracerStudyAnalytics({ shell, can, campaign, analytics, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Analytics Tracer Study · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={TrendingUp}
                        eyebrow="Alumni · Tracer Study"
                        title={`Analytics: ${campaign.title}`}
                        description={`Ringkasan hasil kuesioner ${campaign.academicYear ? `tahun akademik ${campaign.academicYear}` : 'lintas tahun'} yang diisi oleh alumni sasaran.`}
                        badges={[campaign.statusLabel, `${campaign.responsesCount} respon`, `${campaign.responseRate}% response rate`]}
                        actions={(
                            <>
                                {can.viewCampaign && <a className="db-btn light" href={urls.show}>Detail kampanye</a>}
                                {can.viewResponses && <a className="db-btn light" href={urls.responses}>Daftar respon</a>}
                                <a className="db-btn light" href={urls.exportXlsx}><Download size={14} /> XLSX</a>
                                <a className="db-btn light" href={urls.exportCsv}><Download size={14} /> CSV</a>
                                <a className="db-btn light" href={urls.exportPdf}><Download size={14} /> PDF</a>
                            </>
                        )}
                    />

                    <section className="db-stats" aria-label="Ringkasan analytics">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{analytics.total_responses}</span><span className="db-stat-label">Total respon</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><TrendingUp size={20} /></span>
                            <div><span className="db-stat-num">{campaign.responseRate}%</span><span className="db-stat-label">Response rate</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Briefcase size={20} /></span>
                            <div><span className="db-stat-num">{analytics.avg_time_to_employment ?? '-'}</span><span className="db-stat-label">Rata-rata bulan ke kerja</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{analytics.further_study_count}</span><span className="db-stat-label">Lanjut studi</span></div>
                        </div>
                    </section>

                    <div className="crud-grid">
                        <DistributionTable title="Status Pekerjaan" items={analytics.employment_distribution} />
                        <DistributionTable title="Relevansi Pekerjaan" items={analytics.job_relevance_breakdown} />
                    </div>

                    <SalaryTable items={analytics.salary_distribution} />
                    <GroupTable title="Rekap per Program Studi" groups={analytics.by_study_program} />
                    <GroupTable title="Rekap per Tahun Lulus" groups={analytics.by_graduation_year} />
                </div>
            </div>
        </AdminShell>
    );
}
