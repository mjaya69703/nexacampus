// Public institusi accreditation (akreditasi kampus) page.
import { Head } from '@inertiajs/react';
import { Award, CalendarCheck, Download, LucideIcon, ScrollText, ShieldCheck } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';

type AccreditationDetail = { label: string; value: string };
type StatItem = { icon: LucideIcon; tone: string; value: string; label: string };

const details: Array<AccreditationDetail> = [
    { label: 'Lembaga Penilai', value: 'BAN-PT (Badan Akreditasi Nasional Perguruan Tinggi)' },
    { label: 'Predikat', value: 'Unggul' },
    { label: 'Masa Berlaku', value: 'Hingga 2030' },
    { label: 'Dasar Penilaian', value: 'Surat Keputusan BAN-PT' },
];

const stats: Array<StatItem> = [
    { icon: ShieldCheck, tone: '', value: 'UNGGUL', label: 'Predikat Institusi' },
    { icon: CalendarCheck, tone: 'green', value: '2030', label: 'Berlaku Hingga' },
    { icon: ScrollText, tone: 'gold', value: 'BAN-PT', label: 'Lembaga Penilai' },
];

export default function Akreditasi({ campus, links, user }: InstitutionBaseProps) {
    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Akreditasi" eyebrow="Akreditasi & Sertifikasi" title="Kualitas yang diakui secara nasional." description="Bukti komitmen kami terhadap kualitas pendidikan yang memenuhi standar mutu nasional dari BAN-PT maupun lembaga internasional." icon={ShieldCheck}>
        <Head title={`Akreditasi Kampus · ${campus.name}`} />

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
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#fbbf24', animation:'pulse-amber 2s infinite' }} />
                        Pengakuan Nasional &amp; Internasional
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Akreditasi Institusi: UNGGUL
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Predikat tertinggi yang memastikan setiap program studi terselenggara dengan standar mutu yang terjamin.
                    </p>
                </div>
                <a className="adm-btn outline" href={links.contact}><ScrollText size={15} /> Verifikasi Manual</a>
            </div>
        </section>

        <style>{`@keyframes pulse-amber { 0%{box-shadow:0 0 0 0 rgba(251,191,36,.7)} 70%{box-shadow:0 0 0 8px rgba(251,191,36,0)} 100%{box-shadow:0 0 0 0 rgba(251,191,36,0)} }`}</style>

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

        <div className="adm-layout" style={{ marginBottom:28 }}>
            {/* ── Certificate Card ────────────────────────────────────── */}
            <section className="adm-card" style={{ textAlign:'center' }}>
                <div className="adm-card-head" style={{ justifyContent:'center' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                        <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-gold)', background:'var(--adm-gold-soft)' }}><Award size={14} /></span>
                        <h2 className="adm-card-title">Sertifikat Akreditasi Institusi</h2>
                    </div>
                </div>
                <div className="adm-card-body" style={{ display:'grid', justifyItems:'center', gap:16 }}>
                    <span className="adm-stat-icon gold" style={{ width:64, height:64, borderRadius:18 }}><Award size={30} /></span>
                    <p style={{ margin:0, color:'var(--adm-heading)', font:"700 26px/1.2 'Source Serif 4', Georgia, serif" }}>Akreditasi Institusi: UNGGUL</p>
                    <p className="adm-hint" style={{ margin:0, maxWidth:480 }}>Berdasarkan Surat Keputusan BAN-PT, {campus.name} telah meraih predikat akreditasi Unggul yang berlaku hingga 2030.</p>
                    <button type="button" className="adm-btn primary"><Download size={15} /> Unduh Sertifikat Akreditasi</button>
                </div>
            </section>

            {/* ── Details ─────────────────────────────────────────────── */}
            <section className="adm-card">
                <div className="adm-card-head">
                    <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                        <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><ShieldCheck size={14} /></span>
                        <h2 className="adm-card-title">Rincian Akreditasi</h2>
                    </div>
                </div>
                <div className="adm-card-body tight">
                    <div className="adm-list">
                        {details.map((detail) => (
                            <div className="adm-list-row" key={detail.label}>
                                <small>{detail.label}</small>
                                <b>{detail.value}</b>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
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
                        <Award size={12} /> Sertifikat Digital Segera Tersedia
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Dokumen unduhan saat ini masih disiapkan — untuk verifikasi sementara, silakan ajukan permintaan melalui halaman kontak resmi kampus.
                    </p>
                </div>
            </div>
        </section>
    </InstitutionShell>;
}
