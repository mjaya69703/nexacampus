// Detail lowongan alumni — informasi lengkap + mitra perusahaan (Inertia React).
import { Head } from '@inertiajs/react';
import {
    ArrowLeft, Building2, CalendarClock, CalendarDays, ChevronRight,
    CircleAlert, Globe, MapPin, Send, Wallet,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Partner = {
    name: string;
    description: string | null;
    website: string | null;
    logoUrl: string | null;
};

type Job = {
    id: number;
    title: string;
    companyName: string;
    industry: string | null;
    description: string | null;
    requirements: string | null;
    location: string | null;
    jobType: string | null;
    jobTypeLabel: string | null;
    salaryRange: string | null;
    applyUrl: string | null;
    contactEmail: string | null;
    postedDate: string | null;
    deadlineDate: string | null;
    deadlineIn: string | null;
    isExpired: boolean;
    partner: Partner | null;
};

type Props = {
    shell: ShellProps;
    job: Job;
    urls: { index: string };
};

function DetailRow({ label, value }: { label: string; value: string | null }) {
    return (
        <tr>
            <td className="db-hint" style={{ width: '40%', padding: '9px 0', borderBottom: '1px solid var(--db-line)' }}>{label}</td>
            <td style={{ padding: '9px 0', borderBottom: '1px solid var(--db-line)', color: 'var(--db-heading)', fontWeight: 600 }}>
                {value || '-'}
            </td>
        </tr>
    );
}

export default function JobShow({ shell, job, urls }: Props) {
    const applyHref = job.applyUrl || (job.contactEmail ? `mailto:${job.contactEmail}` : null);

    return (
        <AdminShell shell={shell}>
            <Head title={`${job.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Job board"
                        title={job.title}
                        description={`${job.companyName}${job.location ? ` · ${job.location}` : ''}`}
                        badges={[
                            job.jobTypeLabel ?? 'Lowongan',
                            job.isExpired ? 'Ditutup' : 'Dibuka',
                        ]}
                        actions={(
                            <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Job Board</a>
                        )}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Building2 size={16} /> Deskripsi Pekerjaan</h2>
                                    {job.isExpired && <span className="db-badge gray">Lowongan ditutup</span>}
                                </div>
                                <div className="db-card-body">
                                    {job.description ? (
                                        <div style={{ whiteSpace: 'pre-wrap', lineHeight: 1.8, color: 'var(--db-ink)' }}>
                                            {job.description}
                                        </div>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada deskripsi untuk lowongan ini.</p>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ChevronRight size={16} /> Kualifikasi &amp; Persyaratan</h2>
                                </div>
                                <div className="db-card-body">
                                    {job.requirements ? (
                                        <div style={{ whiteSpace: 'pre-wrap', lineHeight: 1.8, color: 'var(--db-ink)' }}>
                                            {job.requirements}
                                        </div>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada persyaratan khusus.</p>
                                    )}
                                </div>
                            </section>

                            {job.partner && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Building2 size={16} /> Tentang Mitra</h2>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'flex', gap: 14, alignItems: 'flex-start' }}>
                                        {job.partner.logoUrl ? (
                                            <img
                                                src={job.partner.logoUrl}
                                                alt={job.partner.name}
                                                style={{ width: 56, height: 56, borderRadius: 12, objectFit: 'contain', border: '1px solid var(--db-line)', background: '#fff' }}
                                            />
                                        ) : (
                                            <span className="db-stat-icon"><Building2 size={20} /></span>
                                        )}
                                        <div style={{ minWidth: 0 }}>
                                            <strong style={{ color: 'var(--db-heading)', display: 'block' }}>{job.partner.name}</strong>
                                            {job.partner.description && (
                                                <p className="db-hint" style={{ margin: '4px 0 8px' }}>{job.partner.description}</p>
                                            )}
                                            {job.partner.website && (
                                                <a className="db-btn ghost sm" href={job.partner.website} target="_blank" rel="noreferrer">
                                                    <Globe size={13} /> Kunjungi website
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                </section>
                            )}
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CalendarDays size={16} /> Detail Lowongan</h2>
                                </div>
                                <div className="db-card-body">
                                    <table className="db-table" style={{ width: '100%' }}>
                                        <tbody>
                                            <DetailRow label="Tipe" value={job.jobTypeLabel} />
                                            <DetailRow label="Industri" value={job.industry} />
                                            <DetailRow label="Lokasi" value={job.location ?? 'Remote'} />
                                            <DetailRow label="Gaji" value={job.salaryRange} />
                                            <DetailRow label="Diposting" value={job.postedDate} />
                                            <DetailRow label="Batas lamar" value={job.deadlineDate} />
                                        </tbody>
                                    </table>
                                    {job.deadlineIn && (
                                        <div className={`db-note ${job.isExpired ? 'warning' : 'info'}`} style={{ marginTop: 12 }}>
                                            <CalendarClock size={15} />
                                            <span>
                                                {job.isExpired
                                                    ? 'Lowongan ini sudah melewati batas waktu lamaran.'
                                                    : `Batas lamaran ${job.deadlineIn}.`}
                                            </span>
                                        </div>
                                    )}
                                    {!job.isExpired && applyHref ? (
                                        <a
                                            className="db-btn primary"
                                            href={applyHref}
                                            target={job.applyUrl ? '_blank' : undefined}
                                            rel={job.applyUrl ? 'noreferrer' : undefined}
                                            style={{ width: '100%', marginTop: 14 }}
                                        >
                                            <Send size={15} /> Lamar Sekarang
                                        </a>
                                    ) : (
                                        <button className="db-btn ghost" type="button" disabled style={{ width: '100%', marginTop: 14 }}>
                                            <CircleAlert size={15} /> Lamaran ditutup
                                        </button>
                                    )}
                                    {job.contactEmail && !job.isExpired && (
                                        <p className="db-hint" style={{ margin: '8px 0 0', textAlign: 'center' }}>
                                            Kontak: {job.contactEmail}
                                        </p>
                                    )}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
