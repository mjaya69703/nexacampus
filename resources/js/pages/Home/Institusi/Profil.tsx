// Public institusi profile (profil kampus) page.
import { Head, Link } from '@inertiajs/react';
import { GraduationCap, Handshake, IdCard, Library, LucideIcon, Mail, MapPin, MessageCircle, Phone, Target, UserCheck } from 'lucide-react';
import { InstitutionBaseProps, InstitutionShell } from '../../../components/Home/Institution/InstitutionShell';

type ProfileDetail = {
    name: string;
    logoVertikal: string | null;
    address: string | null;
    city: string | null;
    province: string | null;
    postalCode: string | null;
    phone: string | null;
    whatsapp: string | null;
    emailInfo: string | null;
};

export type ProfilPageProps = InstitutionBaseProps & { profile: ProfileDetail | null };

type FastFact = { icon: LucideIcon; tone: string; value: string; label: string };
type ContactRow = { icon: LucideIcon; tone: string; value: string };

const fastFacts: Array<FastFact> = [
    { icon: GraduationCap, tone: '', value: '15K+', label: 'Alumni Sukses' },
    { icon: UserCheck, tone: '', value: '500+', label: 'Dosen Pakar' },
    { icon: Library, tone: 'green', value: '30+', label: 'Program Studi' },
    { icon: Handshake, tone: 'gold', value: '100+', label: 'Mitra Industri' },
];

const cityLine = (city: string | null, province: string | null, postalCode: string | null): string =>
    [city, province].filter(Boolean).join(', ') + (postalCode ? ` ${postalCode}` : '');

export default function Profil({ campus, links, user, profile }: ProfilPageProps) {
    const name = profile?.name ?? campus.name;

    const contactRows: Array<ContactRow> = [];
    if (profile?.phone) contactRows.push({ icon: Phone, tone: '', value: profile.phone });
    if (profile?.whatsapp) contactRows.push({ icon: MessageCircle, tone: 'green', value: profile.whatsapp });
    if (profile?.emailInfo) contactRows.push({ icon: Mail, tone: 'gold', value: profile.emailInfo });

    return <InstitutionShell campus={campus} links={links} user={user} activeTab="Profil" eyebrow="Profil Kampus" title={`Profil ${name}.`} description="Berkomitmen untuk mencetak generasi unggul yang siap bersaing secara global melalui pendidikan berkualitas dan berkarakter." icon={GraduationCap}>
        <Head title={`Profil Kampus · ${campus.name}`} />

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
                        Mengenal Lebih Dekat
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Profil {name}
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Institusi pendidikan tinggi terdepan yang berdedikasi mencetak lulusan kompeten, inovatif, dan berintegritas.
                    </p>
                </div>
                <div style={{ display:'flex', gap:10, flexWrap:'wrap' }}>
                    <a className="adm-btn outline" href={links.contact}><IdCard size={15} /> Hubungi Kami</a>
                    <Link className="adm-btn outline" href="/institusi/visi-misi"><Target size={15} /> Visi &amp; Misi</Link>
                </div>
            </div>
        </section>

        <style>{`@keyframes pulse-green { 0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} 100%{box-shadow:0 0 0 0 rgba(74,222,128,0)} }`}</style>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 24 }}>
            {fastFacts.map((fact) => {
                const Icon = fact.icon;
                return <div className="adm-stat" key={fact.label}>
                    <span className={`adm-stat-icon ${fact.tone}`}><Icon size={18} /></span>
                    <div>
                        <strong className="adm-stat-num">{fact.value}</strong>
                        <span className="adm-stat-label">{fact.label}</span>
                    </div>
                </div>;
            })}
        </div>

        {profile?.logoVertikal && (
            <section className="adm-card" style={{ marginBottom:24 }}>
                <div style={{ padding:26, display:'grid', placeItems:'center' }}>
                    <img src={profile.logoVertikal} alt={`Logo ${name}`} style={{ maxHeight:200 }} />
                </div>
            </section>
        )}

        <div className="adm-layout">
            {/* ── Main Column ─────────────────────────────────────────── */}
            <section className="adm-card">
                <div className="adm-card-head">
                    <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                        <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><MapPin size={14} /></span>
                        <h2 className="adm-card-title">Tentang {name}</h2>
                    </div>
                    <span className="adm-badge">Profil Institusi</span>
                </div>
                <div className="adm-card-body" style={{ display:'grid', gap:12 }}>
                    <p style={{ margin:0, color:'var(--adm-ink)', lineHeight:1.7 }}>Selamat datang di <b>{name}</b>, institusi pendidikan tinggi terdepan yang berdedikasi untuk mencetak lulusan kompeten, inovatif, dan berintegritas. Sejak didirikan, kami terus berinovasi dalam mengintegrasikan teknologi ke dalam proses pembelajaran dan tridharma perguruan tinggi.</p>
                    <p style={{ margin:0, color:'var(--adm-ink)', lineHeight:1.7 }}>Dengan fasilitas kampus yang modern, tenaga pengajar profesional berstandar industri, dan jejaring kerja sama yang luas, kami membekali setiap mahasiswa tidak hanya dengan teori akademik, tetapi juga keterampilan praktis yang dibutuhkan di dunia kerja global.</p>
                    <p style={{ margin:0, color:'var(--adm-ink)', lineHeight:1.7 }}>Lingkungan belajar yang inklusif dan dinamis di {name} mendorong eksplorasi potensi diri secara maksimal, baik dalam bidang akademik maupun non-akademik.</p>
                </div>
            </section>

            {/* ── Sidebar ─────────────────────────────────────────────── */}
            <div className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head">
                        <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                            <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><MapPin size={14} /></span>
                            <h2 className="adm-card-title">Lokasi Kampus</h2>
                        </div>
                    </div>
                    <div className="adm-card-body tight">
                        <div className="adm-list">
                            <div className="adm-list-row">
                                <small>Alamat</small>
                                <b>{profile?.address ?? 'Jl. Pendidikan No. 1, Kota Akademik'}</b>
                            </div>
                            {(profile?.city || profile?.province || profile?.postalCode) && (
                                <div className="adm-list-row">
                                    <small>Kota / Provinsi</small>
                                    <b>{cityLine(profile?.city ?? null, profile?.province ?? null, profile?.postalCode ?? null)}</b>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="adm-card">
                    <div className="adm-card-head">
                        <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                            <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><IdCard size={14} /></span>
                            <h2 className="adm-card-title">Informasi Kontak</h2>
                        </div>
                    </div>
                    <div className="adm-card-body tight" style={{ display:'grid', gap:12 }}>
                        {contactRows.length > 0 ? (
                            <div className="adm-list">
                                {contactRows.map((row) => {
                                    const Icon = row.icon;
                                    return <div key={row.value} style={{ display:'flex', alignItems:'center', gap:10 }}>
                                        <span className={`adm-stat-icon ${row.tone}`} style={{ width:34, height:34, borderRadius:10 }}><Icon size={16} /></span>
                                        <span style={{ color:'var(--adm-ink)' }}>{row.value}</span>
                                    </div>;
                                })}
                            </div>
                        ) : (
                            <p className="adm-hint" style={{ margin:0 }}>Kontak resmi belum dipublikasikan.</p>
                        )}
                        <a className="adm-btn ghost sm block" href={links.contact}><IdCard size={14} /> Halaman Kontak Lengkap</a>
                    </div>
                </section>

                <a className="adm-btn primary block" href={links.admission} style={{ textAlign:'center' }}><GraduationCap size={15} /> Bergabung Bersama Kami</a>
            </div>
        </div>

        {/* ── CTA Gradient ──────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff', marginTop:28,
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'32px 38px',
        }}>
            <div style={{ position:'absolute', top:'-40%', left:'50%', transform:'translateX(-50%)', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'relative', zIndex:2, display:'flex', alignItems:'center', justifyContent:'space-between', gap:20, flexWrap:'wrap' }}>
                <div style={{ maxWidth:580 }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:8, marginBottom:8, color:'rgba(255,255,255,.85)', fontSize:11, fontWeight:800, letterSpacing:'.1em', textTransform:'uppercase' }}>
                        <Target size={12} /> Kenali Arah Institusi
                    </span>
                    <p style={{ margin:0, color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                        Visi dan misi {name} menjadi landasan seluruh program pendidikan, riset, dan pengabdian masyarakat.
                    </p>
                </div>
                <Link className="adm-btn outline" href="/institusi/visi-misi" style={{ whiteSpace:'nowrap' }}><Target size={14} /> Lihat Visi &amp; Misi</Link>
            </div>
        </section>
    </InstitutionShell>;
}
