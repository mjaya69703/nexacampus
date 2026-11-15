// Public institutional homepage.
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    Award,
    BookOpen,
    CalendarDays,
    ChevronRight,
    CircleCheck,
    FileText,
    GraduationCap,
    Landmark,
    Megaphone,
    MoveUpRight,
    Newspaper,
    UsersRound,
} from 'lucide-react';
import { Campus, PublicLinks, PublicShell } from '../../components/Home/PublicShell';
import '../../../css/landing.css';

type Announcement = { id: number; title: string; excerpt: string; publishedAt: string; isPinned: boolean };
type CampusAgenda = { id: number; title: string; detail: string; date: string; month: string };
type CampusNews = { id: number; title: string; excerpt: string; publishedAt: string };
type Props = {
    campus: Campus;
    links: PublicLinks;
    stats: { studyPrograms: number; admissionOpen: boolean };
    announcements: Announcement[];
    agenda: CampusAgenda[];
    news: CampusNews[];
    user: { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
};

const visitorLinks = [
    { icon: GraduationCap, label: 'Calon mahasiswa', text: 'Kenali program studi dan temukan jalur masuk yang sesuai.', href: '/admission/apply', action: 'Mulai dari sini' },
    { icon: UsersRound, label: 'Mahasiswa', text: 'Akses kalender, jadwal, kurikulum, dan layanan akademik.', href: '/akademik/kalender', action: 'Lihat layanan' },
    { icon: Landmark, label: 'Masyarakat & mitra', text: 'Pelajari profil, kabar, kerja sama, dan kontribusi kampus.', href: '/institusi/profil', action: 'Kenali kampus' },
];

export default function Welcome({ campus, links, stats, announcements, agenda, news, user }: Props) {
    const featured = announcements[0];
    const stories = announcements.slice(1);

    return (
        <PublicShell campus={campus} links={links} user={user}>
            <section className="institutional-hero">
                <div className="public-container institutional-hero-grid">
                    <div className="institutional-hero-copy">
                        <span className="hero-kicker">Portal resmi {campus.name}</span>
                        <h1>Tempat pengetahuan tumbuh, <em>masa depan dibentuk.</em></h1>
                        <p>Selamat datang di ruang digital {campus.name}. Temukan kabar terbaru, layanan akademik, kehidupan kampus, dan informasi penerimaan dalam satu tempat.</p>
                        <div className="hero-actions">
                            <Link href={links.admission} className="campus-button campus-button-primary">Jelajahi penerimaan <ArrowRight size={16} /></Link>
                            <Link href="/institusi/profil" className="hero-text-link">Tentang {campus.name} <MoveUpRight size={15} /></Link>
                        </div>
                    </div>
                    <div className="institutional-hero-visual" aria-label="Suasana akademik">
                        <div className="visual-topline"><span>RUANG AKADEMIK</span><span>01 / 04</span></div>
                        <div className="visual-quote">“Membuka akses belajar, memperluas kemungkinan.”</div>
                        <div className="visual-campus-mark"><span>{campus.name.slice(0, 2).toUpperCase()}</span><small>EST. 2026</small></div>
                        <div className="visual-bottomline"><span>Belajar · Berkarya · Berdampak</span><ArrowUpRight size={16} /></div>
                    </div>
                </div>
                <div className="public-container hero-stat-line">
                    <span><b>{stats.studyPrograms || 12}</b> program studi aktif</span>
                    <span><b>24/7</b> akses informasi</span>
                    <span><b>{stats.admissionOpen ? 'Sedang dibuka' : 'Segera hadir'}</b> penerimaan mahasiswa baru</span>
                    <span className="hero-stat-note">Terakhir diperbarui hari ini</span>
                </div>
            </section>

            <section className="public-container notice-section">
                <div className="notice-heading"><span className="section-label"><Megaphone size={15} /> Papan informasi</span><Link href={links.announcements}>Lihat semua pengumuman <ChevronRight size={15} /></Link></div>
                <div className="notice-grid">
                    <article className="notice-feature">
                        <div className="notice-feature-meta"><span className="notice-badge">PENTING</span><span>{featured?.publishedAt ?? 'Informasi terbaru'}</span></div>
                        <h2>{featured?.title ?? 'Informasi akademik dan kegiatan kampus terbaru'}</h2>
                        <p>{featured?.excerpt ?? `Ikuti kabar terbaru, jadwal kegiatan, dan informasi resmi dari ${campus.name}.`}</p>
                        <Link href={links.announcements} className="underlined-link">Baca pengumuman <ArrowUpRight size={15} /></Link>
                    </article>
                    <div className="notice-list">
                        {(stories.length ? stories : [
                            { id: 11, title: 'Kalender akademik semester berjalan', excerpt: 'Lihat tanggal penting perkuliahan dan administrasi.', publishedAt: 'Akademik', isPinned: false },
                            { id: 12, title: 'Layanan kampus untuk seluruh civitas', excerpt: 'Temukan informasi yang membantu aktivitas Anda.', publishedAt: 'Kampus', isPinned: false },
                        ]).map((item) => <Link href={links.announcements} className="notice-list-item" key={item.id}><span className="notice-list-icon"><Newspaper size={17} /></span><span><small>{item.publishedAt}</small><strong>{item.title}</strong><em>{item.excerpt}</em></span><ChevronRight size={16} /></Link>)}
                    </div>
                </div>
            </section>

            <section className="campus-life-section">
                <div className="public-container">
                    <div className="section-intro-row"><div><span className="section-label">Menjelajahi kampus</span><h2>Satu kampus, banyak cara untuk bertumbuh.</h2></div><p>Baik Anda datang untuk belajar, mengajar, bekerja sama, atau mencari informasi—mulai perjalanan Anda dari sini.</p></div>
                    <div className="visitor-grid">{visitorLinks.map(({ icon: Icon, label, text, href, action }, index) => <Link href={href} className="visitor-card" key={label}><span className="visitor-card-number">0{index + 1}</span><Icon className="visitor-card-icon" size={24} /><span className="visitor-card-label">{label}</span><p>{text}</p><span className="visitor-card-action">{action} <ArrowUpRight size={15} /></span></Link>)}</div>
                </div>
            </section>

            <section className="public-container campus-story-section">
                <div className="story-copy"><span className="section-label"><Award size={15} /> Tentang institusi</span><h2>Kami percaya pendidikan harus terasa dekat dengan kehidupan.</h2><p>{campus.description} Di sini, ruang kelas bertemu dengan gagasan, kolaborasi, dan keberanian untuk memberi dampak yang nyata.</p><div className="story-checks"><span><CircleCheck size={16} /> Pembelajaran yang relevan</span><span><CircleCheck size={16} /> Komunitas yang inklusif</span><span><CircleCheck size={16} /> Budaya integritas</span></div><Link href="/institusi/profil" className="underlined-link">Baca kisah institusi <ArrowRight size={15} /></Link></div>
                <div className="story-facts"><div className="story-fact-main"><span className="story-fact-index">02</span><BookOpen size={25} /><strong>Belajar<br />lebih bermakna</strong><small>Karena setiap perjalanan memiliki tujuan.</small></div><div className="story-fact-list"><span><b>01</b><em>Visi &amp; misi</em><ChevronRight size={15} /></span><span><b>02</b><em>Fasilitas kampus</em><ChevronRight size={15} /></span><span><b>03</b><em>Akreditasi &amp; mutu</em><ChevronRight size={15} /></span></div></div>
            </section>

            <section className="campus-calendar-section">
                <div className="public-container calendar-grid">
                    <div className="calendar-intro"><span className="section-label"><CalendarDays size={15} /> Agenda akademik</span><h2>Catat tanggal pentingnya.</h2><p>Jangan lewatkan agenda perkuliahan, administrasi, dan kegiatan yang berlangsung di {campus.name}.</p><Link href="/agenda" className="underlined-link">Buka kalender lengkap <ArrowRight size={15} /></Link></div>
                    <div className="agenda-list">{agenda.length ? agenda.map((item) => <div className="agenda-row" key={item.id}><div className="agenda-date"><b>{item.date}</b><small>{item.month}</small></div><div><span>{item.detail}</span><strong>{item.title}</strong></div><ChevronRight size={16} /></div>) : <div className="agenda-empty">Belum ada agenda kampus yang dipublikasikan.</div>}</div>
                </div>
            </section>

            <section className="public-container latest-section">
                <div className="section-intro-row latest-intro"><div><span className="section-label"><FileText size={15} /> Dari kampus</span><h2>Kabar, karya, dan cerita hari ini.</h2></div><Link href="/berita" className="underlined-link">Buka semua berita <ArrowRight size={15} /></Link></div>
                <div className="latest-grid">{(news.length ? news : [{ id: 0, title: 'Belum ada berita yang dipublikasikan', excerpt: `Kabar terbaru ${campus.name} akan tampil di area ini.`, publishedAt: 'Informasi kampus' }]).map((item, index) => <Link href="/berita" className={`latest-card latest-card-${index + 1}`} key={item.id}><div className="latest-card-image"><span>0{index + 1}</span><ArrowUpRight size={18} /></div><div className="latest-card-content"><small>{item.publishedAt}</small><h3>{item.title}</h3><p>{item.excerpt}</p></div></Link>)}</div>
            </section>

            <section className="public-container admission-banner"><div className="admission-banner-mark"><GraduationCap size={30} /></div><div><span className="section-label">Penerimaan mahasiswa baru</span><h2>Siap menulis bab berikutnya?</h2><p>Kenali program studi, persyaratan, dan langkah pendaftaran di {campus.name}.</p></div><div className="admission-banner-actions"><Link href={links.admission} className="campus-button campus-button-light">Lihat informasi PMB <ArrowRight size={16} /></Link><Link href={links.admissionStatus} className="banner-secondary-link">Cek status pendaftaran</Link></div></section>
        </PublicShell>
    );
}
