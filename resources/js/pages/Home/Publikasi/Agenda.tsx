// Agenda & kalender kegiatan publik — tab periode, pencarian, filter bulan/kategori,
// dan paginasi sepenuhnya di sisi klien (migrasi Livewire Volt).
import { Head, Link } from '@inertiajs/react';
import { Archive, ArrowRight, CalendarCheck, CalendarDays, CalendarX2, Clock3, FolderOpen, ListFilter, MapPin, Pin, RotateCcw, Search, Sparkles, Star } from 'lucide-react';
import { CSSProperties, useMemo, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { Pagination } from '../../../components/Shared/Pagination';

type AgendaCard = { id:number; title:string; slug:string; excerpt:string; eventDate:string|null; day:string|null; month:string|null; fullDate:string|null; time:string|null; location:string|null; isToday:boolean; isUpcoming:boolean; categoryName:string|null; categorySlug:string|null };
type CategoryItem = { name:string; slug:string; count:number };
type TabChoice = 'mendatang' | 'arsip';

type Props = PublicationBaseProps & { events:AgendaCard[]; upcomingCount:number; pastCount:number; totalEvents:number; months:string[]; categories:CategoryItem[]; featuredEvent:AgendaCard|null; latestEvents:AgendaCard[] };

const PAGE_SIZE = 10;
const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const clampLines = (lines:number): CSSProperties => ({ display:'-webkit-box', WebkitLineClamp:lines, WebkitBoxOrient:'vertical', overflow:'hidden' });
const monthLabel = (value:string): string => {
    const [yearText, monthText] = value.split('-');
    const name = bulan[Number(monthText) - 1];
    return `${name ?? monthText ?? ''} ${yearText ?? ''}`.trim();
};
const shortLocation = (value:string): string => (value.length > 26 ? `${value.slice(0, 26)}…` : value);

const tileTones = [
    { background:'var(--adm-brand-soft)', color:'var(--adm-brand)' },
    { background:'var(--adm-green-soft)', color:'var(--adm-green)' },
    { background:'var(--adm-gold-soft)', color:'var(--adm-gold)' },
];

const sidebarHeaderStyle = {
    padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
    display:'flex', alignItems:'center', gap:8,
} as const;

export default function Agenda({ campus, links, user, events, upcomingCount, pastCount, totalEvents, months, categories, featuredEvent, latestEvents }: Props) {
    const [tab, setTab] = useState<TabChoice>('mendatang');
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [month, setMonth] = useState('');
    const [page, setPage] = useState(1);

    const query = search.trim().toLowerCase();

    const visibleEvents = useMemo(() => {
        const scoped = events.filter((event) => (tab === 'mendatang' ? event.isUpcoming : !event.isUpcoming)
            && (category === '' || event.categorySlug === category)
            && (month === '' || event.eventDate?.slice(0, 7) === month));
        const matched = query === '' ? scoped : scoped.filter((event) => event.title.toLowerCase().includes(query) || event.excerpt.toLowerCase().includes(query));
        const dated = matched.filter((event) => event.eventDate !== null);
        const undated = matched.filter((event) => event.eventDate === null);
        dated.sort((first, second) => {
            const order = (first.eventDate ?? '').localeCompare(second.eventDate ?? '');
            return tab === 'mendatang' ? order : -order;
        });
        return [...dated, ...undated];
    }, [events, tab, category, month, query]);

    const totalPages = Math.max(1, Math.ceil(visibleEvents.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pagedEvents = visibleEvents.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
    const pristineFilters = query === '' && category === '' && month === '';
    const showFeatured = featuredEvent !== null && tab === 'mendatang' && currentPage === 1 && pristineFilters;

    const resetFilters = (): void => { setSearch(''); setCategory(''); setMonth(''); setPage(1); };
    const switchTab = (next:TabChoice): void => { setTab(next); setPage(1); };

    const pagination = <Pagination page={currentPage} totalPages={totalPages} total={visibleEvents.length} unit="agenda" onPrev={() => setPage(currentPage - 1)} onNext={() => setPage(currentPage + 1)} />;

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Agenda" eyebrow="Agenda Kampus" title="Tanggal penting & event terpadu." description="Pantau seminar, workshop, wisuda, dan kegiatan akademik — lengkap dengan penanda hari ini serta arsip tahunan." icon={CalendarDays}>
        <Head title={`Agenda Kampus · ${campus.name}`} />

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            <div style={{ position:'absolute', top:'-40%', right:'-8%', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'absolute', bottom:'-35%', left:'-5%', width:320, height:320, borderRadius:'50%', background:'radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%)', pointerEvents:'none' }} />

            <div style={{ position:'relative', zIndex:2, display:'flex', flexWrap:'wrap', alignItems:'flex-end', justifyContent:'space-between', gap:20 }}>
                <div style={{ maxWidth:600 }}>
                    <span style={{
                        display:'inline-flex', alignItems:'center', gap:8, marginBottom:14,
                        padding:'5px 14px', borderRadius:999, border:'1px solid rgba(255,255,255,.25)',
                        background:'rgba(255,255,255,.09)', color:'#dce8f5', fontSize:11, fontWeight:700,
                        letterSpacing:'.04em', textTransform:'uppercase',
                    }}>
                        <span style={{ width:8, height:8, borderRadius:'50%', background:'#4ade80' }} />
                        Agenda &amp; Kalender Akademik
                    </span>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Seluruh kegiatan resmi kampus tersaji dalam satu kalender — gunakan tab periode dan filter bulan agar tidak ada tanggal penting yang terlewat.
                    </p>
                </div>
                <span style={{
                    display:'inline-flex', alignItems:'center', gap:6, padding:'8px 18px',
                    borderRadius:999, fontSize:12, fontWeight:700,
                    color:'#fff', background:'rgba(255,255,255,.15)', border:'1px solid rgba(255,255,255,.25)',
                    backdropFilter:'blur(8px)',
                }}>
                    <Sparkles size={13} /> {upcomingCount} Agenda Mendatang
                </span>
            </div>
        </section>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 24 }}>
            {[
                { icon: <CalendarDays size={18} />, num: totalEvents, label: 'Total Event', tone: '' },
                { icon: <CalendarCheck size={18} />, num: upcomingCount, label: 'Mendatang', tone: 'green' },
                { icon: <Archive size={18} />, num: pastCount, label: 'Arsip', tone: 'gold' },
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

        {/* ── Featured Event ───────────────────────────────────────── */}
        {showFeatured && featuredEvent !== null && (
            <Link href={`/agenda/${featuredEvent.slug}`} style={{
                display:'grid', gridTemplateColumns:'minmax(0, 1.4fr) minmax(180px, .6fr)',
                overflow:'hidden', borderRadius:16, textDecoration:'none', color:'inherit',
                border:'1px solid var(--adm-line)', background:'var(--adm-card)',
                boxShadow:'0 2px 8px rgba(20,39,63,.04)',
                transition:'transform .22s ease, box-shadow .22s ease, border-color .22s ease', marginBottom:24,
            }}
            onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-4px)'; e.currentTarget.style.boxShadow='0 14px 36px rgba(59,130,246,.1)'; e.currentTarget.style.borderColor='rgba(59,130,246,.35)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 2px 8px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
            >
                <div style={{ padding:'28px 30px', display:'flex', flexDirection:'column', gap:10, justifyContent:'center' }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:7, alignSelf:'flex-start', color:'var(--adm-brand)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.1em' }}>
                        <Star size={13} /> Agenda Terdekat Utama
                    </span>
                    {featuredEvent.categoryName !== null && (
                        <span style={{ display:'inline-flex', alignItems:'center', alignSelf:'flex-start', padding:'4px 12px', borderRadius:999, fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.05em', background:'rgba(37,99,174,.08)', color:'var(--adm-brand)', border:'1px solid rgba(37,99,174,.15)' }}>
                            {featuredEvent.categoryName}
                        </span>
                    )}
                    <b style={{ color:'var(--adm-heading)', lineHeight:1.3, font:"700 clamp(18px, 2.2vw, 24px)/1.3 'Source Serif 4', Georgia, serif", letterSpacing:'-.015em' }}>
                        {featuredEvent.title}
                    </b>
                    {featuredEvent.excerpt !== '' && <span style={{ color:'var(--adm-muted)', fontSize:13.5, lineHeight:1.65, maxWidth:500 }}>{featuredEvent.excerpt}</span>}
                    <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:14, marginTop:6, color:'var(--adm-muted)', fontSize:12.5 }}>
                        {featuredEvent.fullDate !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><CalendarDays size={13} />{featuredEvent.fullDate}</span>}
                        {featuredEvent.time !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><Clock3 size={13} />{featuredEvent.time} WIB</span>}
                        {featuredEvent.location !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><MapPin size={13} />{shortLocation(featuredEvent.location)}</span>}
                        <span style={{
                            marginLeft:'auto', display:'inline-flex', alignItems:'center', gap:6,
                            padding:'8px 18px', borderRadius:999, fontSize:12, fontWeight:700,
                            color:'#fff', background:'linear-gradient(135deg, var(--adm-brand), #3b7cc4)',
                            boxShadow:'0 4px 12px rgba(37,99,174,.28)',
                        }}>
                            Lihat Detail <ArrowRight size={13} />
                        </span>
                    </div>
                </div>
                <div style={{ display:'grid', placeItems:'center', padding:'24px 20px', background:'var(--adm-soft)', borderLeft:'1px solid var(--adm-line)' }}>
                    <span style={{ display:'grid', gap:2, justifyItems:'center', padding:'18px 34px', borderRadius:16, background:'var(--adm-card)', boxShadow:'0 4px 14px rgba(20,39,63,.08)' }}>
                        <b style={{ color:'var(--adm-brand)', fontSize:28, lineHeight:1, fontWeight:800 }}>{featuredEvent.day ?? '--'}</b>
                        <small style={{ color:'var(--adm-muted)', fontSize:11, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>{[featuredEvent.month, featuredEvent.eventDate?.slice(0, 4)].filter(Boolean).join(' ') || '—'}</small>
                    </span>
                </div>
            </Link>
        )}

        {/* ── Main Layout ──────────────────────────────────────────── */}
        <div className="adm-layout">
            {/* Sidebar */}
            <aside className="adm-stack">
                {/* Tab Period */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <ListFilter size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Periode Event</h2>
                    </div>
                    <div style={{ padding:'14px 20px', display:'flex', gap:8 }}>
                        <button type="button" className={tab === 'mendatang' ? 'adm-chip active' : 'adm-chip'} style={{ flex:1, justifyContent:'center' }} onClick={() => switchTab('mendatang')}>
                            <CalendarCheck size={14} /> Mendatang<b>{upcomingCount}</b>
                        </button>
                        <button type="button" className={tab === 'arsip' ? 'adm-chip active' : 'adm-chip'} style={{ flex:1, justifyContent:'center' }} onClick={() => switchTab('arsip')}>
                            <Archive size={14} /> Arsip<b>{pastCount}</b>
                        </button>
                    </div>
                </section>

                {/* Search & Filter */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <Search size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Cari &amp; Filter</h2>
                    </div>
                    <div style={{ padding:'18px 20px', display:'grid', gap:10 }}>
                        <input type="search" className="adm-input" placeholder="Cari nama agenda…" value={search} aria-label="Cari agenda"
                            onChange={(change) => { setSearch(change.target.value); setPage(1); }} />
                        <select className="adm-input" value={month} aria-label="Filter bulan pelaksanaan"
                            onChange={(change) => { setMonth(change.target.value); setPage(1); }}>
                            <option value="">Semua Bulan &amp; Tahun</option>
                            {months.map((value) => <option key={value} value={value}>{monthLabel(value)}</option>)}
                        </select>
                        {!pristineFilters && <button type="button" className="adm-btn soft block sm" onClick={resetFilters}><RotateCcw size={13} /> Reset Filter</button>}
                    </div>
                </section>

                {/* Categories */}
                {categories.length > 0 && <section className="adm-card" style={{ padding:0 }}>
                    <div style={{ ...sidebarHeaderStyle, justifyContent:'space-between' }}>
                        <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                            <FolderOpen size={14} style={{ color:'var(--adm-brand)' }} />
                            <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Kategori</h2>
                        </div>
                        <span className="adm-badge gray" style={{ fontSize:10 }}>{categories.length}</span>
                    </div>
                    <div style={{ padding:'16px 20px', display:'flex', flexWrap:'wrap', gap:8 }}>
                        <button type="button" className={category === '' ? 'adm-chip active' : 'adm-chip'} onClick={() => { setCategory(''); setPage(1); }}>Semua<b>{events.length}</b></button>
                        {categories.map((item) => (
                            <button type="button" key={item.slug} className={category === item.slug ? 'adm-chip active' : 'adm-chip'} onClick={() => { setCategory(item.slug); setPage(1); }}>{item.name}<b>{item.count}</b></button>
                        ))}
                    </div>
                </section>}

                {/* Latest Events */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={sidebarHeaderStyle}>
                        <Pin size={14} style={{ color:'var(--adm-gold)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Agenda Terdekat</h2>
                    </div>
                    <div style={{ padding:'14px 20px', display:'grid', gap:6 }}>
                        {latestEvents.map((event, index) => {
                            const tone = tileTones[index % tileTones.length];
                            return <Link href={`/agenda/${event.slug}`} key={event.id} style={{
                                display:'grid', gridTemplateColumns:'48px minmax(0,1fr)', gap:12, alignItems:'center',
                                padding:'10px 12px', borderRadius:12, textDecoration:'none', color:'inherit',
                                transition:'background .15s, transform .15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background='var(--adm-soft)'; e.currentTarget.style.transform='translateX(2px)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; }}
                            >
                                <span style={{ display:'grid', gap:1, placeItems:'center', width:48, height:48, flex:'0 0 auto', borderRadius:12, background:tone.background, color:tone.color }}>
                                    <b style={{ fontSize:16, lineHeight:1 }}>{event.day ?? '–'}</b>
                                    <small style={{ fontSize:9, fontWeight:800, textTransform:'uppercase', letterSpacing:'.04em' }}>{event.month}</small>
                                </span>
                                <span style={{ minWidth:0 }}>
                                    <b style={{ display:'block', color:'var(--adm-heading)', lineHeight:1.35, fontSize:12.5, ...clampLines(2) }}>{event.title}</b>
                                    <small style={{ display:'inline-flex', alignItems:'center', gap:4, marginTop:3, color:'var(--adm-muted)', fontSize:11 }}><MapPin size={11} />{event.location ?? 'Lokasi menyusul'}</small>
                                </span>
                            </Link>;
                        })}
                        {latestEvents.length === 0 && <p style={{ margin:0, color:'var(--adm-muted)', fontSize:12, textAlign:'center', padding:'8px 0' }}>Belum ada agenda mendatang lainnya.</p>}
                    </div>
                </section>
            </aside>

            {/* Main Content */}
            <section className="adm-stack">
                <div className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title">{tab === 'mendatang' ? 'Daftar Agenda Mendatang' : 'Arsip Agenda Lampau'}</h2>
                        <span className="adm-badge">{visibleEvents.length} Kegiatan</span>
                    </div>
                    <div className="adm-card-body">
                        {pagedEvents.length === 0 ? <EmptyState
                            icon={CalendarX2}
                            title={tab === 'mendatang' ? 'Belum Ada Agenda Mendatang' : 'Arsip Agenda Masih Kosong'}
                            description="Tidak ada kegiatan yang cocok dengan kata kunci pencarian, kategori, atau filter bulan yang sedang dipilih."
                            action={<button type="button" className="adm-btn primary sm" onClick={resetFilters}><RotateCcw size={13} /> Reset Semua Filter</button>}
                        /> : <>
                            <div className="adm-grid adm-cols-2">
                                {pagedEvents.map((event) => (
                                    <Link href={`/agenda/${event.slug}`} key={event.id} style={{
                                        display:'flex', flexDirection:'column', gap:10, textDecoration:'none', color:'inherit',
                                        padding:'18px 20px', border:'1px solid var(--adm-line)', borderRadius:14,
                                        background:'var(--adm-card)', boxShadow:'0 1px 3px rgba(20,39,63,.04)',
                                        transition:'transform .2s, box-shadow .2s, border-color .2s',
                                    }}
                                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 8px 22px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(59,130,246,.3)'; }}
                                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 1px 3px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                                    >
                                        <span style={{ display:'flex', gap:13, alignItems:'flex-start' }}>
                                            <span style={{ display:'grid', gap:1, placeItems:'center', width:54, flex:'0 0 auto', padding:'9px 4px', borderRadius:12, background:event.isToday ? 'var(--adm-green-soft)' : 'var(--adm-brand-soft)', color:event.isToday ? 'var(--adm-green)' : 'var(--adm-brand)' }}>
                                                <b>{event.day ?? '--'}</b>
                                                <small style={{ fontWeight:800, textTransform:'uppercase' }}>{event.month}</small>
                                            </span>
                                            <span style={{ minWidth:0, flex:1 }}>
                                                <span style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
                                                    {event.categoryName !== null && <span className="adm-badge">{event.categoryName}</span>}
                                                    {event.isToday && <span className="adm-badge green"><CalendarCheck size={12} /> Hari Ini</span>}
                                                </span>
                                                <b style={{ display:'block', marginTop:6, color:'var(--adm-heading)', lineHeight:1.4, ...clampLines(2) }}>{event.title}</b>
                                            </span>
                                        </span>
                                        {event.excerpt !== '' && <small style={{ color:'var(--adm-muted)', fontSize:12.5, lineHeight:1.6, ...clampLines(2) }}>{event.excerpt}</small>}
                                        <span style={{ marginTop:'auto', paddingTop:10, borderTop:'1px dashed var(--adm-line)', display:'flex', flexWrap:'wrap', gap:12, color:'var(--adm-muted)', fontSize:12.5 }}>
                                            {event.time !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><Clock3 size={12} />{event.time} WIB</span>}
                                            {event.location !== null && <span style={{ display:'inline-flex', alignItems:'center', gap:5, minWidth:0 }}><MapPin size={12} />{shortLocation(event.location)}</span>}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                            {pagination}
                        </>}
                    </div>
                </div>
            </section>
        </div>
    </PublicationShell>;
}
