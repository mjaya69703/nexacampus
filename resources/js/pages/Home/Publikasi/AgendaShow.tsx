// Detail agenda publik — hero status pelaksanaan, deskripsi teks biasa,
// kartu lokasi, dan ringkasan event pada sidebar.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarCheck, CalendarDays, CheckCircle2, Clock3, FolderOpen, Hourglass, Info, MapPin, Pin, ScrollText } from 'lucide-react';
import { CSSProperties } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';

type AgendaCard = { id:number; title:string; slug:string; excerpt:string; eventDate:string|null; day:string|null; month:string|null; fullDate:string|null; time:string|null; location:string|null; isToday:boolean; isUpcoming:boolean; categoryName:string|null; categorySlug:string|null };
type EventDetail = AgendaCard & { description:string|null };
type CategoryRef = { name:string; slug:string };

type Props = PublicationBaseProps & { event:EventDetail; isUpcoming:boolean; categories:CategoryRef[]; latestEvents:AgendaCard[] };

const clampLines = (lines:number): CSSProperties => ({ display:'-webkit-box', WebkitLineClamp:lines, WebkitBoxOrient:'vertical', overflow:'hidden' });

const tileTones = [
    { background:'var(--adm-brand-soft)', color:'var(--adm-brand)' },
    { background:'var(--adm-green-soft)', color:'var(--adm-green)' },
    { background:'var(--adm-gold-soft)', color:'var(--adm-gold)' },
];

const sidebarHeaderStyle = {
    padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
    display:'flex', alignItems:'center', gap:8,
} as const;

export default function AgendaShow({ campus, links, user, event, isUpcoming, categories, latestEvents }: Props) {
    const hasDescription = event.description !== null && event.description.trim() !== '';
    const tilePeriod = [event.month, event.eventDate?.slice(0, 4)].filter(Boolean).join(' ');

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Agenda" eyebrow="Detail Agenda" title="Rincian agenda & kegiatan kampus." description="Jadwal, lokasi, dan deskripsi resmi pelaksanaan kegiatan dari panitia penyelenggara." icon={CalendarDays}>
        <Head title={`${event.title} · ${campus.name}`} />

        <Link href="/agenda" className="adm-btn ghost sm" style={{ justifySelf:'start' }}>
            <ArrowLeft size={14} /> Kembali ke Daftar Agenda
        </Link>

        {/* ── Hero Event ──────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'32px 36px', marginBottom:24,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, display:'flex', flexWrap:'wrap', gap:22, alignItems:'center' }}>
                {/* Date tile */}
                <span style={{ display:'grid', gap:2, justifyItems:'center', padding:'16px 28px', borderRadius:16, background:'#ffffff', flex:'0 0 auto', boxShadow:'0 6px 18px rgba(0,0,0,.15)' }}>
                    <b style={{ color:'var(--adm-brand-deep)', fontSize:28, lineHeight:1, fontWeight:800 }}>{event.day ?? '--'}</b>
                    <small style={{ color:'var(--adm-muted)', fontSize:11, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>{tilePeriod === '' ? '—' : tilePeriod}</small>
                </span>

                <div style={{ minWidth:240, flex:1 }}>
                    <div style={{ display:'flex', flexWrap:'wrap', gap:8, marginBottom:10 }}>
                        {event.categoryName !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5, padding:'4px 12px', borderRadius:999, fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.05em', color:'#fff', background:'rgba(255,255,255,.2)', border:'1px solid rgba(255,255,255,.3)' }}>{event.categoryName}</span>}
                        {isUpcoming
                            ? <span style={{ display:'inline-flex', alignItems:'center', gap:5, padding:'4px 12px', borderRadius:999, fontSize:10.5, fontWeight:700, color:'#4ade80', background:'rgba(74,222,128,.12)', border:'1px solid rgba(74,222,128,.25)' }}><Hourglass size={11} /> Mendatang</span>
                            : <span style={{ display:'inline-flex', alignItems:'center', gap:5, padding:'4px 12px', borderRadius:999, fontSize:10.5, fontWeight:700, color:'rgba(255,255,255,.7)', background:'rgba(255,255,255,.1)' }}><CheckCircle2 size={11} /> Selesai</span>}
                    </div>
                    <b style={{ color:'#fff', lineHeight:1.3, font:"700 clamp(18px, 2.5vw, 26px)/1.3 'Source Serif 4', Georgia, serif" }}>{event.title}</b>
                    <div style={{ display:'flex', flexWrap:'wrap', gap:14, marginTop:12, color:'rgba(255,255,255,.78)', fontSize:12.5 }}>
                        {event.fullDate !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><CalendarDays size={13} />{event.fullDate}</span>}
                        {event.time !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><Clock3 size={13} />{event.time} WIB</span>}
                        {event.location !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><MapPin size={13} />{event.location}</span>}
                    </div>
                </div>
            </div>
        </section>

        <div className="adm-layout">
            <section className="adm-stack">
                {/* Description */}
                <section className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title"><ScrollText size={16} /> Deskripsi &amp; Detail Agenda</h2>
                        <span className={isUpcoming ? 'adm-badge green' : 'adm-badge gray'}>{isUpcoming ? 'Terjadwal' : 'Selesai'}</span>
                    </div>
                    <div className="adm-card-body">
                        {hasDescription
                            ? <p style={{ margin:0, whiteSpace:'pre-line', lineHeight:1.85 }}>{event.description}</p>
                            : <p style={{ margin:0, fontStyle:'italic', color:'var(--adm-muted)' }}>Deskripsi rinci untuk agenda ini belum tersedia.</p>}
                    </div>
                </section>

                {/* Location */}
                {event.location !== null && <section className="adm-card">
                    <div className="adm-card-head"><h2 className="adm-card-title"><MapPin size={16} /> Lokasi Pelaksanaan</h2></div>
                    <div className="adm-card-body tight">
                        <div style={{ display:'flex', gap:14, alignItems:'center' }}>
                            <span style={{ display:'grid', placeItems:'center', width:48, height:48, borderRadius:13, color:'var(--adm-red)', background:'var(--adm-red-soft)' }}><MapPin size={22} /></span>
                            <div>
                                <b style={{ display:'block', color:'var(--adm-heading)' }}>{event.location}</b>
                                <small style={{ color:'var(--adm-muted)', fontSize:12 }}>Pastikan hadir tepat waktu di lokasi kegiatan sesuai jadwal yang ditentukan panitia.</small>
                            </div>
                        </div>
                    </div>
                </section>}
            </section>

            <aside className="adm-stack">
                {/* Summary */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <Info size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Ringkasan Event</h2>
                    </div>
                    <div style={{ padding:'18px 20px' }}>
                        <div style={{ display:'grid', gap:13 }}>
                            <div style={{ display:'grid', gap:2 }}>
                                <small style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-muted)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.07em' }}><CalendarCheck size={12} /> Hari &amp; Tanggal</small>
                                <b style={{ color:'var(--adm-heading)', fontSize:13.5 }}>{event.fullDate ?? 'Akan diumumkan'}</b>
                            </div>
                            {event.time !== null && (
                                <div style={{ display:'grid', gap:2 }}>
                                    <small style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-muted)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.07em' }}><Clock3 size={12} /> Waktu</small>
                                    <b style={{ color:'var(--adm-heading)', fontSize:13.5 }}>{event.time} WIB</b>
                                </div>
                            )}
                            <div style={{ display:'grid', gap:2 }}>
                                <small style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-muted)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.07em' }}><MapPin size={12} /> Lokasi</small>
                                <b style={{ color:'var(--adm-heading)', fontSize:13.5 }}>{event.location ?? 'Belum ditentukan'}</b>
                            </div>
                            <div style={{ display:'grid', gap:2 }}>
                                <small style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-muted)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.07em' }}><Hourglass size={12} /> Status</small>
                                <b style={{ color:'var(--adm-heading)', fontSize:13.5 }}>{isUpcoming ? 'Dijadwalkan berjalan' : 'Telah selesai dilaksanakan'}</b>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Latest Events */}
                {latestEvents.length > 0 && <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <Pin size={14} style={{ color:'var(--adm-gold)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Agenda Lainnya</h2>
                    </div>
                    <div style={{ padding:'14px 20px', display:'grid', gap:6 }}>
                        {latestEvents.map((item, index) => {
                            const tone = tileTones[index % tileTones.length];
                            return <Link href={`/agenda/${item.slug}`} key={item.id} style={{
                                display:'grid', gridTemplateColumns:'48px minmax(0,1fr)', gap:12, alignItems:'center',
                                padding:'10px 12px', borderRadius:12, textDecoration:'none', color:'inherit',
                                transition:'background .15s, transform .15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background='var(--adm-soft)'; e.currentTarget.style.transform='translateX(2px)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; }}
                            >
                                <span style={{ display:'grid', gap:1, placeItems:'center', width:48, height:48, flex:'0 0 auto', borderRadius:12, background:tone.background, color:tone.color }}>
                                    <b style={{ fontSize:16, lineHeight:1 }}>{item.day ?? '–'}</b>
                                    <small style={{ fontSize:9, fontWeight:800, textTransform:'uppercase', letterSpacing:'.04em' }}>{item.month}</small>
                                </span>
                                <span style={{ minWidth:0 }}>
                                    <b style={{ display:'block', color:'var(--adm-heading)', lineHeight:1.35, fontSize:12.5, ...clampLines(2) }}>{item.title}</b>
                                    <small style={{ display:'inline-flex', alignItems:'center', gap:4, marginTop:3, color:'var(--adm-muted)', fontSize:11 }}><MapPin size={11} />{item.location ?? 'Lokasi menyusul'}</small>
                                </span>
                            </Link>;
                        })}
                    </div>
                </section>}

                {/* Categories */}
                {categories.length > 0 && <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <CalendarDays size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Kategori</h2>
                    </div>
                    <div style={{ padding:'16px 20px', display:'flex', flexWrap:'wrap', gap:8 }}>
                        {categories.map((item) => (
                            <Link href="/agenda" key={item.slug} className={item.slug === event.categorySlug ? 'adm-chip active' : 'adm-chip'}>{item.name}</Link>
                        ))}
                    </div>
                </section>}
            </aside>
        </div>
    </PublicationShell>;
}
