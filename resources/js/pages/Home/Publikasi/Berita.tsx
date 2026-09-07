// Portal berita publik — daftar artikel dengan pencarian, urutan, filter kategori,
// dan paginasi yang seluruhnya dijalankan di sisi klien (migrasi Livewire Volt).
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Clock3, Flame, FolderOpen, Inbox, ListFilter, Newspaper, RotateCcw, Search, Sparkles } from 'lucide-react';
import { CSSProperties, useMemo, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { Pagination } from '../../../components/Shared/Pagination';

type NewsCard = { id:number; title:string; slug:string; excerpt:string; readingTime:number; publishedAt:string|null; day:string|null; month:string|null; image:string|null; categorySlug:string|null; categoryName:string|null };
type FeaturedNews = NewsCard & { lede:string };
type LatestItem = { title:string; slug:string; day:string|null; month:string|null; date:string|null };
type CategoryItem = { name:string; slug:string; count:number };
type SortChoice = 'terbaru' | 'terlama';

type Props = PublicationBaseProps & { featured:FeaturedNews|null; articles:NewsCard[]; categories:CategoryItem[]; latest:LatestItem[] };

const PAGE_SIZE = 9;

const clampLines = (lines:number): CSSProperties => ({ display:'-webkit-box', WebkitLineClamp:lines, WebkitBoxOrient:'vertical', overflow:'hidden' });

const tileTones = [
    { background:'var(--adm-brand-soft)', color:'var(--adm-brand)' },
    { background:'var(--adm-green-soft)', color:'var(--adm-green)' },
    { background:'var(--adm-gold-soft)', color:'var(--adm-gold)' },
];

const categoryTones = [
    { bg:'rgba(37,99,174,.08)', fg:'var(--adm-brand)' },
    { bg:'rgba(35,121,92,.08)', fg:'var(--adm-green)' },
    { bg:'rgba(184,134,47,.08)', fg:'var(--adm-gold)' },
    { bg:'rgba(179,64,47,.08)', fg:'var(--adm-red)' },
];

// publishedAt berformat 'd M Y' Indonesia ("05 Agu 2025") sehingga dibaca ulang
// menjadi angka yyyyMMdd agar urutan kronologis benar; tanpa tanggal selalu di bawah.
const monthIndex: Record<string, number> = { jan:1, feb:2, mar:3, apr:4, mei:5, jun:6, jul:7, agu:8, ags:8, sep:9, okt:10, nov:11, des:12 };
const publishedKey = (value:string): number => {
    const [dayText, monthText, yearText] = value.split(' ');
    return (Number(yearText) || 0) * 10000 + ((monthText !== undefined ? monthIndex[monthText.toLowerCase()] : undefined) ?? 0) * 100 + (Number(dayText) || 0);
};

export default function Berita({ campus, links, user, featured, articles, categories, latest }: Props) {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<SortChoice>('terbaru');
    const [category, setCategory] = useState('');
    const [page, setPage] = useState(1);

    const query = search.trim().toLowerCase();

    const filteredArticles = useMemo(() => {
        const matched = articles.filter((article) => {
            const hitQuery = query === '' || article.title.toLowerCase().includes(query) || article.excerpt.toLowerCase().includes(query);
            const hitCategory = category === '' || article.categorySlug === category;
            return hitQuery && hitCategory;
        });
        const dated = matched.filter((article) => article.publishedAt !== null);
        const undated = matched.filter((article) => article.publishedAt === null);
        dated.sort((first, second) => {
            const difference = publishedKey(first.publishedAt ?? '') - publishedKey(second.publishedAt ?? '');
            return sort === 'terbaru' ? -difference : difference;
        });
        return [...dated, ...undated];
    }, [articles, query, category, sort]);

    const totalPages = Math.max(1, Math.ceil(filteredArticles.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pagedArticles = filteredArticles.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE);
    const pristineFilters = query === '' && category === '' && sort === 'terbaru';
    const showFeatured = featured !== null && currentPage === 1 && pristineFilters;
    const totalArticles = articles.length + (featured === null ? 0 : 1);

    const resetFilters = (): void => { setSearch(''); setSort('terbaru'); setCategory(''); setPage(1); };

    const pagination = <Pagination page={currentPage} totalPages={totalPages} total={filteredArticles.length} unit="artikel" onPrev={() => setPage(currentPage - 1)} onNext={() => setPage(currentPage + 1)} />;

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Berita" eyebrow="Berita Kampus" title="Informasi & prestasi dalam satu warta." description="Ikuti kabar akademik, pencapaian mahasiswa, liputan riset, dan kegiatan institusi dari humas kampus." icon={Newspaper}
        action={<div className="adm-hero-cta"><Link className="adm-btn outline" href="/agenda"><CalendarDays size={14} /> Lihat Agenda</Link></div>}>
        <Head title={`Berita Kampus · ${campus.name}`} />

        {/* ── Hero Banner ─────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff',
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'36px 38px', marginBottom:28,
        }}>
            {/* decorative orbs */}
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
                        Portal Berita &amp; Kabar Kampus
                    </span>
                    <h1 style={{
                        margin:'0 0 10px', color:'#fff',
                        font:"700 clamp(22px, 3vw, 30px)/1.2 'Source Serif 4', Georgia, serif",
                        letterSpacing:'-.01em',
                    }}>
                        Informasi &amp; Prestasi{' '}
                        <span style={{ background:'linear-gradient(135deg, #60a5fa, #a78bfa)', WebkitBackgroundClip:'text', WebkitTextFillColor:'transparent' }}>
                            NexaCampus
                        </span>
                    </h1>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Setiap artikel terverifikasi tim redaksi dan tersusun rapi dari yang terbaru — saring lewat kata kunci atau kategori favoritmu.
                    </p>
                </div>

                <div style={{ display:'flex', gap:12 }}>
                    <div style={{
                        padding:'14px 22px', border:'1px solid rgba(255,255,255,.22)', borderRadius:14,
                        background:'rgba(255,255,255,.08)', textAlign:'center', backdropFilter:'blur(8px)',
                    }}>
                        <b style={{ display:'block', color:'#fff', fontSize:22, lineHeight:1 }}>{totalArticles}</b>
                        <small style={{ color:'rgba(255,255,255,.7)', fontSize:10, fontWeight:700, letterSpacing:'.06em', textTransform:'uppercase' }}>Artikel Terbit</small>
                    </div>
                    <div style={{
                        padding:'14px 22px', border:'1px solid rgba(255,255,255,.22)', borderRadius:14,
                        background:'rgba(255,255,255,.08)', textAlign:'center', backdropFilter:'blur(8px)',
                    }}>
                        <b style={{ display:'block', color:'#fff', fontSize:22, lineHeight:1 }}>{categories.length}</b>
                        <small style={{ color:'rgba(255,255,255,.7)', fontSize:10, fontWeight:700, letterSpacing:'.06em', textTransform:'uppercase' }}>Kategori Aktif</small>
                    </div>
                </div>
            </div>
        </section>

        <style>{`@keyframes pulse-green { 0%{box-shadow:0 0 0 0 rgba(74,222,128,.7)} 70%{box-shadow:0 0 0 8px rgba(74,222,128,0)} 100%{box-shadow:0 0 0 0 rgba(74,222,128,0)} }`}</style>

        <div className="adm-layout">
            <section className="adm-stack">
                {/* ── Featured Article ───────────────────────────────── */}
                {showFeatured && featured !== null && (
                    <Link href={`/berita/${featured.slug}`} style={{
                        display:'grid', gridTemplateColumns:'minmax(0, 1.2fr) minmax(260px, .8fr)',
                        overflow:'hidden', borderRadius:16, textDecoration:'none', color:'inherit',
                        border:'1px solid var(--adm-line)', background:'var(--adm-card)',
                        boxShadow:'0 2px 8px rgba(20,39,63,.04)',
                        transition:'transform .22s ease, box-shadow .22s ease, border-color .22s ease',
                    }}
                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-4px)'; e.currentTarget.style.boxShadow='0 14px 36px rgba(59,130,246,.1)'; e.currentTarget.style.borderColor='rgba(59,130,246,.35)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 2px 8px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                    >
                        <div style={{ padding:'28px 30px', display:'flex', flexDirection:'column', gap:10, justifyContent:'center' }}>
                            <span style={{
                                display:'inline-flex', alignItems:'center', gap:7, alignSelf:'flex-start',
                                color:'var(--adm-brand)', fontSize:10.5, fontWeight:800,
                                textTransform:'uppercase', letterSpacing:'.1em',
                            }}>
                                <Sparkles size={13} /> Sorotan Utama
                            </span>
                            {featured.categoryName !== null && (
                                <span style={{
                                    display:'inline-flex', alignItems:'center', alignSelf:'flex-start',
                                    padding:'4px 12px', borderRadius:999, fontSize:10.5, fontWeight:800,
                                    textTransform:'uppercase', letterSpacing:'.05em',
                                    background:'rgba(37,99,174,.08)', color:'var(--adm-brand)',
                                    border:'1px solid rgba(37,99,174,.15)',
                                }}>
                                    {featured.categoryName}
                                </span>
                            )}
                            <b style={{
                                color:'var(--adm-heading)', lineHeight:1.3,
                                font:"700 clamp(18px, 2.2vw, 24px)/1.3 'Source Serif 4', Georgia, serif",
                                letterSpacing:'-.015em',
                            }}>
                                {featured.title}
                            </b>
                            <span style={{ color:'var(--adm-muted)', fontSize:13.5, lineHeight:1.65, maxWidth:500 }}>
                                {featured.lede}
                            </span>
                            <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:14, marginTop:6, color:'var(--adm-muted)', fontSize:12.5 }}>
                                <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><CalendarDays size={13} />{featured.publishedAt ?? 'Tanpa Tanggal'}</span>
                                <span style={{ display:'inline-flex', alignItems:'center', gap:5 }}><Clock3 size={13} />{featured.readingTime} menit baca</span>
                                <span style={{
                                    marginLeft:'auto', display:'inline-flex', alignItems:'center', gap:6,
                                    padding:'8px 18px', borderRadius:999, fontSize:12, fontWeight:700,
                                    color:'#fff', background:'linear-gradient(135deg, var(--adm-brand), #3b7cc4)',
                                    boxShadow:'0 4px 12px rgba(37,99,174,.28)',
                                }}>
                                    Baca Selengkapnya <ArrowRight size={13} />
                                </span>
                            </div>
                        </div>
                        <div style={{
                            flex:'1 1 250px', minWidth:230, minHeight:240, position:'relative',
                            background:'var(--adm-soft)', overflow:'hidden',
                        }}>
                            {featured.image === null
                                ? <span style={{ position:'absolute', inset:0, display:'grid', placeItems:'center', color:'var(--adm-muted)' }}><Newspaper size={42} /></span>
                                : <img src={featured.image} alt={featured.title} loading="lazy" style={{ position:'absolute', inset:0, width:'100%', height:'100%', objectFit:'cover', transition:'transform .35s ease' }}
                                    onMouseEnter={(e) => { (e.target as HTMLImageElement).style.transform='scale(1.04)'; }}
                                    onMouseLeave={(e) => { (e.target as HTMLImageElement).style.transform=''; }}
                                />}
                        </div>
                    </Link>
                )}

                {/* ── Article Grid ──────────────────────────────────── */}
                <section className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title"><Newspaper size={16} /> Kabar Terkini</h2>
                        <span className="adm-badge">{filteredArticles.length} Artikel</span>
                    </div>
                    <div className="adm-card-body">
                        {pagedArticles.length === 0 ? <EmptyState
                            icon={Inbox}
                            title="Berita Tidak Ditemukan"
                            description="Tidak ada artikel yang cocok dengan kata kunci pencarian atau filter kategori yang sedang aktif."
                            action={<button type="button" className="adm-btn primary sm" onClick={resetFilters}><RotateCcw size={13} /> Reset Filter</button>}
                        /> : <>
                            <div className="adm-grid adm-cols-3">
                                {pagedArticles.map((article, idx) => {
                                    const tone = categoryTones[article.id % categoryTones.length];
                                    return <Link href={`/berita/${article.slug}`} key={article.id} style={{
                                        display:'flex', flexDirection:'column', textDecoration:'none', color:'inherit',
                                        border:'1px solid var(--adm-line)', borderRadius:14, overflow:'hidden',
                                        background:'var(--adm-card)', boxShadow:'0 1px 3px rgba(20,39,63,.04)',
                                        transition:'transform .2s ease, box-shadow .2s ease, border-color .2s ease',
                                    }}
                                    onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-4px)'; e.currentTarget.style.boxShadow='0 10px 28px rgba(0,0,0,.07)'; e.currentTarget.style.borderColor='rgba(59,130,246,.35)'; }}
                                    onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 1px 3px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                                    >
                                        <span style={{ display:'block', height:150, borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)', overflow:'hidden' }}>
                                            {article.image === null
                                                ? <span style={{ display:'grid', placeItems:'center', height:'100%', color:'var(--adm-muted)' }}><Newspaper size={30} /></span>
                                                : <img src={article.image} alt={article.title} loading="lazy" style={{ width:'100%', height:'100%', objectFit:'cover', display:'block', transition:'transform .35s ease' }}
                                                    onMouseEnter={(e) => { (e.target as HTMLImageElement).style.transform='scale(1.04)'; }}
                                                    onMouseLeave={(e) => { (e.target as HTMLImageElement).style.transform=''; }}
                                                />}
                                        </span>
                                        <span style={{ display:'flex', flexDirection:'column', gap:7, padding:'16px 18px', flex:1 }}>
                                            <span style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
                                                {article.categoryName !== null && (
                                                    <span style={{
                                                        display:'inline-flex', alignItems:'center', padding:'3px 10px',
                                                        borderRadius:999, fontSize:10, fontWeight:800, textTransform:'uppercase',
                                                        letterSpacing:'.04em', background:tone.bg, color:tone.fg,
                                                    }}>
                                                        {article.categoryName}
                                                    </span>
                                                )}
                                                <span style={{
                                                    display:'inline-flex', alignItems:'center', gap:4, padding:'3px 9px',
                                                    borderRadius:999, fontSize:10, fontWeight:700,
                                                    color:'var(--adm-muted)', background:'var(--adm-soft)',
                                                }}>
                                                    <Clock3 size={11} />{article.readingTime} mnt
                                                </span>
                                            </span>
                                            <b style={{
                                                color:'var(--adm-heading)', lineHeight:1.4,
                                                font:"700 14px/1.4 'Source Serif 4', Georgia, serif",
                                                ...clampLines(2),
                                            }}>
                                                {article.title}
                                            </b>
                                            <small style={{ color:'var(--adm-muted)', fontSize:12.5, lineHeight:1.6, ...clampLines(2) }}>
                                                {article.excerpt}
                                            </small>
                                            <span style={{
                                                marginTop:'auto', paddingTop:10,
                                                borderTop:'1px solid var(--adm-line)',
                                                display:'inline-flex', alignItems:'center', gap:5,
                                                color:'var(--adm-muted)', fontSize:12,
                                            }}>
                                                <CalendarDays size={12} /> {article.publishedAt ?? 'Belum terjadwal'}
                                            </span>
                                        </span>
                                    </Link>;
                                })}
                            </div>
                            {pagination}
                        </>}
                    </div>
                </section>
            </section>

            {/* ── Sidebar ───────────────────────────────────────────── */}
            <aside className="adm-stack">
                {/* Search & Sort */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={{
                        padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', alignItems:'center', gap:8,
                    }}>
                        <ListFilter size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>
                            Cari &amp; Urutkan
                        </h2>
                    </div>
                    <div style={{ padding:'18px 20px', display:'grid', gap:10 }}>
                        <span style={{ position:'relative', display:'block' }}>
                            <Search size={14} style={{ position:'absolute', top:'50%', left:12, transform:'translateY(-50%)', color:'var(--adm-muted)', pointerEvents:'none' }} />
                            <input type="search" style={{
                                width:'100%', padding:'10px 14px 10px 36px', border:'1.5px solid var(--adm-line)',
                                borderRadius:10, background:'var(--adm-card)', color:'var(--adm-ink)',
                                fontSize:13, outline:0, transition:'border-color .15s, box-shadow .15s',
                            }} placeholder="Cari judul atau isi berita…" value={search} aria-label="Cari berita"
                                onChange={(change) => { setSearch(change.target.value); setPage(1); }}
                                onFocus={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; e.currentTarget.style.boxShadow='0 0 0 3.5px rgba(37,99,174,.12)'; }}
                                onBlur={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; e.currentTarget.style.boxShadow=''; }}
                            />
                        </span>
                        <select style={{
                            width:'100%', padding:'10px 14px', border:'1.5px solid var(--adm-line)',
                            borderRadius:10, background:'var(--adm-card)', color:'var(--adm-ink)',
                            fontSize:13, outline:0, cursor:'pointer',
                        }} value={sort} aria-label="Urutkan berita"
                            onChange={(change) => { setSort(change.target.value === 'terlama' ? 'terlama' : 'terbaru'); setPage(1); }}>
                            <option value="terbaru">Terbaru Dulu</option>
                            <option value="terlama">Terlama Dulu</option>
                        </select>
                        {!pristineFilters && (
                            <button type="button" style={{
                                width:'100%', padding:'9px 14px', border:'1px solid var(--adm-line)', borderRadius:10,
                                background:'var(--adm-card)', color:'var(--adm-brand)', cursor:'pointer',
                                fontSize:12, fontWeight:700, display:'flex', alignItems:'center', justifyContent:'center', gap:6,
                                transition:'background .15s, border-color .15s',
                            }} onClick={resetFilters}
                                onMouseEnter={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; }}
                            >
                                <RotateCcw size={12} /> Reset Filter
                            </button>
                        )}
                    </div>
                </section>

                {/* Categories */}
                {categories.length > 0 && <section className="adm-card" style={{ padding:0 }}>
                    <div style={{
                        padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', alignItems:'center', justifyContent:'space-between',
                    }}>
                        <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                            <FolderOpen size={14} style={{ color:'var(--adm-brand)' }} />
                            <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>
                                Kategori
                            </h2>
                        </div>
                        <span className="adm-badge gray" style={{ fontSize:10 }}>{categories.length}</span>
                    </div>
                    <div style={{ padding:'16px 20px', display:'flex', flexWrap:'wrap', gap:8 }}>
                        <button type="button" className={category === '' ? 'adm-chip active' : 'adm-chip'} onClick={() => { setCategory(''); setPage(1); }}>
                            Semua<b>{articles.length}</b>
                        </button>
                        {categories.map((item) => (
                            <button type="button" key={item.slug} className={category === item.slug ? 'adm-chip active' : 'adm-chip'} onClick={() => { setCategory(item.slug); setPage(1); }}>
                                {item.name}<b>{item.count}</b>
                            </button>
                        ))}
                    </div>
                </section>}

                {/* Latest News */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={{
                        padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', alignItems:'center', gap:8,
                    }}>
                        <Flame size={14} style={{ color:'var(--adm-gold)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>
                            Berita Terkini
                        </h2>
                    </div>
                    <div style={{ padding:'14px 20px', display:'grid', gap:6 }}>
                        {latest.map((item, index) => {
                            const tone = tileTones[index % tileTones.length];
                            return <Link href={`/berita/${item.slug}`} key={item.slug} style={{
                                display:'grid', gridTemplateColumns:'48px minmax(0,1fr)', gap:12, alignItems:'center',
                                padding:'10px 12px', borderRadius:12, textDecoration:'none', color:'inherit',
                                transition:'background .15s, transform .15s',
                            }}
                            onMouseEnter={(e) => { e.currentTarget.style.background='var(--adm-soft)'; e.currentTarget.style.transform='translateX(2px)'; }}
                            onMouseLeave={(e) => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; }}
                            >
                                <span style={{
                                    display:'grid', gap:1, placeItems:'center', width:48, height:48, flex:'0 0 auto',
                                    borderRadius:12, background:tone.background, color:tone.color,
                                }}>
                                    <b style={{ fontSize:16, lineHeight:1 }}>{item.day ?? '–'}</b>
                                    <small style={{ fontSize:9, fontWeight:800, textTransform:'uppercase', letterSpacing:'.04em' }}>{item.month}</small>
                                </span>
                                <span style={{ minWidth:0 }}>
                                    <b style={{ display:'block', color:'var(--adm-heading)', lineHeight:1.35, fontSize:12.5, ...clampLines(2) }}>{item.title}</b>
                                    <small style={{ display:'inline-flex', alignItems:'center', gap:4, marginTop:3, color:'var(--adm-muted)', fontSize:11 }}>
                                        <CalendarDays size={11} />{item.date ?? '—'}
                                    </small>
                                </span>
                            </Link>;
                        })}
                        {latest.length === 0 && <p style={{ margin:0, color:'var(--adm-muted)', fontSize:12, textAlign:'center', padding:'8px 0' }}>Belum ada berita terbaru.</p>}
                    </div>
                </section>
            </aside>
        </div>
    </PublicationShell>;
}
