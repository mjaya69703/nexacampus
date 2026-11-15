import { Head, Link } from '@inertiajs/react';
import { CalendarMinus, CheckCircle2, Clock3, Coins, Inbox, Plus, RotateCcw } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import { ServiceStats, StatusBadge } from '../ServiceUI';
import { formatRupiah } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    number: string;
    academicYear: string;
    semester: number;
    duration: number;
    reasonCategory: string;
    reasonLabel: string;
    reason: string;
    leaveFeeAmount: number | null;
    status: string;
    statusLabel: string;
    submittedAt: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    applications: Row[];
    summary: { total: number; in_progress: number; active: number; returned: number };
    urls: { index: string; create: string };
};

export default function LeavesIndex({ shell, hasProfile, applications, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Cuti Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarMinus}
                        eyebrow="Student Services"
                        title="Cuti Akademik"
                        description="Pengajuan cuti akademik dan riwayatnya."
                        badges={[`${summary.total} pengajuan`, `${summary.active} cuti aktif`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Ajukan Cuti
                            </Link>
                        }
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}

                    <ServiceStats
                        items={[
                            { label: 'Total Pengajuan', value: summary.total, icon: CalendarMinus },
                            { label: 'Sedang Diproses', value: summary.in_progress, icon: Clock3, tone: 'gold' },
                            { label: 'Cuti Aktif', value: summary.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Kembali Aktif', value: summary.returned, icon: RotateCcw },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarMinus size={16} /> Daftar Pengajuan</h2>
                            <span className="db-badge gray">{applications.length}</span>
                        </div>
                        <div className="db-card-body">
                            {applications.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengajuan cuti" description="Ajukan cuti baru untuk memulai." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {applications.map((application) => (
                                        <article key={application.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{application.number}</b>
                                                    <small>{application.reasonLabel}</small>
                                                </div>
                                                <StatusBadge status={application.status} label={application.statusLabel} />
                                            </div>
                                            <p className="db-class-reason">{application.reason}</p>
                                            <div className="db-class-meta">
                                                <span><CalendarMinus size={12} /> {application.academicYear}</span>
                                                <span>Semester {application.semester}</span>
                                                <span>Durasi {application.duration} semester</span>
                                                {application.leaveFeeAmount !== null && (
                                                    <span><Coins size={12} /> {formatRupiah(application.leaveFeeAmount)}</span>
                                                )}
                                                <span><Clock3 size={12} /> Diajukan {application.submittedAt ?? '-'}</span>
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={application.url} className="db-btn ghost sm">Lihat Detail</Link>
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
