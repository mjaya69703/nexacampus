// Public institusi partnerships (mitra kerjasama) page.
import { Head } from '@inertiajs/react';
import { Briefcase, Building2, ExternalLink, Globe, Handshake, LucideIcon } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type Partner = { name: string; industry: string; website: string | null; logo: string | null };

export type KerjasamaPageProps = InstitutionBaseProps & { total: number; partners: Array<Partner> };

const avatarTones: Array<string> = ['', 'green', 'gold', 'red'];

const initialsFor = (name: string): string =>
    name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

const hostOf = (url: string): string => {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
};

export default function Kerjasama({ campus, links, user, total, partners }: KerjasamaPageProps) {
    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Kerjasama" eyebrow="Mitra Kerjasama" title="Jejaring mitra nasional & global." description="Berkolaborasi dengan ratusan institusi akademik, pemerintah, dan perusahaan multinasional untuk memajukan pendidikan dan riset." icon={Handshake}>
        <Head title={`Mitra Kerjasama · ${campus.name}`} />

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
                        Penyaluran magang, rekrutmen, dan riset bersama
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Jejaring Mitra {campus.name}
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Setiap kemitraan membuka pintu magang, rekrutmen lulusan, dan kolaborasi riset bagi mahasiswa.
                    </p>
                </div>
                <div style={{ display:'flex', gap:12 }}>
                    <div style={{
                        padding:'14px 22px', border:'1px solid rgba(255,255,255,.22)', borderRadius:14,
                        background:'rgba(255,255,255,.08)', textAlign:'center', backdropFilter:'blur(8px)',
                    }}>
                        <b style={{ display:'block', color:'#fff', fontSize:22, lineHeight:1 }}>{total}+</b>
                        <small style={{ color:'rgba(255,255,255,.7)', fontSize:10, fontWeight:700, letterSpacing:'.06em', textTransform:'uppercase' }}>Mitra Terdaftar</small>
                    </div>
                </div>
            </div>
        </section>

        <style>{`@keyframes pulse-green { 0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} 100%{box-shadow:0 0 0 0 rgba(74,222,128,0)} }`}</style>

        {/* ── Partner Grid ──────────────────────────────────────────── */}
        {partners.length > 0 ? (
            <section className="adm-card" style={{ marginBottom:28 }}>
                <div className="adm-card-head">
                    <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                        <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Handshake size={14} /></span>
                        <h2 className="adm-card-title">Mitra Kerjasama</h2>
                    </div>
                    <span className="adm-badge">{partners.length} Mitra Aktif</span>
                </div>
                <div className="adm-card-body">
                    <div className="adm-grid adm-cols-4">
                        {partners.map((partner, index) => {
                            const initials = initialsFor(partner.name);
                            const tone = avatarTones[index % avatarTones.length];
                            return <article className="adm-card" key={`${partner.name}-${index}`}>
                                <div className="adm-card-body tight" style={{ display:'grid', gap:9, justifyItems:'center', textAlign:'center' }}>
                                    <span className={`adm-stat-icon ${tone}`} style={{ width:46, height:46, borderRadius:13, font:"700 15px 'Source Serif 4', Georgia, serif" }}>
                                        {initials || <Building2 size={20} />}
                                    </span>
                                    <b style={{ color:'var(--adm-heading)', fontSize:13, lineHeight:1.35 }}>{partner.name}</b>
                                    <small className="adm-hint">{partner.industry}</small>
                                    {partner.website && (
                                        <a href={partner.website} target="_blank" rel="noopener noreferrer" className="adm-badge gray" style={{ textDecoration:'none' }}>
                                            <Globe size={12} /> {hostOf(partner.website)}
                                        </a>
                                    )}
                                </div>
                            </article>;
                        })}
                    </div>
                </div>
            </section>
        ) : (
            <EmptyState
                icon={Handshake}
                iconSize={28}
                title="Belum Ada Mitra Terdaftar"
                description="Daftar mitra kerjasama akan segera ditampilkan. Nantikan pembaruan jejaring industri dan institusi mitra kami."
            />
        )}

        {/* ── CTA Gradient ──────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'32px 38px', marginBottom: total > partners.length ? 18 : 0,
        }}>
            <div style={{ position:'absolute', top:'-40%', left:'50%', transform:'translateX(-50%)', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'relative', zIndex:2, display:'flex', alignItems:'center', justifyContent:'space-between', gap:20, flexWrap:'wrap' }}>
                <div style={{ maxWidth:580 }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:8, marginBottom:8, color:'rgba(255,255,255,.85)', fontSize:11, fontWeight:800, letterSpacing:'.1em', textTransform:'uppercase' }}>
                        <Briefcase size={12} /> Dari Kampus Langsung ke Dunia Kerja
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Kerja sama mencakup penyaluran magang, program rekrutmen lulusan, hingga riset bersama — manfaat nyata bagi setiap mahasiswa {campus.name}.
                    </p>
                </div>
            </div>
        </section>

        {total > partners.length && (
            <div className="adm-card">
                <div style={{ padding:'18px 22px', display:'flex', alignItems:'center', gap:14 }}>
                    <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)', flexShrink:0 }}><ExternalLink size={14} /></span>
                    <div>
                        <b style={{ display:'block', color:'var(--adm-heading)', fontSize:13, marginBottom:2 }}>{total - partners.length} mitra lainnya belum ditampilkan.</b>
                        <small style={{ color:'var(--adm-muted)', fontSize:12 }}>Halaman ini memuat cuplikan mitra teraktif — daftar lengkap akan diperbarui secara berkala oleh tim kerja sama industri.</small>
                    </div>
                </div>
            </div>
        )}
    </InstitutionShell>;
}
