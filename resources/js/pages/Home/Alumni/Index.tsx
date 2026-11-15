// Public alumni network page.
import { Head, Link } from '@inertiajs/react';
import { BadgeCheck, Briefcase, Clock3, Coins, GraduationCap, Inbox, MapPin, Send, ShieldCheck, Users } from 'lucide-react';
import { AlumniShell, CommunityBaseProps } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type AlumniStats = { total: number; employed: number; programs: number; jobs: number };
type AlumniItem = { name: string; nim: string; program: string; faculty: string; graduationYear: number | null; gpa: number | null; employer: string | null; jobTitle: string | null; city: string | null; employmentStatus: string; linkedin: string | null; initials: string };
type JobPreview = { id: number; title: string; company: string; location: string; jobType: string; industry: string; salary: string | null; deadline: string | null; applyUrl: string | null };
type Props = CommunityBaseProps & { stats: AlumniStats; alumni: AlumniItem[]; jobs: JobPreview[] };

type Tone = '' | 'green' | 'amber' | 'gray';

const nf = new Intl.NumberFormat('id-ID');
const avatarTones = [
    { background: 'var(--adm-brand-soft)', color: 'var(--adm-brand)' },
    { background: 'var(--adm-green-soft)', color: 'var(--adm-green)' },
    { background: 'var(--adm-gold-soft)', color: 'var(--adm-gold)' },
];

const empMap: Record<string, { label: string; tone: Tone }> = {
    employed: { label: 'Bekerja', tone: 'green' },
    self_employed: { label: 'Wirausaha', tone: 'amber' },
    unemployed: { label: 'Mencari Kerja', tone: 'gray' },
    studying: { label: 'Studi Lanjut', tone: '' },
};
const employmentBadge = (status: string): { label: string; tone: Tone } => empMap[status] ?? { label: status, tone: '' };

export default function AlumniIndex({ campus, links, user, stats, alumni, jobs }: Props) {
    const featured = jobs.slice(0, 3);

    return <AlumniShell campus={campus} links={links} user={user} activeTab="Profil Alumni" eyebrow="Profil Alumni" title="Jaringan alumni yang berdampak." description="Menghubungkan kisah sukses lulusan dengan peluang karir eksklusif dari mitra industri — semuanya dalam satu jaringan." icon={Users}
        action={<div className="adm-hero-cta"><Link className="adm-btn light" href="/alumni/karir"><Briefcase size={15} /> Lowongan Karir</Link></div>}>
        <Head title="Profil Alumni · NexaCampus" />

        <section className="adm-hero">
            <div className="adm-hero-inner">
                <div style={{ maxWidth: 640 }}>
                    <span className="adm-crumb"><Users size={13} /> Jaringan Alumni NexaCampus</span>
                    <h2 style={{ margin: '12px 0 0', color: '#ffffff', font: '700 25px/1.25 "Source Serif 4", Georgia, serif' }}>Alumni yang mengubah dunia, dimulai dari kampus.</h2>
                    <p style={{ margin: '9px 0 0', color: 'rgba(255,255,255,.78)', fontSize: 13 }}>Ribuan lulusan kini berkarir di berbagai industri. Jelajahi profil mereka dan temukan peluang eksklusif untuk keluarga besar NexaCampus.</p>
                </div>
                <div className="adm-hero-actions" style={{ flexDirection: 'column', alignItems: 'stretch' }}>
                    <span className="adm-badge solid" style={{ justifySelf: 'start' }}><BadgeCheck size={12} /> Komunitas Aktif</span>
                    <a className="adm-btn outline" href="/admission/apply"><Send size={14} /> Daftar Sekarang</a>
                </div>
            </div>
        </section>

        <StatStrip
            items={[
                { icon: Users, value: nf.format(stats.total), label: 'Total Alumni' },
                { icon: BadgeCheck, value: nf.format(stats.employed), label: 'Bekerja / Wirausaha', tone: 'green' },
                { icon: GraduationCap, value: nf.format(stats.programs), label: 'Program Studi', tone: 'gold' },
                { icon: Briefcase, value: nf.format(stats.jobs), label: 'Lowongan Aktif', tone: 'red' },
            ]}
        />

        <div className="adm-layout">
            <div className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head"><h2 className="adm-card-title">Profil Alumni Terbaru</h2><span className="adm-badge">{alumni.length} Profil</span></div>
                    <div className="adm-card-body">
                        {alumni.length === 0 ? <EmptyState
                            icon={Users}
                            iconSize={28}
                            title="Belum Ada Profil Alumni"
                            description="Profil lulusan akan tampil di sini setelah diaktifkan oleh bagian kemahasiswaan kampus."
                        /> : <div className="adm-grid adm-cols-3">
                            {alumni.map((item, index) => {
                                const status = employmentBadge(item.employmentStatus);
                                const tone = avatarTones[index % avatarTones.length];
                                const workplace = [item.employer, item.city].filter(Boolean).join(' — ');
                                return <article className="adm-doc" key={`${item.nim}-${index}`}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                                        <span style={{ display: 'grid', placeItems: 'center', width: 42, height: 42, flex: '0 0 auto', borderRadius: '50%', fontWeight: 800, background: tone.background, color: tone.color }}>{item.initials}</span>
                                        <div style={{ minWidth: 0 }}>
                                            <b style={{ display: 'block', color: 'var(--adm-heading)' }}>{item.name}</b>
                                            <small className="adm-hint" style={{ display: 'block' }}>{item.program} · {item.faculty}</small>
                                        </div>
                                    </div>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                        <span className={status.tone ? `adm-badge ${status.tone}` : 'adm-badge'}>{status.label}</span>
                                        {item.graduationYear !== null && <span className="adm-badge gray">{item.graduationYear}</span>}
                                        {item.gpa !== null && <span className="adm-badge">IPK {item.gpa.toFixed(2)}</span>}
                                    </div>
                                    <div style={{ marginTop: 'auto', paddingTop: 11, borderTop: '1px dashed var(--adm-line)' }}>
                                        {item.jobTitle && <b style={{ display: 'block', color: 'var(--adm-heading)' }}>{item.jobTitle}</b>}
                                        {workplace && <small className="adm-hint" style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 2 }}><MapPin size={13} />{workplace}</small>}
                                        <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: 8, marginTop: 7 }}>
                                            <small className="adm-hint">NIM {item.nim}</small>
                                            {item.linkedin && <a className="adm-btn ghost sm" href={item.linkedin} target="_blank" rel="noreferrer">LinkedIn</a>}
                                        </div>
                                    </div>
                                </article>;
                            })}
                        </div>}
                    </div>
                </section>
            </div>

            <aside className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head"><h2 className="adm-card-title">Lowongan Terbaru</h2><Link className="adm-btn ghost sm" href="/alumni/karir">Lihat Semua</Link></div>
                    <div className="adm-card-body tight">
                        {featured.length === 0 ? <EmptyState
                            icon={Inbox}
                            iconSize={24}
                            title="Belum Ada Lowongan"
                            description="Lowongan dari mitra industri akan segera hadir."
                            style={{ minHeight: 0, padding: '22px 18px' }}
                        /> : <div style={{ display: 'grid', gap: 14 }}>
                            {featured.map((job) => <div key={job.id} style={{ display: 'grid', gap: 7 }}>
                                <b style={{ color: 'var(--adm-heading)' }}>{job.title}</b>
                                <small className="adm-hint" style={{ display: 'block' }}>{[job.industry, job.company, job.location].filter(Boolean).join(' · ')}</small>
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                                    <span className="adm-badge">{job.jobType}</span>
                                    {job.salary && <span className="adm-badge green"><Coins size={12} />{job.salary}</span>}
                                    {job.deadline && <span className="adm-badge amber"><Clock3 size={12} />Tutup {job.deadline}</span>}
                                </div>
                            </div>)}
                        </div>}
                    </div>
                </section>

                <div className="adm-note"><ShieldCheck size={16} /><div><b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)', fontSize: 12.5 }}>Data Resmi Kampus</b>Profil alumni bersumber dari basis data kemahasiswaan dan diperbarui berkala. Hubungi layanan alumni untuk memperbarui data Anda.</div></div>
            </aside>
        </div>
    </AlumniShell>;
}
