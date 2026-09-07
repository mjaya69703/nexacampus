// Public student organizations (organisasi kemahasiswaan) page.
import { Head } from '@inertiajs/react';
import { CheckCircle2, Gavel, GraduationCap, HeartPulse, Lightbulb, LucideIcon, Medal, Palette, ShieldCheck, Trophy, Users } from 'lucide-react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Organization = { icon: LucideIcon; tone: string; title: string; badge: string; badgeTone: string; desc: string };

const organizations: Array<Organization> = [
    {
        icon: Gavel,
        tone: 'red',
        title: 'BEM & DPM',
        badge: 'Institusional',
        badgeTone: 'red',
        desc: 'Badan Eksekutif dan Dewan Perwakilan Mahasiswa tingkat universitas dan fakultas.',
    },
    {
        icon: GraduationCap,
        tone: '',
        title: 'HIMA',
        badge: 'Keilmuan',
        badgeTone: '',
        desc: 'Himpunan Mahasiswa Program Studi yang berfokus pada pengembangan keilmuan spesifik.',
    },
    {
        icon: Medal,
        tone: 'green',
        title: 'UKM Olahraga',
        badge: 'Minat & Bakat',
        badgeTone: 'green',
        desc: 'Wadah penyaluran bakat olahraga mulai dari basket, futsal, hingga e-sports.',
    },
    {
        icon: Palette,
        tone: 'gold',
        title: 'UKM Seni',
        badge: 'Seni & Budaya',
        badgeTone: 'amber',
        desc: 'Kembangkan kreativitas melalui paduan suara, teater, tari tradisional, dan band.',
    },
    {
        icon: ShieldCheck,
        tone: '',
        title: 'UKM Kerohanian',
        badge: 'Pembinaan',
        badgeTone: 'gray',
        desc: 'Organisasi pembinaan mental dan spiritual untuk berbagai agama.',
    },
    {
        icon: Lightbulb,
        tone: 'red',
        title: 'UKM Penalaran',
        badge: 'Riset & Kompetisi',
        badgeTone: 'amber',
        desc: 'Fokus pada riset, jurnalistik, robotika, dan kewirausahaan mahasiswa.',
    },
];

export default function Organisasi({ campus, links, user }: CommunityBaseProps) {
    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Organisasi" eyebrow="Organisasi Mahasiswa" title="Tumbuh lewat organisasi intra kampus." description="Kembangkan jiwa kepemimpinan dan jejaring Anda melalui berbagai wadah organisasi yang aktif dan dinamis." icon={Users}>
        <Head title={`Organisasi Mahasiswa · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><Users size={14} /> 25+ Ormawa Aktif</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>Dari badan eksekutif hingga unit kegiatan seni, olahraga, dan penalaran — ada panggung untuk setiap minat dan potensi.</p>
                    </div>
                    <div className="adm-hero-cta">
                        <a className="adm-btn outline" href="/kemahasiswaan/prestasi"><Trophy size={15} /> Galeri Prestasi</a>
                        <a className="adm-btn light" href="/kemahasiswaan/layanan"><HeartPulse size={15} /> Layanan Mahasiswa</a>
                    </div>
                </div>
            </div>
        </div>

        <StatStrip
            items={[
                { icon: Users, value: '25+', label: 'Total Ormawa' },
                { icon: CheckCircle2, value: organizations.length, label: 'Kategori Wadah Kegiatan', tone: 'green' },
            ]}
        />

        <div className="adm-grid adm-cols-3">
            {organizations.map((organization) => {
                const Icon = organization.icon;
                return <article className="adm-card" key={organization.title}>
                    <div className="adm-card-body" style={{ display: 'grid', gap: 11, alignContent: 'start' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                            <span className={`adm-stat-icon ${organization.tone}`} style={{ width: 46, height: 46, borderRadius: 13 }}><Icon size={22} /></span>
                            <span className={`adm-badge ${organization.badgeTone}`}>{organization.badge}</span>
                        </div>
                        <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 15px/1.35 'Source Serif 4', Georgia, serif" }}>{organization.title}</h3>
                        <p className="adm-hint" style={{ margin: 0 }}>{organization.desc}</p>
                    </div>
                </article>;
            })}
        </div>

        <div className="adm-note green"><CheckCircle2 size={16} /><div><b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Aktif sepanjang tahun.</b>Seluruh ormawa aktif menyelenggarakan program kerja setiap tahun — pilih wadah yang paling sesuai dengan minat dan potensimu.</div></div>
    </KemahasiswaanShell>;
}
