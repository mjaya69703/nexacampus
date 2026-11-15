// Halaman kontak publik — info layanan, peta, dan media sosial kampus.
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Compass, ExternalLink, HelpCircle, Mail, MapPin, Phone, Send } from 'lucide-react';
import { useEffect, useRef } from 'react';
import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { Campus, PublicLinks, PublicShell } from '../../../components/Home/PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';
import '../../../../css/admission-public.css';
import 'leaflet/dist/leaflet.css';

type AuthUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;

type ContactData = {
    phone: string | null; faximile: string | null; whatsapp: string | null;
    emailInfo: string | null; emailHumas: string | null;
    address: string | null; city: string | null; province: string | null; postalCode: string | null;
    latitude: string | null; longitude: string | null;
    locations: Array<{ id: number | string; name: string; code: string; address: string | null; latitude: number; longitude: number; isMain: boolean }>;
    instagram: string | null; facebook: string | null; xtwitter: string | null;
    linkedin: string | null; tiktok: string | null;
} | null;

type Props = { campus: Campus; links: PublicLinks; user: AuthUser; contact: ContactData };

const socialLinks = (c: ContactData) => [
    { url: c?.instagram, color: '#e1306c', icon: 'fab fa-instagram', label: 'Instagram' },
    { url: c?.facebook, color: '#1877f2', icon: 'fab fa-facebook-f', label: 'Facebook' },
    { url: c?.xtwitter, color: '#1d1d1f', icon: 'fa-brands fa-x-twitter', label: 'X' },
    { url: c?.linkedin, color: '#0a66c2', icon: 'fab fa-linkedin-in', label: 'LinkedIn' },
    { url: c?.tiktok, color: '#000000', icon: 'fab fa-tiktok', label: 'TikTok' },
].filter((s): s is { url: string; color: string; icon: string; label: string } => typeof s.url === 'string' && s.url.length > 0);

const contactCardStyle = {
    display:'flex', alignItems:'center', gap:18, padding:'22px 24px',
    border:'1px solid var(--adm-line)', borderRadius:16, background:'var(--adm-card)',
    boxShadow:'0 2px 8px rgba(20,39,63,.04)',
    transition:'transform .2s ease, box-shadow .2s ease, border-color .2s ease',
} as const;

const iconBoxStyle = (gradient: string, shadow: string) => ({
    width:58, height:58, borderRadius:16, flexShrink:0,
    display:'flex', alignItems:'center', justifyContent:'center',
    background:gradient, color:'#fff', fontSize:'1.4rem',
    boxShadow:shadow,
});

L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

const escapeHtml = (value: string) => value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

function CampusMap({ locations }: { locations: NonNullable<ContactData>['locations'] }) {
    const mapElement = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!mapElement.current || locations.length === 0) return;

        const map = L.map(mapElement.current, { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        const bounds = L.latLngBounds([]);
        locations.forEach((location) => {
            const point = [location.latitude, location.longitude] as [number, number];
            bounds.extend(point);

            const address = location.address
                ? `<br><span>${escapeHtml(location.address)}</span>`
                : '';
            L.marker(point)
                .addTo(map)
                .bindPopup(`<strong>${escapeHtml(location.name)}</strong>${address}`);
        });

        if (locations.length === 1) {
            map.setView([locations[0].latitude, locations[0].longitude], 16);
        } else {
            map.fitBounds(bounds, { padding: [28, 28], maxZoom: 16 });
        }

        requestAnimationFrame(() => map.invalidateSize());
        return () => map.remove();
    }, [locations]);

    return <div ref={mapElement} className="contact-map" aria-label="Peta lokasi kampus" />;
}

export default function Kontak({ campus, links, user, contact }: Props) {
    const fullAddress = [contact?.address, contact?.city, contact?.province, contact?.postalCode].filter(Boolean).join(', ');
    const socials = socialLinks(contact);
    const locations = contact?.locations ?? [];

    return <PublicShell campus={campus} links={links} user={user} activeNav="Kontak">
        <Head title={`Kontak · ${campus.name}`} />
        <div className="academic-page adm-root">
        <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">Kontak</span><div className="academic-title-row"><span className="academic-title-icon"><Phone size={25} /></span><div><h1>Hubungi NexaCampus.</h1><p>Tim layanan informasi kami siap membantu Anda — hubungi lewat telepon, WhatsApp, atau email resmi.</p></div></div></div><div className="adm-hero-cta"><Link className="adm-btn outline" href={links.faq}><HelpCircle size={14} /> Pusat Bantuan</Link></div></div></header>
        <main className="public-container adm-content">

        {/* ── Contact Cards + Map ─────────────────────────────────── */}
        <div className="contact-layout">
            {/* Left: Contact info cards */}
            <div className="contact-card-list">
                {/* Phone */}
                <div style={contactCardStyle}
                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 10px 28px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(59,130,246,.3)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 2px 8px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                >
                    <div style={iconBoxStyle('linear-gradient(135deg, #3b82f6, #1d4ed8)', '0 8px 24px rgba(59,130,246,.3)')}>
                        <Phone size={24} />
                    </div>
                    <div>
                        <b style={{ display:'block', color:'var(--adm-heading)', fontSize:15 }}>Layanan Telepon</b>
                        <small style={{ color:'var(--adm-muted)', fontSize:12 }}>Panggilan resmi pada jam kerja</small>
                        {contact?.phone
                            ? <div style={{ marginTop:6, color:'var(--adm-brand)', fontWeight:700, fontSize:15 }}>{contact.phone}</div>
                            : <div style={{ marginTop:6, color:'var(--adm-muted)', fontStyle:'italic', fontSize:13 }}>-</div>}
                        {contact?.faximile && <div style={{ marginTop:2, color:'var(--adm-muted)', fontSize:11 }}>Fax: {contact.faximile}</div>}
                    </div>
                </div>

                {/* WhatsApp */}
                <div style={contactCardStyle}
                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 10px 28px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(16,185,129,.3)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 2px 8px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                >
                    <div style={iconBoxStyle('linear-gradient(135deg, #10b981, #059669)', '0 8px 24px rgba(16,185,129,.3)')}>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </div>
                    <div>
                        <b style={{ display:'block', color:'var(--adm-heading)', fontSize:15 }}>WhatsApp Center</b>
                        <small style={{ color:'var(--adm-muted)', fontSize:12 }}>Pesan cepat khusus info PMB/Mahasiswa</small>
                        {contact?.whatsapp
                            ? <a href={`https://wa.me/${contact.whatsapp.replace(/[^0-9]/g, '')}`} target="_blank" rel="noreferrer" style={{ display:'inline-flex', alignItems:'center', gap:6, marginTop:6, color:'var(--adm-green)', fontWeight:700, fontSize:15, textDecoration:'none' }}>
                                {contact.whatsapp} <ExternalLink size={12} />
                            </a>
                            : <div style={{ marginTop:6, color:'var(--adm-muted)', fontStyle:'italic', fontSize:13 }}>-</div>}
                    </div>
                </div>

                {/* Email */}
                <div style={contactCardStyle}
                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 10px 28px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(139,92,246,.3)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 2px 8px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                >
                    <div style={iconBoxStyle('linear-gradient(135deg, #8b5cf6, #7c3aed)', '0 8px 24px rgba(139,92,246,.3)')}>
                        <Mail size={24} />
                    </div>
                    <div style={{ flex:1, minWidth:0 }}>
                        <b style={{ display:'block', color:'var(--adm-heading)', fontSize:15 }}>Email Resmi</b>
                        <small style={{ color:'var(--adm-muted)', fontSize:12 }}>Pertanyaan umum & persuratan</small>
                        {contact?.emailInfo
                            ? <a href={`mailto:${contact.emailInfo}`} style={{ display:'block', marginTop:6, color:'#8b5cf6', fontWeight:700, fontSize:14, textDecoration:'none' }}>{contact.emailInfo}</a>
                            : <div style={{ marginTop:6, color:'var(--adm-muted)', fontStyle:'italic', fontSize:13 }}>-</div>}
                        {contact?.emailHumas && <a href={`mailto:${contact.emailHumas}`} style={{ display:'block', marginTop:2, color:'var(--adm-muted)', fontSize:12, textDecoration:'none' }}>Humas: {contact.emailHumas}</a>}
                    </div>
                </div>
            </div>

            {/* Right: Map + Address */}
            <div className="contact-map-card" style={{
                border:'1px solid var(--adm-line)', borderRadius:16, background:'var(--adm-card)',
                boxShadow:'0 2px 8px rgba(20,39,63,.04)', overflow:'hidden', display:'flex', flexDirection:'column',
            }}>
                {/* Address header */}
                <div style={{ padding:'20px 24px', borderBottom:'1px solid var(--adm-line)' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:10, marginBottom:8 }}>
                        <MapPin size={18} style={{ color:'var(--adm-brand)' }} />
                        <b style={{ color:'var(--adm-heading)', fontSize:16, fontWeight:800 }}>Kunjungi Kampus Kami</b>
                    </div>
                    <p style={{ margin:0, color:'var(--adm-muted)', fontSize:13, lineHeight:1.7 }}>
                        {locations.length > 1 ? `${locations.length} lokasi kampus tersedia.` : (fullAddress || 'Alamat kampus belum tersedia.')}
                    </p>
                </div>

                {/* Map area */}
                <div className="contact-map-wrap">
                    {locations.length ? <CampusMap locations={locations} /> : (
                        <div style={{ position:'absolute', inset:0, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', color:'var(--adm-muted)' }}>
                            <Compass size={48} style={{ opacity:.4, marginBottom:12 }} />
                            <b style={{ fontSize:14 }}>Peta Lokasi</b>
                            <small style={{ fontSize:12, marginTop:4 }}>Peta interaktif akan dimuat di sini</small>
                        </div>
                    )}
                </div>

                {/* Social media footer */}
                <div style={{ padding:'18px 24px', borderTop:'1px solid var(--adm-line)', background:'var(--adm-soft)' }}>
                    <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', flexWrap:'wrap', gap:12 }}>
                        <b style={{ color:'var(--adm-heading)', fontSize:13 }}>Media Sosial Resmi</b>
                        {socials.length > 0 && <div style={{ display:'flex', gap:8 }}>
                            {socials.map((s) => (
                                <a key={s.label} href={s.url} target="_blank" rel="noreferrer" aria-label={s.label} style={{
                                    display:'grid', placeItems:'center', width:36, height:36, borderRadius:'50%',
                                    border:`1px solid ${s.color}22`, color:s.color, textDecoration:'none',
                                    transition:'background .15s, transform .15s',
                                }}
                                onMouseEnter={(e) => { e.currentTarget.style.background=`${s.color}12`; e.currentTarget.style.transform='translateY(-2px)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; }}
                                >
                                    <i className={s.icon} />
                                </a>
                            ))}
                        </div>}
                    </div>
                </div>
            </div>
        </div>

        </main>
        </div>

    </PublicShell>;
}
