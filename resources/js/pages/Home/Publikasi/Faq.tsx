// Public FAQ page (Inertia migration of the Livewire Volt faq component).
import { Head, Link } from '@inertiajs/react';
import { ChevronDown, FolderOpen, GraduationCap, HelpCircle, Inbox, Search, Send, ShieldCheck, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type FaqItem = { id: number; question: string; answer: string; category: string; type: string };
type Props = PublicationBaseProps & { faqs: FaqItem[]; types: Array<{ value: string; label: string }>; typeCounts: Record<string, number> };

type Tone = '' | 'green' | 'amber' | 'gray';

const typeTones: Record<string, Tone> = {
    admission: '', academic: 'green', finance: 'amber', financial: 'amber', service: 'gray', services: 'gray',
};
const toneForType = (value: string): Tone => typeTones[value] ?? '';
const plainText = (html: string): string => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
const matchesTerm = (item: FaqItem, term: string): boolean =>
    term === '' || `${item.question} ${plainText(item.answer)} ${item.category}`.toLowerCase().includes(term);

export default function Faq({ campus, links, user, faqs, types, typeCounts }: Props) {
    const [query, setQuery] = useState('');
    const [selectedType, setSelectedType] = useState('all');
    const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
    const [openIds, setOpenIds] = useState<Set<number>>(() => {
        const seen = new Set<string>();
        const initial = new Set<number>();
        for (const item of faqs) {
            if (!seen.has(item.category)) { seen.add(item.category); initial.add(item.id); }
        }
        return initial;
    });

    const term = query.trim().toLowerCase();
    const totalFaqs = typeCounts.all ?? faqs.length;
    const categoryTotal = new Set(faqs.map((item) => item.category)).size;

    const typeFiltered = useMemo(() => (selectedType === 'all' ? faqs : faqs.filter((item) => item.type === selectedType)), [faqs, selectedType]);
    const categories = useMemo(() => Array.from(new Set(typeFiltered.map((item) => item.category))).sort((a, b) => a.localeCompare(b)), [typeFiltered]);
    const groups = useMemo(() => categories
        .map((category) => ({ category, items: typeFiltered.filter((item) => item.category === category && matchesTerm(item, term)) }))
        .filter((group) => group.items.length > 0),
    [categories, term, typeFiltered]);
    const resultCount = groups.reduce((sum, group) => sum + group.items.length, 0);
    const hasNarrowFilters = term !== '' || selectedCategory !== null;

    const toggleItem = (id: number) => setOpenIds((current) => {
        const next = new Set(current);
        if (next.has(id)) next.delete(id); else next.add(id);
        return next;
    });
    const pickType = (value: string) => { setSelectedType(value); setSelectedCategory(null); };
    const resetNarrow = () => { setQuery(''); setSelectedCategory(null); };
    const resetAll = () => { setQuery(''); setSelectedType('all'); setSelectedCategory(null); };

    return <PublicationShell campus={campus} links={links} user={user} activeTab="FAQ" eyebrow="FAQ" title="Pusat bantuan & pertanyaan umum." description="Temukan jawaban resmi seputar PMB, akademik, keuangan, dan layanan mahasiswa." icon={HelpCircle}
        action={<div className="adm-hero-cta">
            <Link className="adm-btn light" href={links.contact}><Send size={15} /> Hubungi Kami</Link>
            <Link className="adm-btn ghost" href={links.admission}><GraduationCap size={14} /> Pendaftaran</Link>
        </div>}>
        <Head title={`FAQ · ${campus.name}`} />

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
                        Pusat Bantuan {campus.name}
                    </span>
                    <h2 style={{ margin:'0 0 8px', color:'#fff', font:"700 clamp(20px, 2.8vw, 28px)/1.2 'Source Serif 4', Georgia, serif", letterSpacing:'-.01em' }}>
                        Temukan jawaban dalam hitungan detik.
                    </h2>
                    <p style={{ margin:0, color:'#c3d4e6', fontSize:13.5, lineHeight:1.7 }}>
                        Ketik kata kunci seperti "pendaftaran", "UKT", atau "KRS" — lalu saring berdasarkan modul dan kategori untuk mempersempit hasil.
                    </p>
                </div>
                <div style={{ display:'flex', gap:10, flexWrap:'wrap' }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'#fff', background:'rgba(255,255,255,.18)', border:'1px solid rgba(255,255,255,.28)' }}>
                        <ShieldCheck size={12} /> Jawaban Resmi
                    </span>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, padding:'8px 16px', borderRadius:999, fontSize:11, fontWeight:700, color:'rgba(255,255,255,.8)', background:'rgba(255,255,255,.08)', border:'1px solid rgba(255,255,255,.18)' }}>
                        <Inbox size={12} /> Diperbarui Berkala
                    </span>
                </div>
            </div>
        </section>

        {/* ── Stats Inline ─────────────────────────────────────────── */}
        <div className="adm-stats" style={{ marginBottom: 24 }}>
            {[
                { icon: <HelpCircle size={18} />, num: totalFaqs, label: 'Total FAQ', tone: '' },
                { icon: <FolderOpen size={18} />, num: categoryTotal, label: 'Kategori Bahasan', tone: 'green' },
                { icon: <Inbox size={18} />, num: types.length, label: 'Modul Layanan', tone: 'gold' },
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

        {/* ── Search & Filter Card ─────────────────────────────────── */}
        <section className="adm-card" style={{ padding:0, marginBottom:24 }}>
            <div style={{ padding:'16px 22px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)', display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                    <Search size={14} style={{ color:'var(--adm-brand)' }} />
                    <span style={{ color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>Cari &amp; Saring</span>
                </div>
                <span className="adm-badge">{resultCount} Hasil</span>
            </div>
            <div style={{ padding:'18px 22px', display:'grid', gap:14 }}>
                <div style={{ position:'relative' }}>
                    <span style={{ position:'absolute', top:'50%', left:13, transform:'translateY(-50%)', display:'inline-flex', color:'var(--adm-muted)', pointerEvents:'none' }}><Search size={15} /></span>
                    <input id="faq-search" className="adm-input" style={{ paddingLeft:38, paddingRight:40 }} value={query}
                        onChange={(event) => setQuery(event.target.value)} placeholder="Ketik kata kunci pertanyaan…" aria-label="Cari pertanyaan" />
                    {query !== '' && <button type="button" onClick={() => setQuery('')} aria-label="Bersihkan pencarian"
                        style={{ position:'absolute', top:'50%', right:10, transform:'translateY(-50%)', display:'inline-flex', alignItems:'center', justifyContent:'center', width:26, height:26, border:0, borderRadius:'50%', background:'transparent', color:'var(--adm-muted)', cursor:'pointer' }}><X size={15} /></button>}
                </div>
                <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:8 }}>
                    <button type="button" className={selectedType === 'all' ? 'adm-chip active' : 'adm-chip'} onClick={() => pickType('all')}>Semua Modul<b>{totalFaqs}</b></button>
                    {types.map(({ value, label }) => (
                        <button key={value} type="button" className={selectedType === value ? 'adm-chip active' : 'adm-chip'} onClick={() => pickType(value)}>{label}<b>{typeCounts[value] ?? 0}</b></button>
                    ))}
                </div>
                {categories.length > 0 && <div style={{ display:'flex', flexWrap:'wrap', alignItems:'center', gap:8 }}>
                    <span style={{ display:'inline-flex', alignItems:'center', gap:6, color:'var(--adm-brand)', fontSize:10.5, fontWeight:800, textTransform:'uppercase', letterSpacing:'.1em' }}><FolderOpen size={11} /> Kategori</span>
                    {categories.map((category) => (
                        <button key={category} type="button" className={selectedCategory === category ? 'adm-chip active' : 'adm-chip'} onClick={() => setSelectedCategory(selectedCategory === category ? null : category)}>{category}</button>
                    ))}
                    {hasNarrowFilters && <button type="button" className="adm-btn ghost sm" onClick={resetNarrow}><X size={13} /> Reset Filter</button>}
                </div>}
            </div>
        </section>

        {/* ── FAQ Groups ───────────────────────────────────────────── */}
        {groups.length === 0 ? <EmptyState
            icon={Search}
            title="FAQ Tidak Ditemukan"
            description="Tidak ada pertanyaan yang cocok dengan kata kunci atau kombinasi filter saat ini. Coba ubah pencarian atau reset seluruh filter."
            action={<button type="button" className="adm-btn primary" onClick={resetAll}><X size={14} /> Reset Semua Filter</button>}
        /> : groups.map(({ category, items }) => <section className="adm-card" key={category} style={{ marginBottom:14 }}>
            <div className="adm-card-head">
                <div style={{ display:'flex', alignItems:'center', gap:10 }}>
                    <span style={{ display:'grid', placeItems:'center', width:30, height:30, borderRadius:9, color:'var(--adm-brand)', background:'var(--adm-brand-soft)' }}><FolderOpen size={14} /></span>
                    <h2 className="adm-card-title">{category}</h2>
                </div>
                <span className="adm-badge">{items.length} Pertanyaan</span>
            </div>
            <div className="adm-card-body tight">
                <div className="adm-faq">
                    {items.map((item) => {
                        const open = openIds.has(item.id);
                        const tone = toneForType(item.type);
                        const typeLabel = types.find((option) => option.value === item.type)?.label ?? item.type;
                        return <div key={item.id} className={open ? 'adm-faq-item open' : 'adm-faq-item'}>
                            <button type="button" className="adm-faq-q" onClick={() => toggleItem(item.id)} aria-expanded={open}>
                                <span className={tone ? `adm-badge ${tone}` : 'adm-badge'}>{typeLabel}</span>
                                <span style={{ flex:1 }}>{item.question}</span>
                                <ChevronDown size={16} />
                            </button>
                            <div className="adm-faq-a">
                                <div dangerouslySetInnerHTML={{ __html: item.answer }} />
                            </div>
                        </div>;
                    })}
                </div>
            </div>
        </section>)}

        {/* ── CTA Footer ───────────────────────────────────────────── */}
        <section style={{
            position:'relative', overflow:'hidden', borderRadius:20, color:'#fff', marginTop:28,
            background:'linear-gradient(118deg, var(--adm-brand-deep) 0%, #1d4d82 55%, #2b69ab 100%)',
            padding:'40px 38px', textAlign:'center',
        }}>
            <div style={{ position:'absolute', top:'-40%', left:'50%', transform:'translateX(-50%)', width:420, height:420, borderRadius:'50%', background:'radial-gradient(circle, rgba(212,167,96,.22) 0%, transparent 65%)', pointerEvents:'none' }} />
            <div style={{ position:'relative', zIndex:2, maxWidth:580, margin:'0 auto' }}>
                <span style={{ display:'inline-flex', alignItems:'center', gap:8, marginBottom:10, color:'rgba(255,255,255,.85)', fontSize:11, fontWeight:800, letterSpacing:'.1em', textTransform:'uppercase' }}>
                    <Send size={12} /> Masih Butuh Bantuan?
                </span>
                <h2 style={{ margin:'8px 0 8px', color:'#fff', font:"700 clamp(20px, 2.5vw, 26px)/1.2 'Source Serif 4', Georgia, serif" }}>
                    Masih memiliki pertanyaan lain?
                </h2>
                <p style={{ margin:'0 0 20px', color:'rgba(255,255,255,.75)', fontSize:13, lineHeight:1.7 }}>
                    Tim layanan dan akademisi NexaCampus siap membantu Anda — hubungi kami atau langsung daftar.
                </p>
                <div style={{ display:'flex', justifyContent:'center', flexWrap:'wrap', gap:10 }}>
                    <Link className="adm-btn light" href={links.contact}><Send size={14} /> Hubungi Kami</Link>
                    <Link className="adm-btn outline" href={links.admission}><GraduationCap size={14} /> Daftar PMB</Link>
                </div>
            </div>
        </section>
    </PublicationShell>;
}
