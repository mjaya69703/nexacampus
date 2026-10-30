import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, CalendarDays, CheckCircle2, Clock3, GraduationCap, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import { ServiceStats, StatusBadge } from '../ServiceUI';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    number: string;
    status: string;
    statusLabel: string;
    graduationPeriod: string | null;
    graduationDate: string | null;
    thesisTitle: string | null;
    submittedAt: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    applications: Row[];
    summary: { total: number; in_progress: number; approved: number; finalized: number };
    urls: { index: string; create: string };
};

export default function GraduationsIndex({ shell, hasProfile, applications, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Student Services"
                        title="Yudisium"
                        description="Pengajuan yudisium, checklist, dan dokumen kelulusan."
                        badges={[`${summary.total} pengajuan`, `${summary.finalized} selesai`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Ajukan Yudisium
                            </Link>
                        }
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}

                    <ServiceStats
                        items={[
                            { label: 'Total Pengajuan', value: summary.total, icon: GraduationCap },
                            { label: 'Sedang Diproses', value: summary.in_progress, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: summary.approved, icon: BadgeCheck },
                            { label: 'Selesai', value: summary.finalized, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Daftar Pengajuan</h2>
                            <span className="db-badge gray">{applications.length}</span>
                        </div>
                        <div className="db-card-body">
                            {applications.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengajuan yudisium" description="Ajukan yudisium untuk memulai." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {applications.map((application) => (
                                        <article key={application.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{application.number}</b>
                                                    <small>{application.graduationPeriod ?? 'Periode belum ditentukan'}</small>
                                                </div>
                                                <StatusBadge status={application.status} label={application.statusLabel} />
                                            </div>
                                            {application.thesisTitle && <p className="db-class-reason">{application.thesisTitle}</p>}
                                            <div className="db-class-meta">
                                                <span><CalendarDays size={12} /> Lulus: {application.graduationDate ?? 'Belum final'}</span>
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
