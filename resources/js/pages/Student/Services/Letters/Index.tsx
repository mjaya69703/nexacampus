import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock3, Download, FileText, Inbox, Plus, XCircle } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import { ServiceStats, StatusBadge } from '../ServiceUI';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    number: string;
    type: string;
    purpose: string;
    status: string;
    statusLabel: string;
    submittedAt: string | null;
    downloadUrl: string | null;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    requests: Row[];
    summary: { total: number; in_progress: number; issued: number; rejected: number };
    urls: { index: string; create: string };
};

export default function LettersIndex({ shell, hasProfile, requests, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Surat Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Student Services"
                        title="Surat Mahasiswa"
                        description="Ajukan surat keterangan, transkrip, dan dokumen akademik lainnya."
                        badges={[`${summary.total} pengajuan`, `${summary.issued} diterbitkan`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Ajukan Surat
                            </Link>
                        }
                    />

                    {!hasProfile && <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>}

                    <ServiceStats
                        items={[
                            { label: 'Total Pengajuan', value: summary.total, icon: FileText },
                            { label: 'Sedang Diproses', value: summary.in_progress, icon: Clock3, tone: 'gold' },
                            { label: 'Diterbitkan', value: summary.issued, icon: CheckCircle2, tone: 'green' },
                            { label: 'Ditolak', value: summary.rejected, icon: XCircle, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Daftar Pengajuan</h2>
                            <span className="db-badge gray">{requests.length}</span>
                        </div>
                        <div className="db-card-body">
                            {requests.length === 0 ? (
                                <EmptyState icon={Inbox} title="Belum ada pengajuan surat" description="Ajukan surat baru untuk memulai." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {requests.map((request) => (
                                        <article key={request.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{request.number}</b>
                                                    <small>{request.type}</small>
                                                </div>
                                                <StatusBadge status={request.status} label={request.statusLabel} />
                                            </div>
                                            <p className="db-class-reason">{request.purpose}</p>
                                            <div className="db-class-meta">
                                                <span><Clock3 size={12} /> Diajukan {request.submittedAt ?? '-'}</span>
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={request.url} className="db-btn ghost sm">Lihat Detail</Link>
                                                {request.downloadUrl && (
                                                    <a href={request.downloadUrl} className="db-btn primary sm" target="_blank" rel="noreferrer">
                                                        <Download size={13} /> Unduh Surat
                                                    </a>
                                                )}
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
