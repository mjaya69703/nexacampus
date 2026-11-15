// Public career center page (job postings for alumni & students).
import { Head, Link } from '@inertiajs/react';
import { Briefcase, Building2, Clock3, Coins, FileText, Inbox, MapPin, Send, Users } from 'lucide-react';
import { AlumniShell, CommunityBaseProps } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type JobItem = { id: number; title: string; company: string; industry: string; location: string; jobType: string; salary: string | null; description: string; requirements: string; deadline: string | null; postedAt: string | null; applyUrl: string | null; email: string | null; logo: string | null; initials: string };
type Props = CommunityBaseProps & { totalJobs: number; jobs: JobItem[] };

type Tone = '' | 'green' | 'amber' | 'gray';

const nf = new Intl.NumberFormat('id-ID');
const avatarTones = [
    { background: 'var(--adm-brand-soft)', color: 'var(--adm-brand)' },
    { background: 'var(--adm-green-soft)', color: 'var(--adm-green)' },
    { background: 'var(--adm-gold-soft)', color: 'var(--adm-gold)' },
];

const typeMap: Record<string, { label: string; tone: Tone }> = {
    'full-time': { label: 'Full-time', tone: '' },
    'part-time': { label: 'Part-time', tone: 'amber' },
    'contract': { label: 'Kontrak', tone: 'gray' },
    'internship': { label: 'Magang', tone: 'green' },
    'freelance': { label: 'Freelance', tone: '' },
};
const typeBadge = (jobType: string): { label: string; tone: Tone } => typeMap[jobType.toLowerCase()] ?? { label: jobType, tone: '' };

export default function AlumniKarir({ campus, links, user, totalJobs, jobs }: Props) {
    const industries = new Set(jobs.map((job) => job.industry || '').filter((value) => value !== ''));
    const locations = new Set(jobs.map((job) => job.location || '').filter((value) => value !== ''));

    return <AlumniShell campus={campus} links={links} user={user} activeTab="Karir" eyebrow="Karir" title="Pusat karir & lowongan aktif." description="Lowongan kerja dan magang dari mitra industri kampus — lengkap dengan kisaran gaji serta batas waktu lamaran agar tidak ada kesempatan terlewat." icon={Briefcase}
        action={<div className="adm-hero-cta"><Link className="adm-btn light" href="/alumni"><Users size={15} /> Profil Alumni</Link></div>}>
        <Head title="Lowongan Karir · NexaCampus" />

        <section className="adm-hero">
            <div className="adm-hero-inner">
                <div style={{ maxWidth: 640 }}>
                    <span className="adm-crumb"><Briefcase size={13} /> Pusat Karir NexaCampus</span>
                    <h2 style={{ margin: '12px 0 0', color: '#ffffff', font: '700 25px/1.25 "Source Serif 4", Georgia, serif' }}>Peluang karir bagi lulusan &amp; mahasiswa.</h2>
                    <p style={{ margin: '9px 0 0', color: 'rgba(255,255,255,.78)', fontSize: 13 }}>Setiap lowongan diverifikasi tim karir kampus dan diurutkan berdasarkan tenggat lamaran terdekat.</p>
                </div>
                <div style={{ display: 'grid', gap: 4, justifyItems: 'start', padding: '16px 22px', border: '1px solid rgba(255,255,255,.25)', borderRadius: 15, background: 'rgba(255,255,255,.09)' }}>
                    <span style={{ color: 'rgba(255,255,255,.75)', fontSize: 11, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.07em' }}>Lowongan Aktif</span>
                    <strong style={{ color: '#ffffff', font: '700 34px/1.1 "Source Serif 4", Georgia, serif' }}>{nf.format(totalJobs)}</strong>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'rgba(255,255,255,.75)' }}><Building2 size={13} />Dari mitra industri &amp; alumni</span>
                </div>
            </div>
        </section>

        <StatStrip
            items={[
                { icon: Briefcase, value: nf.format(totalJobs), label: 'Posisi Aktif' },
                { icon: Building2, value: industries.size, label: 'Industri Mitra', tone: 'gold' },
                { icon: MapPin, value: locations.size, label: 'Lokasi Kerja', tone: 'green' },
            ]}
        />

        <section className="adm-card">
            <div className="adm-card-head"><h2 className="adm-card-title">Daftar Lowongan</h2><span className="adm-badge">{jobs.length} Ditayangkan</span></div>
            <div className="adm-card-body">
                {jobs.length === 0 ? <EmptyState
                    icon={Inbox}
                    iconSize={28}
                    title="Belum Ada Lowongan"
                    description="Lowongan pekerjaan dan magang akan segera diperbarui oleh mitra kami. Pantau halaman ini secara berkala."
                    action={<Link className="adm-btn soft" href="/alumni">Lihat Profil Alumni</Link>}
                /> : <div className="adm-grid adm-cols-2">
                    {jobs.map((job, index) => {
                        const type = typeBadge(job.jobType);
                        const tone = avatarTones[index % avatarTones.length];
                        return <article className="adm-doc" key={job.id}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                <span style={{ display: 'grid', placeItems: 'center', width: 46, height: 46, flex: '0 0 auto', borderRadius: 12, fontWeight: 800, background: tone.background, color: tone.color }}>{job.initials}</span>
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <b style={{ display: 'block', color: 'var(--adm-heading)' }}>{job.title}</b>
                                    <small className="adm-hint" style={{ display: 'block' }}>{job.company}</small>
                                </div>
                                <span className={type.tone ? `adm-badge ${type.tone}` : 'adm-badge'}>{type.label}</span>
                            </div>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                {job.location && <span className="adm-badge gray"><MapPin size={12} />{job.location}</span>}
                                {job.industry && <span className="adm-badge gray"><Building2 size={12} />{job.industry}</span>}
                                {job.salary && <span className="adm-badge green"><Coins size={12} />{job.salary}</span>}
                            </div>
                            {job.description && <p className="adm-hint" style={{ margin: 0 }}>{job.description}</p>}
                            {job.requirements && <small className="adm-hint" style={{ display: 'flex', alignItems: 'flex-start', gap: 8, padding: '10px 12px', borderRadius: 10, background: 'var(--adm-soft)' }}><FileText size={14} style={{ flex: '0 0 auto', marginTop: 2 }} /><span><b style={{ color: 'var(--adm-heading)' }}>Persyaratan: </b>{job.requirements}</span></small>}
                            <div style={{ marginTop: 'auto', paddingTop: 12, borderTop: '1px solid var(--adm-line)', display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                <div style={{ display: 'grid', gap: 4, justifyItems: 'start' }}>
                                    <span className="adm-badge amber"><Clock3 size={12} />{job.deadline ? `Tutup: ${job.deadline}` : 'Tanpa Batas Waktu'}</span>
                                    {job.postedAt && <small className="adm-hint">Diposting {job.postedAt}</small>}
                                </div>
                                {job.applyUrl ? <a className="adm-btn primary sm" href={job.applyUrl} target="_blank" rel="noreferrer"><Send size={13} /> Lamar Sekarang</a>
                                    : job.email ? <a className="adm-btn primary sm" href={`mailto:${job.email}`}><Send size={13} /> Kirim Email</a> : null}
                            </div>
                        </article>;
                    })}
                </div>}
            </div>
        </section>
    </AlumniShell>;
}
