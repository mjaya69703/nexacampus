// Public institusi vision & mission (visi misi) page.
import { Head } from '@inertiajs/react';
import { CalendarCheck, ClipboardList, Compass, LucideIcon, Quote, Star, Target } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';

type Mission = { title: string; desc: string };

const missions: Array<Mission> = [
    {
        title: 'Pendidikan Berkualitas',
        desc: 'Menyelenggarakan pendidikan berkualitas yang berstandar internasional untuk menghasilkan lulusan yang kompeten, berkarakter, dan berjiwa wirausaha.',
    },
    {
        title: 'Riset Inovatif',
        desc: 'Melaksanakan penelitian dasar dan terapan yang inovatif untuk memecahkan permasalahan bangsa dan berkontribusi pada perkembangan ilmu pengetahuan.',
    },
    {
        title: 'Pengabdian Masyarakat',
        desc: 'Mendedikasikan hasil pendidikan dan penelitian melalui kegiatan pengabdian yang memberdayakan masyarakat.',
    },
    {
        title: 'Tata Kelola yang Baik',
        desc: 'Membangun tata kelola universitas yang mandiri, transparan, dan akuntabel (Good University Governance).',
    },
];

type StatItem = { icon: LucideIcon; tone: string; value: string; label: string };

const stats: Array<StatItem> = [
    { icon: Star, tone: 'gold', value: '1', label: 'Visi Besar' },
    { icon: ClipboardList, tone: '', value: '4', label: 'Misi Strategis' },
    { icon: CalendarCheck, tone: 'green', value: '2030', label: 'Target Pencapaian' },
];

export default function VisiMisi({ campus, links, user }: InstitutionBaseProps) {
    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Visi & Misi" eyebrow="Visi & Misi" title="Visi besar, misi yang terukur." description="Arah strategis dan komitmen kami dalam membangun pendidikan masa depan yang berdaya saing global dan berlandaskan nilai luhur." icon={Target}>
        <Head title={`Visi & Misi · ${campus.name}`} />

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'absolute', bottom:'-35%', left:'-5%', width:320, height:320, borderRadius:'50%', background:'radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, textAlign:'center', padding:'0 20px' }}>
                <span style={{
                    display:'inline-flex', alignItems:'center', gap:8, marginBottom:14,
                    padding:'5px 14px', borderRadius:999, border:'1px solid rgba(255,255,255,.25)',
                    background:'rgba(255,255,255,.09)', color:'#dce8f5', fontSize:11, fontWeight:700,
                    letterSpacing:'.04em', textTransform:'uppercase',
                }}>
                    <Quote size={12} /> Visi Kami
                </span>
                <p style={{ margin:'0 auto', maxWidth:760, color:'#fff', font:"600 clamp(18px, 2.4vw, 24px)/1.55 'Source Serif 4', Georgia, serif", fontStyle:'italic' }}>
                    &ldquo;Menjadi perguruan tinggi unggul berkelas dunia yang inovatif, adaptif, dan berwawasan lingkungan dalam pengembangan IPTEK untuk kesejahteraan masyarakat pada tahun 2030.&rdquo;
                </p>
                <span style={{ display:'block', marginTop:16, color:'rgba(255,255,255,.72)', fontSize:11.5, fontWeight:700, textTransform:'uppercase', letterSpacing:'.09em' }}>Bergema hingga 2030</span>
            </div>
        </section>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 24 }}>
            {stats.map((stat) => {
                const Icon = stat.icon;
                return <div className="adm-stat" key={stat.label}>
                    <span className={`adm-stat-icon ${stat.tone}`}><Icon size={18} /></span>
                    <div>
                        <strong className="adm-stat-num">{stat.value}</strong>
                        <span className="adm-stat-label">{stat.label}</span>
                    </div>
                </div>;
            })}
        </div>

        {/* ── Misi Card ────────────────────────────────────────────── */}
        <section className="adm-card" style={{ marginBottom:28 }}>
            <div className="adm-card-head">
                <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                    <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Compass size={14} /></span>
                    <h2 className="adm-card-title">Misi Kami</h2>
                </div>
                <span className="adm-badge">4 Pilar Strategi</span>
            </div>
            <div className="adm-card-body">
                <div className="adm-steps">
                    {missions.map((mission, index) => (
                        <div className="adm-step" key={mission.title}>
                            <span className="adm-step-num">{index + 1}</span>
                            <div>
                                <h3>{mission.title}</h3>
                                <p>{mission.desc}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>

        {/* ── CTA Gradient ──────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'32px 38px',
        }}>
            <div style={{ position:'absolute', top:'-40%', left:'50%', transform:'translateX(-50%)', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'relative', zIndex:2, display:'flex', alignItems:'center', justifyContent:'space-between', gap:20, flexWrap:'wrap' }}>
                <div style={{ maxWidth:580 }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:8, marginBottom:8, color:'rgba(255,255,255,.85)', fontSize:11, fontWeight:800, letterSpacing:'.1em', textTransform:'uppercase' }}>
                        <Target size={12} /> Satu Arah Untuk Seluruh Sivitas
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Visi dan misi ini menjadi rujukan utama dalam perencanaan program kerja, kurikulum, serta pengambilan keputusan strategis di seluruh unit {campus.name}.
                    </p>
                </div>
            </div>
        </section>
    </InstitutionShell>;
}
