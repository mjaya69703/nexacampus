// Public gallery page (Inertia migration of the Livewire Volt galeri component).
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Camera, ChevronLeft, ChevronRight, FolderOpen, HelpCircle, Images, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type GalleryImage = { url: string | null; caption: string | null };
type Album = { id: number; title: string; description: string | null; cover: string | null; imageCount: number; categoryName: string; images: GalleryImage[] };
type Props = PublicationBaseProps & { albums: Album[] };
type Lightbox = { albumIndex: number; imageIndex: number };

const trimDescription = (text: string): string => (text.length > 130 ? `${text.slice(0, 130)}…` : text);

export default function Galeri({ campus, links, user, albums }: Props) {
    const [lightbox, setLightbox] = useState<Lightbox | null>(null);

    const photoTotal = useMemo(() => albums.reduce((sum, album) => sum + album.imageCount, 0), [albums]);
    const categoryTotal = useMemo(() => new Set(albums.map((album) => album.categoryName)).size, [albums]);

    const activeAlbum = lightbox === null ? null : albums[lightbox.albumIndex] ?? null;
    const activeImage = activeAlbum !== null && lightbox !== null ? activeAlbum.images[lightbox.imageIndex] ?? null : null;
    const canPrev = lightbox !== null && lightbox.imageIndex > 0;
    const canNext = activeAlbum !== null && lightbox !== null && lightbox.imageIndex < activeAlbum.images.length - 1;

    const closeLightbox = () => setLightbox(null);
    const stepImage = (delta: number) => setLightbox((current) => {
        if (!current) return current;
        const album = albums[current.albumIndex];
        if (!album) return current;
        const next = current.imageIndex + delta;
        if (next < 0 || next >= album.images.length) return current;
        return { ...current, imageIndex: next };
    });

    useEffect(() => {
        if (!lightbox) return undefined;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') { setLightbox(null); return; }
            if (event.key === 'ArrowLeft') stepImage(-1);
            if (event.key === 'ArrowRight') stepImage(1);
        };
        document.addEventListener('keydown', onKeyDown);
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
        };
    }, [lightbox, albums]);

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Galeri" eyebrow="Galeri" title="Galeri & momen kampus." description="Dokumentasi visual kegiatan civitas akademika, fasilitas, dan peristiwa berkesan di lingkungan kampus." icon={Images}
        action={<div className="adm-hero-cta">
            <Link className="adm-btn light" href="/agenda"><CalendarDays size={15} /> Agenda Kampus</Link>
            <Link className="adm-btn outline" href={links.faq}><HelpCircle size={14} /> Pusat Bantuan</Link>
        </div>}>
        <Head title={`Galeri · ${campus.name}`} />

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
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#4ade80' }} />
                        Dokumentasi Visual {campus.name}
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Setiap momen kampus, dalam satu bingkai.
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Klik salah satu album untuk membuka tayangan layar penuh — navigasikan dengan tombol sisi layar atau tombol panah keyboard, lalu tutup dengan Esc.
                    </p>
                </div>
                <div style={{ display:'flex', gap:10, flexWrap:'wrap' }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'#fff', background:'rgba(255,255,255,.18)', border:'1px solid rgba(255,255,255,.28)' }}>
                        <Camera size={12} /> Dokumentasi Resmi
                    </span>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'rgba(255,255,255,.8)', background:'rgba(255,255,255,.08)', border:'1px solid rgba(255,255,255,.18)' }}>
                        <Images size={12} /> {photoTotal} Foto Tersimpan
                    </span>
                </div>
            </div>
        </section>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 28 }}>
            {[
                { icon: <Camera size={18} />, num: albums.length, label: 'Album Terbit', tone: '' },
                { icon: <Images size={18} />, num: photoTotal, label: 'Total Foto', tone: 'green' },
                { icon: <FolderOpen size={18} />, num: categoryTotal, label: 'Kategori Album', tone: 'gold' },
            ].map((s) => (
                <div className="adm-stat" key={s.label}>
                    <span className={`adm-stat-icon ${s.tone}`}>{s.icon}</span>
                    <div>
                        <strong className="adm-stat-num">{s.num}</strong>
                        <span className="adm-stat-label">{s.label}</span>
                    </div>
                </div>
            ))}
        </div>

        {albums.length === 0 ? <EmptyState
            icon={Images}
            title="Belum Ada Album Foto"
            description="Album dokumentasi foto kampus akan langsung tampil di halaman ini segera setelah dipublikasikan oleh humas institusi."
            action={<Link className="adm-btn primary" href="/agenda"><CalendarDays size={14} /> Lihat Agenda Kampus</Link>}
        /> : <section style={{ display:'grid', gap:16 }}>
            <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:10 }}>
                <span style={{ display:'grid', placeItems:'center', width:34, height:34, borderRadius:10, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Images size={16} /></span>
                <h2 className="adm-card-title">Album Dokumentasi</h2>
                <hr className="adm-divider" style={{ flex:1, margin:0 }} />
                <span className="adm-badge">{albums.length} Album</span>
            </div>

            <div className="adm-grid adm-cols-3">
                {albums.map((album, albumIndex) => (
                    <button
                        key={album.id}
                        type="button"
                        onClick={() => setLightbox({ albumIndex, imageIndex: 0 })}
                        aria-label={`Buka galeri ${album.title}`}
                        style={{
                            display:'block', width:'100%', padding:0, textAlign:'left', cursor:'pointer', fontFamily:'inherit',
                            border:'1px solid var(--adm-line)', borderRadius:14, overflow:'hidden',
                            background:'var(--adm-card)', boxShadow:'0 1px 3px rgba(20,39,63,.04)',
                            transition:'transform .22s ease, box-shadow .22s ease, border-color .22s ease',
                        }}
                        onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-4px)'; e.currentTarget.style.boxShadow='0 12px 30px rgba(0,0,0,.08)'; e.currentTarget.style.borderColor='rgba(59,130,246,.3)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 1px 3px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                    >
                        {album.cover
                            ? <img src={album.cover} alt={album.title} loading="lazy" style={{ display:'block', width:'100%', height:175, objectFit:'cover', transition:'transform .35s ease' }} />
                            : <span style={{ display:'grid', placeItems:'center', height:175, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Images size={30} /></span>}
                        <span style={{ display:'grid', gap:8, padding:'16px 18px 18px' }}>
                            <span style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
                                <span className="adm-badge">{album.categoryName}</span>
                                <span className="adm-badge solid"><Images size={11} /> {album.imageCount} Foto</span>
                            </span>
                            <b style={{ color:'var(--adm-heading)', lineHeight:1.4, fontSize:14 }}>{album.title}</b>
                            {album.description && <small style={{ color:'var(--adm-muted)', fontSize:12.5, lineHeight:1.6 }}>{trimDescription(album.description)}</small>}
                        </span>
                    </button>
                ))}
            </div>
        </section>}

        {/* ── Lightbox ─────────────────────────────────────────────── */}
        {activeAlbum !== null && lightbox !== null && <div
            role="dialog"
            aria-modal="true"
            aria-label={`Galeri foto ${activeAlbum.title}`}
            style={{ position:'fixed', inset:0, zIndex:60, background:'rgba(15,23,42,.92)', backdropFilter:'blur(12px)', display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', gap:14, padding:24, animation:'fadeIn .2s ease-out' }}
        >
            <style>{`@keyframes fadeIn { from{opacity:0} to{opacity:1} }`}</style>
            <button type="button" onClick={closeLightbox} aria-label="Tutup galeri" title="Tutup (Esc)" style={{ position:'fixed', top:20, right:20, zIndex:10000, width:44, height:44, display:'grid', placeItems:'center', border:0, borderRadius:'50%', background:'rgba(255,255,255,.12)', color:'#fff', cursor:'pointer', backdropFilter:'blur(8px)', transition:'background .2s' }}
                onMouseEnter={(e) => { e.currentTarget.style.background='rgba(255,255,255,.25)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background='rgba(255,255,255,.12)'; }}
            ><X size={20} /></button>
            {canPrev && <button type="button" onClick={() => stepImage(-1)} aria-label="Foto sebelumnya" title="Foto Sebelumnya" style={{ position:'fixed', left:20, top:'50%', transform:'translateY(-50%)', zIndex:10000, width:44, height:44, display:'grid', placeItems:'center', border:0, borderRadius:'50%', background:'rgba(255,255,255,.12)', color:'#fff', cursor:'pointer', backdropFilter:'blur(8px)', transition:'background .2s' }}
                onMouseEnter={(e) => { e.currentTarget.style.background='rgba(255,255,255,.25)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background='rgba(255,255,255,.12)'; }}
            ><ChevronLeft size={22} /></button>}
            {canNext && <button type="button" onClick={() => stepImage(1)} aria-label="Foto berikutnya" title="Foto Berikutnya" style={{ position:'fixed', right:20, top:'50%', transform:'translateY(-50%)', zIndex:10000, width:44, height:44, display:'grid', placeItems:'center', border:0, borderRadius:'50%', background:'rgba(255,255,255,.12)', color:'#fff', cursor:'pointer', backdropFilter:'blur(8px)', transition:'background .2s' }}
                onMouseEnter={(e) => { e.currentTarget.style.background='rgba(255,255,255,.25)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background='rgba(255,255,255,.12)'; }}
            ><ChevronRight size={22} /></button>}

            {activeImage?.url
                ? <img src={activeImage.url} alt={activeImage.caption ?? `${activeAlbum.title} — foto ${lightbox.imageIndex + 1}`}
                    style={{ maxWidth:'min(940px, 100%)', maxHeight:'64vh', objectFit:'contain', borderRadius:14, border:'1px solid rgba(255,255,255,.2)', boxShadow:'0 25px 70px rgba(0,0,0,.5)' }} />
                : <span style={{ display:'grid', placeItems:'center', gap:8, width:'min(560px, 100%)', padding:'48px 24px', border:'1.5px dashed rgba(255,255,255,.3)', borderRadius:16, color:'#fff' }}>
                    <Images size={32} /><small>Gambar tidak dapat dimuat.</small>
                </span>}

            <div style={{ display:'grid', justifyItems:'center', gap:7, textAlign:'center', maxWidth:640 }}>
                <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'5px 13px', borderRadius:999, background:'rgba(255,255,255,.15)', color:'#fff', fontWeight:700, fontSize:12 }}>
                    <Camera size={12} /> Foto {lightbox.imageIndex + 1} dari {activeAlbum.images.length}
                </span>
                <b style={{ color:'#fff', fontSize:14 }}>{activeAlbum.title}</b>
                {activeImage?.caption && <p style={{ margin:0, color:'rgba(255,255,255,.78)', fontSize:13 }}>{activeImage.caption}</p>}
            </div>
        </div>}
    </PublicationShell>;
}
