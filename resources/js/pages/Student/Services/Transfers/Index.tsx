import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ArrowRightLeft, BadgeCheck, CheckCircle2, Clock3, Coins, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import { ServiceStats, StatusBadge } from '../ServiceUI';
import { classTypeLabel, formatRupiah } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    number: string;
    fromProgram: string;
    toProgram: string;
    type: string;
    typeLabel: string;
    fromClassType: string | null;
    toClassType: string | null;
    transferFeeAmount: number | null;
    status: string;
    statusLabel: string;
    submittedAt: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    requests: Row[];
    summary: { total: number; in_progress: number; approved: number; applied: number };
    urls: { index: string; create: string };
};

export default function TransfersIndex({ shell, hasProfile, requests, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Pindah Program · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ArrowRightLeft}
                        eyebrow="Student Services"
                        title="Pindah Program"
                        description="Pengajuan pindah program studi, fakultas, atau kelas."
                        badges={[`${summary.total} pengajuan`, `${summary.applied} diterapkan`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Ajukan Pindah
                            </Link>
                        }
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}

                    <ServiceStats
                        items={[
                            { label: 'Total Pengajuan', value: summary.total, icon: ArrowRightLeft },
                            { label: 'Sedang Diproses', value: summary.in_progress, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: summary.approved, icon: BadgeCheck },
                            { label: 'Sudah Diterapkan', value: summary.applied, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ArrowRightLeft size={16} /> Daftar Pengajuan</h2>
                            <span className="db-badge gray">{requests.length}</span>
                        </div>
                        <div className="db-card-body">
                            {requests.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengajuan pindah" description="Ajukan pindah program untuk memulai." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {requests.map((request) => (
                                        <article key={request.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{request.number}</b>
                                                    <small>{request.typeLabel}</small>
                                                </div>
                                                <StatusBadge status={request.status} label={request.statusLabel} />
                                            </div>
                                            <div className="db-class-meta" style={{ marginTop: 10 }}>
                                                <span style={{ color: 'var(--db-heading)', fontWeight: 700 }}>
                                                    {request.fromProgram} <ArrowRight size={12} /> {request.toProgram}
                                                </span>
                                            </div>
                                            <div className="db-class-meta">
                                                {request.fromClassType && request.toClassType && (
                                                    <span>Kelas: {classTypeLabel(request.fromClassType)} <ArrowRight size={12} /> {classTypeLabel(request.toClassType)}</span>
                                                )}
                                                {request.transferFeeAmount !== null && (
                                                    <span><Coins size={12} /> {formatRupiah(request.transferFeeAmount)}</span>
                                                )}
                                                <span><Clock3 size={12} /> Diajukan {request.submittedAt ?? '-'}</span>
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={request.url} className="db-btn ghost sm">Lihat Detail</Link>
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
