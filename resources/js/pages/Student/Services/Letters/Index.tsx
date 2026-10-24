import { Head, Link } from '@inertiajs/react';
import { FileText, Inbox, Plus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    number: string;
    type: string;
    purpose: string;
    status: string;
    submittedAt: string | null;
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
                        badges={[`${summary.total} pengajuan`]}
                        actions={
                            <Link href={urls.create} className="db-btn primary">
                                <Plus size={14} /> Ajukan Surat
                            </Link>
                        }
                    />
                    {!hasProfile && (
                        <div className="db-note danger">Profil mahasiswa belum ditemukan.</div>
                    )}
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
                                    {requests.map((r) => (
                                        <article key={r.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{r.number}</b>
                                                    <small>{r.type} · {r.purpose}</small>
                                                </div>
                                                <span className="db-badge gray">{r.status}</span>
                                            </div>
                                            <div className="db-class-actions">
                                                <Link href={r.url} className="db-btn ghost sm">Detail</Link>
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
