// Public kemahasiswaan services (layanan mahasiswa) page.
import { Head } from '@inertiajs/react';
import { Brain, CheckCircle2, HeartPulse, IdCard, Inbox, LucideIcon, Star, Stethoscope, Trophy, Users, Wallet } from 'lucide-react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Service = { icon: LucideIcon; tone: string; title: string; tag: string; badge: string; badgeTone: string; desc: string };

const services: Array<Service> = [
    {
        icon: Stethoscope,
        tone: 'green',
        title: 'Klinik Kesehatan',
        tag: 'Layanan Medis',
        badge: 'Gratis',
        badgeTone: 'green',
        desc: 'Layanan medis dasar dan pertolongan pertama gratis bagi sivitas akademika.',
    },
    {
        icon: Brain,
        tone: 'gold',
        title: 'Konseling Psikologi',
        tag: 'Kesehatan Mental',
        badge: 'Profesional',
        badgeTone: 'amber',
        desc: 'Pendampingan profesional untuk kesehatan mental dan problem akademik.',
    },
    {
        icon: Inbox,
        tone: '',
        title: 'Student Service Center',
        tag: 'Administrasi Terpadu',
        badge: 'Satu Pintu',
        badgeTone: '',
        desc: 'Pusat pelayanan administrasi satu pintu untuk efisiensi birokrasi mahasiswa.',
    },
];

export default function Layanan({ campus, links, user }: CommunityBaseProps) {
    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Layanan" eyebrow="Layanan Mahasiswa" title="Layanan mahasiswa yang siap mendukung studimu." description="Fasilitas layanan kesehatan, konseling psikologi, dan layanan administrasi terpadu untuk kelancaran studi Anda." icon={HeartPulse}>
        <Head title={`Layanan Mahasiswa · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><HeartPulse size={14} /> Pusat Bantuan Terpadu</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>Kesehatan fisik, kesehatan mental, dan kebutuhan administrasi — tiga pilar layanan dalam satu atap kemahasiswaan.</p>
                    </div>
                    <div className="adm-hero-cta">
                        <a className="adm-btn outline" href="/beasiswa"><Wallet size={15} /> Program Beasiswa</a>
                        <a className="adm-btn light" href="/kemahasiswaan/organisasi"><Users size={15} /> Organisasi Mahasiswa</a>
                    </div>
                </div>
            </div>
        </div>

        <StatStrip
            items={[
                { icon: HeartPulse, value: 3, label: 'Layanan Utama' },
                { icon: Star, value: '100%', label: 'Terbuka untuk Mahasiswa', tone: 'gold' },
            ]}
        />

        <div className="adm-stack">
            {services.map((service) => {
                const Icon = service.icon;
                return <section className="adm-card" key={service.title}>
                    <div className="adm-card-head">
                        <div style={{ display: 'flex', alignItems: 'center', gap: 13 }}>
                            <span className={`adm-stat-icon ${service.tone}`} style={{ width: 42, height: 42, borderRadius: 12 }}><Icon size={20} /></span>
                            <div>
                                <h2 className="adm-card-title">{service.title}</h2>
                                <small className="adm-hint">{service.tag}</small>
                            </div>
                        </div>
                        <span className={`adm-badge ${service.badgeTone}`}><CheckCircle2 size={14} /> {service.badge}</span>
                    </div>
                    <div className="adm-card-body tight">
                        <p className="adm-hint" style={{ margin: 0 }}>{service.desc}</p>
                    </div>
                </section>;
            })}

            <div className="adm-note"><IdCard size={16} /><div><b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Satu pintu untuk urusan administrasi.</b>Seluruh kebutuhan administrasi mahasiswa dapat diproses melalui Student Service Center agar lebih cepat dan efisien.</div></div>
        </div>

        <a className="adm-btn ghost" href="/kemahasiswaan/prestasi" style={{ justifyContent: 'center' }}><Trophy size={15} /> Lihat Prestasi Mahasiswa</a>
    </KemahasiswaanShell>;
}
