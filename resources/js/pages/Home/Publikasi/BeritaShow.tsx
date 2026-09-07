// Detail berita publik — badan artikel HTML mentah, toolbar bagikan, dan grid terkait.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, AtSign, CalendarDays, Check, CircleUser, Clock3, Copy, FolderOpen, Globe, MessageCircle, Newspaper, Share2 } from 'lucide-react';
import { CSSProperties, useRef, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';

type ArticleDetail = { title:string; content:string; categoryName:string|null; author:string; publishedAt:string|null; readingTime:number; image:string|null; shareUrl:string };
type RelatedNews = { title:string; slug:string; image:string|null; excerpt:string };
type LatestItem = { title:string; slug:string; day:string|null; month:string|null; date:string|null };
type CategoryItem = { name:string; slug:string; count:number };

type Props = PublicationBaseProps & { article:ArticleDetail; related:RelatedNews[]; latest:LatestItem[]; categories:CategoryItem[] };

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

export default function BeritaShow({ campus, links, user, article, related, latest, categories }: Props) {
    const [copied, setCopied] = useState(false);
    const copyTimer = useRef<number | null>(null);

    const copyShareUrl = (): void => {
        navigator.clipboard.writeText(article.shareUrl).then(() => {
            setCopied(true);
            if (copyTimer.current !== null) window.clearTimeout(copyTimer.current);
            copyTimer.current = window.setTimeout(() => setCopied(false), 2000);
        }).catch(() => undefined);
    };

    const whatsappUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(`${article.title} — ${article.shareUrl}`)}`;
    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(article.shareUrl)}`;
    const xUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(article.title)}&url=${encodeURIComponent(article.shareUrl)}`;
    const initials = article.author.split(' ').slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Berita" eyebrow="Detail Warta" title="Baca kabar lengkap dari humas kampus." description="Artikel resmi beserta tautan berbagi, artikel terkait, dan arsip terbaru dalam satu halaman." icon={Newspaper}>
        <Head title={`${article.title} · ${campus.name}`} />

        <Link href="/berita" className="adm-btn ghost sm" style={{ justifySelf:'start' }}>
            <ArrowLeft size={14} /> Kembali ke Berita Kampus
        </Link>

        <div className="adm-layout">
            <section className="adm-stack">
                {/* ── Article Card ──────────────────────────────────── */}
                <article className="adm-card" style={{ overflow:'hidden' }}>
                    <div style={{ padding:'28px 30px 0' }}>
                        {/* Category & badge */}
                        <div style={{ display:'flex', flexWrap:'wrap', gap:8, alignItems:'center' }}>
                            {article.categoryName !== null && (
                                <span style={{
                                    display:'inline-flex', alignItems:'center', padding:'4px 12px',
                                    borderRadius:999, fontSize:10.5, fontWeight:800, textTransform:'uppercase',
                                    letterSpacing:'.05em', background:'rgba(37,99,174,.08)', color:'var(--adm-brand)',
                                    border:'1px solid rgba(37,99,174,.15)',
                                }}>
                                    {article.categoryName}
                                </span>
                            )}
                            <span style={{
                                display:'inline-flex', alignItems:'center', gap:5, padding:'4px 11px',
                                borderRadius:999, fontSize:10.5, fontWeight:700,
                                color:'var(--adm-muted)', background:'var(--adm-soft)',
                            }}>
                                <Newspaper size={11} /> Warta Resmi Kampus
                            </span>
                        </div>

                        {/* Title */}
                        <h1 style={{
                            margin:'16px 0 0', lineHeight:1.3,
                            color:'var(--adm-heading)',
                            font:"700 clamp(20px, 2.8vw, 28px)/1.3 'Source Serif 4', Georgia, serif",
                            letterSpacing:'-.02em',
                        }}>
                            {article.title}
                        </h1>

                        {/* Author & meta */}
                        <div style={{
                            display:'flex', flexWrap:'wrap', gap:14, alignItems:'center',
                            marginTop:18, paddingTop:16, borderTop:'1px solid var(--adm-line)',
                        }}>
                            <span style={{
                                display:'grid', placeItems:'center', width:42, height:42, borderRadius:'50%',
                                background:'var(--adm-brand-soft)', color:'var(--adm-brand)', fontWeight:800,
                                fontSize:14,
                            }}>
                                {initials === '' ? <CircleUser size={20} /> : initials}
                            </span>
                            <span>
                                <b style={{ display:'block', color:'var(--adm-heading)', fontSize:13.5 }}>{article.author}</b>
                                <small style={{ color:'var(--adm-muted)', fontSize:11.5 }}>Redaksi Informasi Publik</small>
                            </span>
                            <span style={{
                                marginLeft:'auto', display:'inline-flex', alignItems:'center', gap:5,
                                padding:'4px 11px', borderRadius:999, fontSize:10.5, fontWeight:700,
                                color:'var(--adm-gold)', background:'var(--adm-gold-soft)',
                            }}>
                                <CalendarDays size={11} />{article.publishedAt ?? 'Tanpa Tanggal'}
                            </span>
                            <span style={{
                                display:'inline-flex', alignItems:'center', gap:5, padding:'4px 11px',
                                borderRadius:999, fontSize:10.5, fontWeight:700,
                                color:'var(--adm-brand)', background:'var(--adm-brand-soft)',
                            }}>
                                <Clock3 size={11} />{article.readingTime} Menit Baca
                            </span>
                        </div>
                    </div>

                    {/* Featured image */}
                    {article.image !== null && (
                        <figure style={{ margin:'20px 30px 0', borderRadius:14, overflow:'hidden', border:'1px solid var(--adm-line)' }}>
                            <img src={article.image} alt={article.title} style={{ display:'block', width:'100%', maxHeight:400, objectFit:'cover' }} />
                        </figure>
                    )}

                    {/* Content body */}
                    <div style={{
                        padding:'22px 30px 28px', color:'var(--adm-ink)', fontSize:15, lineHeight:1.85,
                    }} dangerouslySetInnerHTML={{ __html: article.content }} />

                    {/* Share toolbar */}
                    <div style={{
                        padding:'18px 30px', borderTop:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', flexWrap:'wrap', alignItems:'center', gap:10,
                    }}>
                        <span style={{
                            display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-brand)',
                            fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.08em',
                        }}>
                            <Share2 size={12} /> Bagikan
                        </span>
                        <span style={{ display:'flex', flexWrap:'wrap', gap:8, marginLeft:6 }}>
                            <button type="button" style={{
                                display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
                                border:'1px solid var(--adm-line)', borderRadius:10, background:'var(--adm-card)',
                                color:'var(--adm-brand)', cursor:'pointer', fontSize:12, fontWeight:700,
                                transition:'border-color .15s, background .15s',
                            }} onClick={copyShareUrl}
                                onMouseEnter={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; }}
                            >
                                {copied ? <Check size={13} /> : <Copy size={13} />} {copied ? 'Tersalin!' : 'Salin Tautan'}
                            </button>
                            <a style={{
                                display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
                                border:'1px solid var(--adm-line)', borderRadius:10, background:'var(--adm-card)',
                                color:'var(--adm-brand)', fontSize:12, fontWeight:700, textDecoration:'none',
                                transition:'border-color .15s',
                            }} href={whatsappUrl} target="_blank" rel="noreferrer"
                                onMouseEnter={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; }}
                            >
                                <MessageCircle size={13} /> WhatsApp
                            </a>
                            <a style={{
                                display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
                                border:'1px solid var(--adm-line)', borderRadius:10, background:'var(--adm-card)',
                                color:'var(--adm-brand)', fontSize:12, fontWeight:700, textDecoration:'none',
                                transition:'border-color .15s',
                            }} href={facebookUrl} target="_blank" rel="noreferrer"
                                onMouseEnter={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; }}
                            >
                                <Globe size={13} /> Facebook
                            </a>
                            <a style={{
                                display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
                                border:'1px solid var(--adm-line)', borderRadius:10, background:'var(--adm-card)',
                                color:'var(--adm-brand)', fontSize:12, fontWeight:700, textDecoration:'none',
                                transition:'border-color .15s',
                            }} href={xUrl} target="_blank" rel="noreferrer"
                                onMouseEnter={(e) => { e.currentTarget.style.borderColor='var(--adm-brand)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.borderColor='var(--adm-line)'; }}
                            >
                                <AtSign size={13} /> X (Twitter)
                            </a>
                        </span>
                    </div>
                </article>

                {/* ── Related Articles ─────────────────────────────── */}
                {related.length > 0 && <section className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title"><Newspaper size={16} /> Berita Terkait</h2>
                        <span className="adm-badge gray">{related.length} Artikel</span>
                    </div>
                    <div className="adm-card-body">
                        <div className="adm-grid adm-cols-3">
                            {related.map((item) => {
                                const tone = categoryTones[Math.abs(item.slug.charCodeAt(0) + item.slug.charCodeAt(1)) % categoryTones.length];
                                return <Link href={`/berita/${item.slug}`} key={item.slug} style={{
                                    display:'flex', flexDirection:'column', textDecoration:'none', color:'inherit',
                                    border:'1px solid var(--adm-line)', borderRadius:12, overflow:'hidden',
                                    background:'var(--adm-card)', transition:'transform .2s, box-shadow .2s, border-color .2s',
                                }}
                                onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 8px 22px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(59,130,246,.3)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                                >
                                    <span style={{ display:'block', height:110, overflow:'hidden', background:'var(--adm-soft)' }}>
                                        {item.image === null
                                            ? <span style={{ display:'grid', placeItems:'center', height:'100%', color:'var(--adm-muted)' }}><Newspaper size={24} /></span>
                                            : <img src={item.image} alt={item.title} loading="lazy" style={{ width:'100%', height:'100%', objectFit:'cover', display:'block' }} />}
                                    </span>
                                    <span style={{ display:'flex', flexDirection:'column', gap:6, padding:'14px 16px', flex:1 }}>
                                        <b style={{ color:'var(--adm-heading)', lineHeight:1.4, fontSize:13.5, ...clampLines(2) }}>{item.title}</b>
                                        <small style={{ color:'var(--adm-muted)', fontSize:12, lineHeight:1.55, ...clampLines(2) }}>{item.excerpt}</small>
                                    </span>
                                </Link>;
                            })}
                        </div>
                    </div>
                </section>}
            </section>

            {/* ── Sidebar ───────────────────────────────────────────── */}
            <aside className="adm-stack">
                {/* Latest News */}
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={{
                        padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', alignItems:'center', gap:8,
                    }}>
                        <Clock3 size={14} style={{ color:'var(--adm-brand)' }} />
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>
                            Berita Terbaru
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
                        {latest.length === 0 && <p style={{ margin:0, color:'var(--adm-muted)', fontSize:12, textAlign:'center', padding:'8px 0' }}>Belum ada berita lain.</p>}
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
                        {categories.map((item) => (
                            <Link href="/berita" key={item.slug} className={item.name === article.categoryName ? 'adm-chip active' : 'adm-chip'}>
                                {item.name}<b>{item.count}</b>
                            </Link>
                        ))}
                    </div>
                </section>}
            </aside>
        </div>
    </PublicationShell>;
}
