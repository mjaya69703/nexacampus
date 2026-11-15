// Pusat laporan & alert pimpinan akademik (Inertia React).
// Alert sudah diurutkan controller berdasarkan level keparahan.
import { Head } from '@inertiajs/react';
import {
    Bell, BookOpen, CheckCircle2, ClipboardList, FileSpreadsheet, FileText,
    LayoutDashboard, Star, TriangleAlert, UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Alert = {
    level: string;
    title: string;
    description: string;
    target: string;
};

type Report = {
    type: string;
    title: string;
    description: string;
    count: number;
};

type Props = {
    shell: ShellProps;
    hasScope: boolean;
    stats: {
        lecturers: number;
        classes: number;
        alerts: number;
        problemClasses: number;
        pendingWorkloads: number;
        avgPerformance: number;
    };
    alerts: Alert[];
    reports: Report[];
    urls: {
        index: string;
        dashboard: string;
        lecturers: string;
        classes: string;
        edom: string;
        export: Record<string, string>;
        reportExport: Record<string, Record<string, string>>;
    };
};

const reportIcons: Record<string, LucideIcon> = {
    lecturers: UsersRound,
    classes: BookOpen,
    alerts: Bell,
};

const alertTone = (level: string) => {
    if (level === 'danger') return 'red';
    if (level === 'warning') return 'amber';
    return '';
};

const alertLabel = (level: string) => {
    if (level === 'danger') return 'Kritis';
    if (level === 'warning') return 'Perhatian';
    return 'Info';
};

export default function ReportIndex({ shell, hasScope, stats, alerts, reports, urls }: Props) {
    const targetUrl = (target: string) => (target === 'lecturers' ? urls.lecturers : urls.classes);
    const targetLabel = (target: string) => (target === 'lecturers' ? 'Dosen' : 'Kelas');

    return (
        <AdminShell shell={shell}>
            <Head title={`Laporan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Pemantauan akademik"
                        title="Laporan"
                        description="Export laporan dosen, kelas, kehadiran, dan alert sesuai scope jabatan aktif."
                        badges={[
                            `${stats.lecturers} dosen`,
                            `${stats.classes} kelas`,
                            `${stats.alerts} alert`,
                            `${stats.problemClasses} kelas bermasalah`,
                        ]}
                        actions={(
                            <a className="db-btn light" href={urls.dashboard}>
                                <LayoutDashboard size={15} /> Dashboard
                            </a>
                        )}
                    />

                    {!hasScope && (
                        <div className="db-note warning">
                            <TriangleAlert size={15} />
                            <span>Scope belum tersedia. Akun ini belum memiliki scope fakultas atau program studi aktif.</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><UsersRound size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.lecturers}</span>
                                <span className="db-stat-label">Dosen</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><BookOpen size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.classes}</span>
                                <span className="db-stat-label">Kelas</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><TriangleAlert size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.alerts}</span>
                                <span className="db-stat-label">Alert</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Star size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.avgPerformance.toFixed(2)}</span>
                                <span className="db-stat-label">Rata-rata performa</span>
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        <a className="db-btn ghost sm" href={urls.lecturers}><UsersRound size={14} /> Dosen</a>
                        <a className="db-btn ghost sm" href={urls.classes}><BookOpen size={14} /> Kelas</a>
                        <a className="db-btn ghost sm" href={urls.edom}><ClipboardList size={14} /> EDOM</a>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 14 }}>
                        {reports.map((report) => {
                            const Icon = reportIcons[report.type] ?? FileText;
                            const exportUrls = urls.reportExport[report.type] ?? {};

                            return (
                                <section key={report.type} className="db-card">
                                    <div className="db-card-body" style={{ display: 'grid', gap: 12, alignContent: 'start', height: '100%' }}>
                                        <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
                                            <span className="db-stat-icon"><Icon size={20} /></span>
                                            <div style={{ minWidth: 0 }}>
                                                <div style={{ color: 'var(--db-heading)', fontWeight: 700 }}>{report.title}</div>
                                                <p className="db-hint" style={{ margin: '4px 0 0' }}>{report.description}</p>
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center', marginTop: 'auto' }}>
                                            <span className="db-badge gray">{report.count} baris</span>
                                            {(['csv', 'xlsx', 'pdf'] as const).map((format) => (
                                                <a
                                                    key={format}
                                                    className="db-btn ghost sm"
                                                    href={exportUrls[format]}
                                                    title={`Export ${format.toUpperCase()}`}
                                                >
                                                    {format === 'xlsx' ? <FileSpreadsheet size={13} /> : <FileText size={13} />}
                                                    {format === 'xlsx' ? 'Excel' : format.toUpperCase()}
                                                </a>
                                            ))}
                                        </div>
                                    </div>
                                </section>
                            );
                        })}
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Bell size={16} /> Alert Terbaru</h2>
                            <span className="db-badge amber">{alerts.length} ditampilkan</span>
                        </div>
                        <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                            {alerts.length === 0 ? (
                                <div className="db-empty-calm">
                                    <CheckCircle2 size={22} />
                                    <div>
                                        <b>Tidak ada alert aktif.</b>
                                        <small>Seluruh kelas dan BKD dalam scope terpantau aman.</small>
                                    </div>
                                </div>
                            ) : alerts.map((alert, index) => (
                                <div
                                    key={`${alert.level}-${alert.title}-${index}`}
                                    style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 12, padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-soft)' }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <span className={`db-badge ${alertTone(alert.level)}`}>{alertLabel(alert.level)}</span>
                                        <div style={{ color: 'var(--db-heading)', fontWeight: 700, marginTop: 5 }}>{alert.title}</div>
                                        <div className="db-hint">{alert.description}</div>
                                    </div>
                                    <a className="db-btn ghost sm" href={targetUrl(alert.target)}>
                                        {targetLabel(alert.target)}
                                    </a>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
