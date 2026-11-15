// Public institusi facilities (fasilitas kampus) page.
import { Head } from '@inertiajs/react';
import { BookOpen, Building2, CheckCircle2, Dumbbell, FlaskConical, Library, LucideIcon } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';

type Facility = { icon: LucideIcon; tone: string; title: string; badge: string; badgeTone: string; desc: string; highlights: Array<string> };

const facilities: Array<Facility> = [
    {
        icon: Library,
        tone: '',
        title: 'Perpustakaan Pusat',
        badge: 'Sumber Belajar',
        badgeTone: '',
        desc: 'Koleksi literatur lengkap, ruang baca nyaman, dan akses jurnal internasional terkemuka.',
        highlights: ['Literatur Lengkap', 'Ruang Baca', 'Jurnal Internasional'],
    },
    {
        icon: FlaskConical,
        tone: 'green',
        title: 'Laboratorium Modern',
        badge: 'Riset',
        badgeTone: 'green',
        desc: 'Dilengkapi dengan perangkat mutakhir untuk riset sains, teknologi, dan komputasi.',
        highlights: ['Perangkat Mutakhir', 'Sains & Teknologi', 'Komputasi'],
    },
    {
        icon: Dumbbell,
        tone: 'gold',
        title: 'Pusat Olahraga',
        badge: 'Bakat & Kebugaran',
        badgeTone: 'amber',
        desc: 'Stadion mini, lapangan basket indoor, lapangan tenis, dan fasilitas kebugaran.',
        highlights: ['Stadion Mini', 'Basket Indoor', 'Tenis', 'Kebugaran'],
    },
    {
        icon: Building2,
        tone: 'red',
        title: 'Student Center',
        badge: 'Kehidupan Mahasiswa',
        badgeTone: 'red',
        desc: 'Pusat kegiatan UKM, ruang diskusi terbuka, dan food court mahasiswa.',
        highlights: ['Pusat UKM', 'Ruang Diskusi', 'Food Court'],
    },
];

type StatItem = { icon: LucideIcon; tone: string; value: string; label: string };

const stats: Array<StatItem> = [
    { icon: BookOpen, tone: '', value: '4', label: 'Fasilitas Utama' },
    { icon: CheckCircle2, tone: 'green', value: '100%', label: 'Terbuka untuk Mahasiswa' },
];

export default function Fasilitas({ campus, links, user }: InstitutionBaseProps) {
    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Fasilitas" eyebrow="Fasilitas Kampus" title="Infrastruktur belajar berstandar internasional." description="Jelajahi berbagai fasilitas modern dan berstandar internasional yang disiapkan untuk mendukung pengalaman belajar dan riset Anda." icon={Library}>
        <Head title={`Fasilitas Kampus · ${campus.name}`} />

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'absolute', bottom:'-35%', left:'-5%', width:320, height:320, borderRadius:'50%', background:'radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, display:'flex', flexWrap:'wrap', alignItems:'flex-end', justifyContent:'space-between', gap:20 }}>
                <div style={{ maxWidth:620 }}>
                    <span style={{
                        display:'inline-flex', alignItems:'center', gap:8, marginBottom:14,
                        padding:'5px 14px', borderRadius:999, border:'1px solid rgba(255,255,255,.25)',
                        background:'rgba(255,255,255,.09)', color:'#dce8f5', fontSize:11, fontWeight:700,
                        letterSpacing:'.04em', textTransform:'uppercase',
                    }}>
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#4ade80', animation:'pulse-green 2s infinite' }} />
                        Infrastruktur Terbaik
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Fasilitas Kampus Modern
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Empat pilar fasilitas kampus yang menunjang perkuliahan, riset, olahraga, hingga kehidupan sosial mahasiswa.
                    </p>
                </div>
                <div style={{
                    padding:'14px 22px', border:'1px solid rgba(255,255,255,.22)', borderRadius:14,
                    background:'rgba(255,255,255,.08)', textAlign:'center', backdropFilter:'blur(8px)',
                }}>
                    <b style={{ display:'block', color:'#fff', fontSize:22, lineHeight:1 }}>1</b>
                    <small style={{ color:'rgba(255,255,255,.7)', fontSize:10, fontWeight:700, letterSpacing:'.06em', textTransform:'uppercase' }}>Kampus Terpadu</small>
                </div>
            </div>
        </section>

        <style>{`@keyframes pulse-green { 0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} 100%{box-shadow:0 0 0 0 rgba(74,222,128,0)} }`}</style>

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

        {/* ── Facility Grid ─────────────────────────────────────────── */}
        <div className="adm-grid adm-cols-2" style={{ marginBottom:28 }}>
            {facilities.map((facility) => {
                const Icon = facility.icon;
                return <article className="adm-card" key={facility.title}>
                    <div className="adm-card-head">
                        <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                            <span className={`adm-stat-icon ${facility.tone}`} style={{ width:42, height:42, borderRadius:12 }}><Icon size={20} /></span>
                            <h2 className="adm-card-title">{facility.title}</h2>
                        </div>
                        <span className={`adm-badge ${facility.badgeTone}`}>{facility.badge}</span>
                    </div>
                    <div className="adm-card-body tight" style={{ display:'grid', gap:12 }}>
                        <p className="adm-hint" style={{ margin:0 }}>{facility.desc}</p>
                        <div style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
                            {facility.highlights.map((highlight) => (
                                <span className="adm-badge gray" key={highlight}><CheckCircle2 size={13} /> {highlight}</span>
                            ))}
                        </div>
                    </div>
                </article>;
            })}
        </div>

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
                        <CheckCircle2 size={12} /> Didukung Penuh Selama Studimu
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Seluruh fasilitas di atas dapat dimanfaatkan oleh mahasiswa aktif {campus.name} sebagai bagian dari pengalaman belajar yang utuh.
                    </p>
                </div>
            </div>
        </section>
    </InstitutionShell>;
}
