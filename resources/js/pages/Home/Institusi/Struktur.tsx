// Public institusi leadership structure (struktur pimpinan) page — placeholder.
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Building2, Handshake, Hourglass, Library, LucideIcon, Network, ShieldCheck, Target } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';

type NavCard = { icon: LucideIcon; label: string; desc: string; href: string };

const navCards: Array<NavCard> = [
    { icon: Building2, label: 'Profil Kampus', desc: 'Identitas, lokasi, dan kontak resmi.', href: '/institusi/profil' },
    { icon: Target, label: 'Visi & Misi', desc: 'Arah strategis dan komitmen institusi.', href: '/institusi/visi-misi' },
    { icon: Library, label: 'Fasilitas', desc: 'Infrastruktur belajar berstandar internasional.', href: '/institusi/fasilitas' },
    { icon: ShieldCheck, label: 'Akreditasi', desc: 'Predikat UNGGUL BAN-PT hingga 2030.', href: '/institusi/akreditasi' },
    { icon: Handshake, label: 'Kerjasama', desc: 'Jejaring mitra industri dan riset.', href: '/institusi/kerjasama' },
];

export default function Struktur({ campus, links, user }: InstitutionBaseProps) {
    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Struktur" eyebrow="Struktur Pimpinan" title="Struktur Pimpinan Universitas." description={`Berkenalan dengan jajaran pimpinan yang mendedikasikan diri untuk kemajuan dan pengembangan institusi pendidikan ${campus.name}.`} icon={Network}>
        <Head title={`Struktur Pimpinan · ${campus.name}`} />

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'absolute', bottom:'-35%', left:'-5%', width:320, height:320, borderRadius:'50%', background:'radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, display:'flex', flexWrap:'wrap', alignItems:'flex-end', justifyContent:'space-between', gap:20 }}>
                <div style={{ maxWidth:520 }}>
                    <span style={{
                        display:'inline-flex', alignItems:'center', gap:8, marginBottom:14,
                        padding:'5px 14px', borderRadius:999, border:'1px solid rgba(255,255,255,.25)',
                        background:'rgba(255,255,255,.09)', color:'#dce8f5', fontSize:11, fontWeight:700,
                        letterSpacing:'.04em', textTransform:'uppercase',
                    }}>
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#fbbf24', animation:'pulse-amber 2s infinite' }} />
                        Sedang Dibangun
                    </span>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Bagan organisasi sedang disusun dan diverifikasi oleh bagian humas sebelum dipublikasikan.
                    </p>
                </div>
                <div style={{ minWidth:200 }}>
                    <span style={{ color:'rgba(255,255,255,.78)', fontSize:11.5, fontWeight:700, textTransform:'uppercase', letterSpacing:'.08em', display:'block', marginBottom:8 }}>Progres penyusunan</span>
                    <div style={{ background:'rgba(255,255,255,.18)', borderRadius:8, height:10 }}>
                        <div style={{ width:'60%', height:'100%', borderRadius:8, background:'#fff', transition:'width .6s ease' }} />
                    </div>
                    <span style={{ display:'block', marginTop:6, color:'rgba(255,255,255,.82)', fontSize:12, fontWeight:700 }}>60%</span>
                </div>
            </div>
        </section>

        <style>{`@keyframes pulse-amber { 0%{box-shadow:0 0 0 0 rgba(251,191,36,.7)} 70%{box-shadow:0 0 0 8px rgba(251,191,36,0)} 100%{box-shadow:0 0 0 0 rgba(251,191,36,0)} }`}</style>

        {/* ── Empty State ──────────────────────────────────────────── */}
        <div className="adm-card" style={{ marginBottom:28 }}>
            <div style={{ padding:'48px 32px', textAlign:'center' }}>
                <span style={{ display:'inline-grid', placeItems:'center', width:64, height:64, borderRadius:16, color:'var(--adm-brand)', background:'var(--adm-brand-soft)', marginBottom:16 }}>
                    <Network size={28} />
                </span>
                <h3 style={{ margin:'0 0 8px', fontSize:18, fontWeight:700, color:'var(--adm-heading)' }}>Halaman Sedang Dalam Pengembangan</h3>
                <p style={{ margin:0, maxWidth:480, marginLeft:'auto', marginRight:'auto', color:'var(--adm-muted)', fontSize:13.5, lineHeight:1.65 }}>
                    Bagan dan profil pimpinan institusi akan segera ditampilkan. Sementara itu, silakan jelajahi halaman institusi lainnya di bawah ini.
                </p>
            </div>
        </div>

        {/* ── Navigation Cards ─────────────────────────────────────── */}
        <section className="adm-card" style={{ marginBottom:28 }}>
            <div className="adm-card-head">
                <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                    <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Network size={14} /></span>
                    <h2 className="adm-card-title">Jelajahi Halaman Institusi Lain</h2>
                </div>
                <span className="adm-badge gray">Navigasi Cepat</span>
            </div>
            <div className="adm-card-body">
                <div className="adm-grid adm-cols-3">
                    {navCards.map((card) => {
                        const Icon = card.icon;
                        return <Link href={card.href} key={card.label} className="adm-doc" style={{ textDecoration:'none' }}>
                            <div style={{ display:'flex', alignItems:'center', gap:12 }}>
                                <span className="adm-stat-icon"><Icon size={19} /></span>
                                <b style={{ color:'var(--adm-heading)', fontSize:13.5 }}>{card.label}</b>
                                <ArrowRight size={15} style={{ marginLeft:'auto', color:'var(--adm-brand)' }} />
                            </div>
                            <small className="adm-hint">{card.desc}</small>
                        </Link>;
                    })}
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
                        <Hourglass size={12} /> Butuh Informasi Lebih Cepat?
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Hubungi kami melalui halaman kontak resmi untuk permintaan profil struktur organisasi terkini.
                    </p>
                </div>
            </div>
        </section>
    </InstitutionShell>;
}
