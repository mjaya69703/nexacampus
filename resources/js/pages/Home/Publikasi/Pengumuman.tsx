// Public announcements page — server pagination + priority/search filter,
// detail links and attachment downloads.
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, ChevronDown, Clock3, Download, FileText, HelpCircle, Hourglass, LucideIcon, Megaphone, Pin, Search, ShieldCheck, Star, X } from 'lucide-react';
import { useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { Pagination } from '../../../components/Shared/Pagination';

type Announcement = {
    id: number; title: string; content: string; priority: string; isPinned: boolean;
    publishedAt: string | null; hasAttachment: boolean; attachmentUrl: string | null; attachmentType: string | null;
};
type PageInfo = { currentPage: number; lastPage: number; perPage: number; total: number };
type Props = PublicationBaseProps & {
    total: number; pinned: Announcement[]; regulars: Announcement[];
    page: PageInfo; filters: { prioritas: string; q: string }; priorities: Array<{ value: string; label: string }>;
};

type Tone = '' | 'red' | 'amber' | 'gray';

const priorityMeta: Record<string, { label: string; tone: Tone; icon: LucideIcon }> = {
    urgent: { label: 'Mendesak', tone: 'red', icon: Hourglass },
    important: { label: 'Penting', tone: 'amber', icon: Star },
    normal: { label: 'Normal', tone: 'gray', icon: Clock3 },
    high: { label: 'Tinggi', tone: 'amber', icon: Star },
    low: { label: 'Rendah', tone: 'gray', icon: ChevronDown },
};
const priorityBadge = (priority: string): { label: string; tone: Tone; icon: LucideIcon } =>
    priorityMeta[priority] ?? { label: priority.replace(/_/g, ' '), tone: 'gray', icon: Clock3 };

export default function Pengumuman({ campus, links, user, total, pinned, regulars, page, filters, priorities }: Props) {
    const [search, setSearch] = useState(filters.q);
    const isEmpty = pinned.length === 0 && regulars.length === 0;
    const showPinned = pinned.length > 0;
    const attachmentCount = [...pinned, ...regulars].filter((item) => item.hasAttachment).length;

    const visit = (overrides: Record<string, string | number>) => {
        router.get('/pengumuman', { q: search, prioritas: filters.prioritas, ...overrides }, {
            preserveState: true,
            replace: true,
        });
    };

    const pickPriority = (value: string) => {
        router.get('/pengumuman', { q: search, prioritas: value, page: 1 }, { preserveState: true, replace: true });
    };

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/pengumuman', { q: search, prioritas: filters.prioritas, page: 1 }, { preserveState: true, replace: true });
    };

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Pengumuman" eyebrow="Pengumuman" title="Pengumuman resmi kampus." description="Kanal tunggal informasi resmi akademik, kemahasiswaan, dan administrasi NexaCampus." icon={Megaphone}
        action={<div className="adm-hero-cta">
            <Link className="adm-btn light" href="/agenda"><CalendarDays size={15} /> Agenda Kampus</Link>
            <Link className="adm-btn outline" href={links.faq}><HelpCircle size={14} /> Pusat Bantuan</Link>
        </div>}>
        <Head title={`Pengumuman · ${campus.name}`} />

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
                        Informasi Resmi {campus.name}
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Jangan sampai ada info penting yang terlewat.
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Seluruh pengumuman dipublikasikan melalui kanal resmi ini — lengkap dengan penanda prioritas dan status lampiran agar Anda mudah memilah yang mendesak.
                    </p>
                </div>
                <div style={{ display:'flex', gap:10, flexWrap:'wrap' }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'#fff', background:'rgba(255,255,255,.18)', border:'1px solid rgba(255,255,255,.28)' }}>
                        <ShieldCheck size={12} /> Kanal Resmi Humas
                    </span>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'rgba(255,255,255,.8)', background:'rgba(255,255,255,.08)', border:'1px solid rgba(255,255,255,.18)' }}>
                        <CalendarDays size={12} /> Diperbarui Berkala
                    </span>
                </div>
            </div>
        </section>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 24 }}>
            {[
                { icon: <Megaphone size={18} />, num: total, label: 'Total Pengumuman', tone: '' },
                { icon: <Pin size={18} />, num: pinned.length, label: 'Disematkan', tone: 'red' },
                { icon: <FileText size={18} />, num: attachmentCount, label: 'Memiliki Lampiran', tone: 'gold' },
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

        {!isEmpty && <>
            {/* Search + priority filter */}
            <form onSubmit={submitSearch} style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:8, marginBottom:14 }}>
                <span style={{ position:'relative', flex:'1 1 220px', maxWidth:340 }}>
                    <Search size={14} style={{ position:'absolute', left:12, top:'50%', transform:'translateY(-50%)', color:'var(--adm-muted)' }} />
                    <input className="adm-input" style={{ paddingLeft:34 }} value={search}
                        onChange={(e) => setSearch(e.target.value)} placeholder="Cari judul pengumuman…" aria-label="Cari pengumuman" />
                </span>
                <button type="submit" className="adm-btn primary sm">Cari</button>
                <span style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-brand)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.1em' }}><Star size={12} /> Filter Prioritas</span>
                <button type="button" className={filters.prioritas === '' ? 'adm-chip active' : 'adm-chip'} onClick={() => pickPriority('')}>Semua<b>{total}</b></button>
                {priorities.map((item) => {
                    const meta = priorityBadge(item.value);
                    return <button key={item.value} type="button" className={filters.prioritas === item.value ? 'adm-chip active' : 'adm-chip'} onClick={() => pickPriority(item.value)}>{meta.label}</button>;
                })}
                {(filters.prioritas !== '' || filters.q !== '') && <button type="button" className="adm-btn ghost sm" onClick={() => { setSearch(''); router.get('/pengumuman', {}, { preserveState: true, replace: true }); }}><X size={13} /> Reset</button>}
            </form>

            {/* Pinned announcements */}
            {showPinned && <section style={{ display:'grid', gap:12, marginBottom:20 }}>
                <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:10 }}>
                    <span style={{ display:'grid', placeItems:'center', width:34, height:34, borderRadius:10, color:'var(--adm-red)', background:'var(--adm-red-soft)' }}><Pin size={16} /></span>
                    <h2 className="adm-card-title">Disematkan</h2>
                    <hr className="adm-divider" style={{ flex:1, margin:0 }} />
                    <span className="adm-badge red">{pinned.length} Info</span>
                </div>
                {pinned.map((item) => {
                    const meta = priorityBadge(item.priority);
                    const Icon = meta.icon;
                    return <article key={item.id} className="adm-card" style={{ borderLeft:'4px solid var(--adm-red)' }}>
                        <div className="adm-card-head">
                            <span className="adm-kicker"><Icon size={13} /> Prioritas {meta.label}</span>
                            <span className="adm-badge red"><Pin size={12} /> Disematkan</span>
                        </div>
                        <div className="adm-card-body tight" style={{ display:'grid', gap:9 }}>
                            <Link href={`/pengumuman/${item.id}`} style={{ textDecoration:'none' }}>
                                <h3 className="adm-card-title">{item.title}</h3>
                            </Link>
                            <p className="adm-hint" style={{ margin:0 }}>{item.content}</p>
                            <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:8 }}>
                                {item.hasAttachment && item.attachmentUrl && (
                                    <a className="adm-badge" href={item.attachmentUrl} download style={{ textDecoration:'none' }}>
                                        <Download size={12} /> Unduh Lampiran
                                    </a>
                                )}
                                {item.publishedAt && <small className="adm-hint" style={{ display:'inline-flex', alignItems:'center', gap:5 }}><CalendarDays size={13} /> {item.publishedAt}</small>}
                            </div>
                        </div>
                    </article>;
                })}
            </section>}

            {/* Regular announcements */}
            {regulars.length > 0 && <section style={{ display:'grid', gap:12 }}>
                <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:10 }}>
                    <span style={{ display:'grid', placeItems:'center', width:34, height:34, borderRadius:10, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><Megaphone size={16} /></span>
                    <h2 className="adm-card-title">Pengumuman Terbaru</h2>
                    <hr className="adm-divider" style={{ flex:1, margin:0 }} />
                    <span className="adm-badge">{page.total} Ditampilkan</span>
                </div>
                <div className="adm-grid adm-cols-2">
                    {regulars.map((item) => {
                        const meta = priorityBadge(item.priority);
                        const Icon = meta.icon;
                        return <article key={item.id} style={{
                            display:'flex', flexDirection:'column', gap:9, padding:'18px 20px',
                            border:'1px solid var(--adm-line)', borderRadius:14, background:'var(--adm-card)',
                            boxShadow:'0 1px 3px rgba(20,39,63,.04)',
                            transition:'transform .2s, box-shadow .2s, border-color .2s',
                        }}
                        onMouseEnter={(e) => { e.currentTarget.style.transform='translateY(-3px)'; e.currentTarget.style.boxShadow='0 8px 22px rgba(0,0,0,.06)'; e.currentTarget.style.borderColor='rgba(59,130,246,.3)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow='0 1px 3px rgba(20,39,63,.04)'; e.currentTarget.style.borderColor='var(--adm-line)'; }}
                        >
                            <div style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
                                <span className={meta.tone ? `adm-badge ${meta.tone}` : 'adm-badge'}><Icon size={12} /> {meta.label}</span>
                                {item.hasAttachment && item.attachmentUrl && (
                                    <a className="adm-badge" href={item.attachmentUrl} download style={{ textDecoration:'none' }}>
                                        <Download size={12} /> Lampiran
                                    </a>
                                )}
                            </div>
                            <Link href={`/pengumuman/${item.id}`} style={{ textDecoration:'none' }}>
                                <b style={{ color:'var(--adm-heading)', lineHeight:1.45, fontSize:14 }}>{item.title}</b>
                            </Link>
                            <p className="adm-hint" style={{ margin:0 }}>{item.content}</p>
                            {item.publishedAt && <small className="adm-hint" style={{ display:'inline-flex', alignItems:'center', gap:5, marginTop:'auto', paddingTop:8, borderTop:'1px dashed var(--adm-line)' }}><CalendarDays size={13} /> {item.publishedAt}</small>}
                        </article>;
                    })}
                </div>
            </section>}

            <Pagination page={page.currentPage} totalPages={page.lastPage} total={page.total} unit="pengumuman"
                onPrev={() => visit({ page: page.currentPage - 1 })} onNext={() => visit({ page: page.currentPage + 1 })} />
        </>}

        {isEmpty && <EmptyState
            icon={Megaphone}
            title="Belum Ada Pengumuman"
            description="Pengumuman kampus akan tampil di halaman ini segera setelah dipublikasikan oleh humas institusi."
            action={<Link className="adm-btn primary" href="/agenda"><CalendarDays size={14} /> Lihat Agenda Kampus</Link>}
        />}
    </PublicationShell>;
}
