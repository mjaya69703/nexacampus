// Detail pengumuman publik — isi HTML penuh, pratinjau lampiran, bagikan.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, Copy, Download, FileText, Megaphone, Paperclip, Pin } from 'lucide-react';
import { useRef, useState } from 'react';
import { PublicationBaseProps, PublicationShell } from '../../../components/Home/Publication/PublicationShell';

type AnnouncementDetail = {
    id: number; title: string; content: string; priority: string; priorityLabel: string;
    isPinned: boolean; publishedAt: string | null; hasAttachment: boolean;
    attachmentUrl: string | null; attachmentType: string | null; attachmentName: string | null;
};
type RelatedItem = { id: number; title: string; publishedAt: string | null };

type Props = PublicationBaseProps & { announcement: AnnouncementDetail; related: RelatedItem[]; shareUrl: string };

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'];

const plainText = (html: string): string => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

export default function PengumumanShow({ campus, links, user, announcement, related, shareUrl }: Props) {
    const [copied, setCopied] = useState(false);
    const [imageBroken, setImageBroken] = useState(false);
    const copyTimer = useRef<number | null>(null);

    const copyShareUrl = (): void => {
        navigator.clipboard.writeText(shareUrl).then(() => {
            setCopied(true);
            if (copyTimer.current !== null) window.clearTimeout(copyTimer.current);
            copyTimer.current = window.setTimeout(() => setCopied(false), 2000);
        }).catch(() => undefined);
    };

    const ext = announcement.attachmentName?.split('.').pop()?.toLowerCase()
        ?? announcement.attachmentType?.toLowerCase();
    const isImage = !!ext && IMAGE_EXTENSIONS.includes(ext);
    const description = plainText(announcement.content).slice(0, 160);

    return <PublicationShell campus={campus} links={links} user={user} activeTab="Pengumuman" eyebrow="Detail Pengumuman" title="Baca pengumuman resmi selengkapnya." description="Isi lengkap beserta lampiran resmi dari humas kampus." icon={Megaphone}>
        <Head title={`${announcement.title} · ${campus.name}`}>
            <meta name="description" content={description} />
            <meta property="og:title" content={announcement.title} />
            <meta property="og:description" content={description} />
        </Head>

        <Link href="/pengumuman" className="adm-btn ghost sm" style={{ justifySelf:'start' }}>
            <ArrowLeft size={14} /> Kembali ke Pengumuman
        </Link>

        <div className="adm-layout">
            <section className="adm-stack">
                <article className="adm-card" style={{ overflow:'hidden' }}>
                    <div style={{ padding:'28px 30px 0' }}>
                        <div style={{ display:'flex', flexWrap:'wrap', gap:8, alignItems:'center' }}>
                            <span className={announcement.priority === 'urgent' ? 'adm-badge red' : announcement.priority === 'important' ? 'adm-badge' : 'adm-badge gray'}>
                                Prioritas {announcement.priorityLabel}
                            </span>
                            {announcement.isPinned && <span className="adm-badge red"><Pin size={12} /> Disematkan</span>}
                            <span style={{
                                marginLeft:'auto', display:'inline-flex', alignItems:'center', gap:5,
                                padding:'4px 11px', borderRadius:999, fontSize:10.5, fontWeight:700,
                                color:'var(--adm-gold)', background:'var(--adm-gold-soft)',
                            }}>
                                <CalendarDays size={11} />{announcement.publishedAt ?? 'Tanpa Tanggal'}
                            </span>
                        </div>
                        <h1 style={{ margin:'14px 0 0', color:'var(--adm-heading)', font:"700 clamp(20px, 2.8vw, 28px)/1.3 'Source Serif 4', Georgia, serif" }}>
                            {announcement.title}
                        </h1>
                    </div>

                    {announcement.hasAttachment && announcement.attachmentUrl && isImage && !imageBroken && (
                        <figure style={{ margin:'20px 30px 0', borderRadius:14, overflow:'hidden', border:'1px solid var(--adm-line)' }}>
                            <img src={announcement.attachmentUrl} alt={announcement.attachmentName ?? announcement.title}
                                loading="lazy" onError={() => setImageBroken(true)}
                                style={{ display:'block', width:'100%', maxHeight:420, objectFit:'cover' }} />
                        </figure>
                    )}

                    <div style={{
                        padding:'22px 30px 28px', color:'var(--adm-ink)', fontSize:15, lineHeight:1.85,
                    }} dangerouslySetInnerHTML={{ __html: announcement.content }} />

                    {announcement.hasAttachment && announcement.attachmentUrl && (
                        <div style={{
                            margin:'0 30px 24px', padding:'14px 16px', border:'1px dashed var(--adm-line)',
                            borderRadius:12, background:'var(--adm-soft)', display:'flex', flexWrap:'wrap',
                            alignItems:'center', gap:10,
                        }}>
                            {isImage && imageBroken
                                ? <FileText size={16} style={{ color:'var(--adm-muted)' }} />
                                : <Paperclip size={16} style={{ color:'var(--adm-brand)' }} />}
                            <span style={{ flex:1, minWidth:180, color:'var(--adm-heading)', fontSize:13, fontWeight:700 }}>
                                {announcement.attachmentName ?? 'Lampiran'}
                            </span>
                            <a className="adm-btn primary sm" href={announcement.attachmentUrl} download>
                                <Download size={13} /> Unduh Lampiran
                            </a>
                        </div>
                    )}

                    <div style={{
                        padding:'18px 30px', borderTop:'1px solid var(--adm-line)', background:'var(--adm-soft)',
                        display:'flex', flexWrap:'wrap', alignItems:'center', gap:10,
                    }}>
                        <button type="button" style={{
                            display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
                            border:'1px solid var(--adm-line)', borderRadius:10, background:'var(--adm-card)',
                            color:'var(--adm-brand)', cursor:'pointer', fontSize:12, fontWeight:700,
                        }} onClick={copyShareUrl}>
                            {copied ? <Check size={13} /> : <Copy size={13} />} {copied ? 'Tersalin!' : 'Salin Tautan'}
                        </button>
                    </div>
                </article>

                {related.length > 0 && <section className="adm-card">
                    <div className="adm-card-head">
                        <h2 className="adm-card-title"><Megaphone size={16} /> Pengumuman Lainnya</h2>
                        <span className="adm-badge gray">{related.length} Info</span>
                    </div>
                    <div className="adm-card-body">
                        <div style={{ display:'grid', gap:8 }}>
                            {related.map((item) => (
                                <Link key={item.id} href={`/pengumuman/${item.id}`} style={{
                                    display:'flex', alignItems:'center', gap:10, padding:'10px 12px',
                                    borderRadius:12, textDecoration:'none', color:'inherit',
                                }}
                                onMouseEnter={(e) => { e.currentTarget.style.background='var(--adm-soft)'; }}
                                onMouseLeave={(e) => { e.currentTarget.style.background=''; }}
                                >
                                    <span style={{ flex:1, minWidth:0 }}>
                                        <b style={{ display:'block', color:'var(--adm-heading)', fontSize:13, lineHeight:1.4 }}>{item.title}</b>
                                        {item.publishedAt && <small className="adm-hint">{item.publishedAt}</small>}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>}
            </section>

            <aside className="adm-stack">
                <section className="adm-card" style={{ padding:0 }}>
                    <div style={{ padding:'16px 20px', borderBottom:'1px solid var(--adm-line)', background:'var(--adm-soft)' }}>
                        <h2 style={{ margin:0, color:'var(--adm-heading)', fontSize:13, fontWeight:800, textTransform:'uppercase', letterSpacing:'.06em' }}>
                            Kanal Resmi
                        </h2>
                    </div>
                    <div style={{ padding:'16px 20px', display:'grid', gap:8 }}>
                        <Link className="adm-btn outline sm" href="/pengumuman" style={{ justifyContent:'center' }}>
                            Semua Pengumuman
                        </Link>
                        <Link className="adm-btn ghost sm" href="/agenda" style={{ justifyContent:'center' }}>
                            <CalendarDays size={13} /> Agenda Kampus
                        </Link>
                    </div>
                </section>
            </aside>
        </div>
    </PublicationShell>;
}
