import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock3, Headset, Inbox, MessageSquare, Plus, UserRound } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import { PriorityBadge, ServiceStats, StatusBadge } from '../ServiceUI';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    ticketNumber: string;
    subject: string;
    category: string;
    priority: string;
    priorityLabel: string;
    status: string;
    statusLabel: string;
    assignedUnit: string | null;
    submittedAt: string | null;
    dueAt: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    complaints: Row[];
    summary: { total: number; active: number; waiting: number; resolved: number };
    urls: { index: string; create: string };
};

export default function ComplaintsIndex({ shell, hasProfile, complaints, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Headset}
                        eyebrow="Student Services"
                        title="Pengaduan"
                        description="Sampaikan kendala layanan kampus dan pantau tindak lanjutnya."
                        badges={[`${summary.total} pengaduan`, `${summary.active} aktif`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Buat Pengaduan
                            </Link>
                        }
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}

                    <ServiceStats
                        items={[
                            { label: 'Total Pengaduan', value: summary.total, icon: Headset },
                            { label: 'Sedang Aktif', value: summary.active, icon: MessageSquare, tone: 'gold' },
                            { label: 'Menunggu Kamu', value: summary.waiting, icon: Clock3, tone: 'gold' },
                            { label: 'Selesai', value: summary.resolved, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Headset size={16} /> Daftar Pengaduan</h2>
                            <span className="db-badge gray">{complaints.length}</span>
                        </div>
                        <div className="db-card-body">
                            {complaints.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengaduan" description="Buat pengaduan baru bila menemui kendala." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {complaints.map((complaint) => (
                                        <article key={complaint.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{complaint.ticketNumber}</b>
                                                    <small>{complaint.subject}</small>
                                                </div>
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                    <PriorityBadge priority={complaint.priority} />
                                                    <StatusBadge status={complaint.status} label={complaint.statusLabel} />
                                                </div>
                                            </div>
                                            <div className="db-class-meta">
                                                <span>{complaint.category}</span>
                                                {complaint.assignedUnit && <span><UserRound size={12} /> {complaint.assignedUnit}</span>}
                                                <span><Clock3 size={12} /> Diajukan {complaint.submittedAt ?? '-'}</span>
                                                {complaint.dueAt && <span>Target {complaint.dueAt}</span>}
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={complaint.url} className="db-btn ghost sm">Lihat Detail</Link>
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
