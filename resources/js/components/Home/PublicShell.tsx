// Home/public site shell.
import { Link } from '@inertiajs/react';
import { Bell, ChevronDown, Grid2X2, Menu, Moon, Settings, Sun, UserCircle, X } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';
import { FlashAlert } from '../Shared/FlashAlert';

export type Campus = { name: string; logo: string; description: string };
export type PublicLinks = { login: string; admission: string; admissionStatus: string; tuition: string; requirements: string; faq: string; contact: string; announcements: string };
type User = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
type Props = { campus: Campus; links: PublicLinks; user: User; children: ReactNode; activeNav?: string };

const initialsFor = (name: string) => name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

export function PublicShell({ campus, links, user, children, activeNav = 'Beranda' }: Props) {
    const [menuOpen, setMenuOpen] = useState(false);
    const [openMenu, setOpenMenu] = useState<'portal' | 'profile' | 'notifications' | 'apps' | null>(null);
    const [dark, setDark] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem('nexa-theme') === 'dark');
    const [avatarFailed, setAvatarFailed] = useState(false);

    useEffect(() => {
        const isDark = window.localStorage.getItem('nexa-theme') === 'dark';
        setDark(isDark);
        document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    }, []);

    useEffect(() => {
        if (!openMenu) return;
        const closeMenu = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest('[data-header-menu]')) {
                setOpenMenu(null);
            }
        };
        document.addEventListener('mousedown', closeMenu);
        return () => document.removeEventListener('mousedown', closeMenu);
    }, [openMenu]);

    const toggleTheme = () => {
        const next = !dark;
        setDark(next);
        document.documentElement.dataset.theme = next ? 'dark' : 'light';
        window.localStorage.setItem('nexa-theme', next ? 'dark' : 'light');
    };

    const navItems: Array<{ label: string; href: string }> = [
        { label: 'Beranda', href: '/' },
        { label: 'Akademik', href: '/program-studi' },
        { label: 'Penerimaan', href: links.admission },
        { label: 'Kemahasiswaan', href: '/kemahasiswaan/layanan' },
        { label: 'Institusi', href: '/institusi/profil' },
        { label: 'Publikasi', href: links.announcements },
        { label: 'Kontak', href: links.contact },
    ];
    const avatarContent = user?.photo && !avatarFailed
        ? <img src={user.photo} alt={user.name} onError={() => setAvatarFailed(true)} />
        : user ? (initialsFor(user.name) || '?') : <UserCircle size={18} />;

    return <div className="public-app">
        <header className="campus-header">
            <div className="campus-identity">
                <div className="public-container identity-inner">
                    <div className="utility-context"><span className="utility-signal" /> <span>Portal resmi {campus.name}</span><i>•</i><span className="utility-muted">Informasi untuk seluruh civitas akademika</span></div>
                    <div className="campus-tools">
                        <div className="utility-links"><Link href="/agenda">Agenda</Link><Link href="/faq">Bantuan</Link><Link href={links.contact}>Kontak</Link></div>
                        <div className="theme-switch" aria-label="Pilih tema"><button type="button" className="theme-toggle-button" onClick={toggleTheme} aria-label={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'} aria-pressed={dark} title={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}>{dark ? <Moon size={14} /> : <Sun size={14} />}<span>{dark ? 'Gelap' : 'Terang'}</span></button></div>
                        <div className="campus-tool-menu" data-header-menu><button type="button" className="campus-tool" onClick={() => { setOpenMenu(openMenu === 'notifications' ? null : 'notifications'); }} aria-label="Notifikasi" aria-expanded={openMenu === 'notifications'} aria-haspopup="menu"><Bell size={16} /><i /></button>{openMenu === 'notifications' && <div className="header-popover utility-popover" role="menu"><span className="popover-label">NOTIFIKASI</span><div className="popover-empty">Belum ada notifikasi baru.</div></div>}</div>
                        <div className="campus-tool-menu" data-header-menu><button type="button" className="campus-tool" onClick={() => { setOpenMenu(openMenu === 'apps' ? null : 'apps'); }} aria-label="Aplikasi" aria-expanded={openMenu === 'apps'} aria-haspopup="menu"><Grid2X2 size={16} /></button>{openMenu === 'apps' && <div className="header-popover utility-popover apps-popover" role="menu"><span className="popover-label">APLIKASI KAMPUS</span>{user ? <a href={user.dashboardUrl} onClick={() => setOpenMenu(null)}>Portal akademik <span>↗</span></a> : <Link href={links.login} onClick={() => setOpenMenu(null)}>Portal akademik <span>↗</span></Link>}<Link href="/admission/status" onClick={() => setOpenMenu(null)}>Penerimaan mahasiswa <span>↗</span></Link><Link href="/pengumuman" onClick={() => setOpenMenu(null)}>Informasi kampus <span>↗</span></Link></div>}</div>
                        <div className="campus-user-menu" data-header-menu>
                            <button type="button" className="campus-user" onClick={() => { setOpenMenu(openMenu === 'profile' ? null : 'profile'); }} aria-expanded={openMenu === 'profile'} aria-haspopup="menu"><span className="user-avatar">{avatarContent}</span><span><b>{user?.name ?? 'Guest'}</b><small>{user?.role ?? 'Guest'}</small></span><ChevronDown size={13} className={openMenu === 'profile' ? 'is-open' : ''} /></button>
                            {openMenu === 'profile' && <div className="header-popover profile-popover" role="menu"><div className="popover-heading"><span className="user-avatar">{avatarContent}</span><span><strong>{user?.name ?? 'Guest'}</strong><small>{user?.role ?? 'Akses publik'}</small></span></div>{user ? <><a href="/profile" onClick={() => setOpenMenu(null)}>Profil saya <span>↗</span></a><Link href="/auth/switch-role" onClick={() => setOpenMenu(null)}>Ganti peran <span>↗</span></Link><Link href="/auth/logout" onClick={() => setOpenMenu(null)} className="popover-danger">Keluar <span>↗</span></Link></> : <Link href={links.login} onClick={() => setOpenMenu(null)}>Masuk ke portal <span>↗</span></Link>}</div>}
                        </div>
                    </div>
                </div>
            </div>
            <div className="campus-navigation">
                <div className="public-container navigation-inner">
                    <Link href="/" className="campus-brand"><img src={campus.logo} alt={campus.name} /><span><strong>{campus.name}</strong><small>Sistem Informasi Akademik</small></span></Link>
                    <button className="mobile-menu-button" type="button" onClick={() => setMenuOpen(!menuOpen)} aria-label={menuOpen ? 'Tutup navigasi' : 'Buka navigasi'} aria-expanded={menuOpen} aria-controls="campus-navigation-menu">{menuOpen ? <X size={20} /> : <Menu size={20} />}</button>
                    <nav id="campus-navigation-menu" className={`campus-nav ${menuOpen ? 'is-open' : ''}`} aria-label="Navigasi situs">{navItems.map(({ label, href }) => <Link key={label} href={href} onClick={() => { setMenuOpen(false); setOpenMenu(null); }} className={label === activeNav ? 'active' : ''}>{label}</Link>)}</nav>
                    <div className="portal-menu-wrap" data-header-menu><button type="button" className="settings-link" onClick={() => { setOpenMenu(openMenu === 'portal' ? null : 'portal'); }} aria-expanded={openMenu === 'portal'} aria-haspopup="menu"><Settings size={15} /><span>{user ? 'Portal saya' : 'Masuk portal'}</span><ChevronDown size={12} className={openMenu === 'portal' ? 'is-open' : ''} /></button>{openMenu === 'portal' && <div className="header-popover portal-popover" role="menu"><span className="popover-label">AKSES PORTAL</span>{user ? <a href={user.dashboardUrl} onClick={() => setOpenMenu(null)}>Buka dashboard <span>↗</span></a> : <Link href={links.login} onClick={() => setOpenMenu(null)}>Login portal <span>↗</span></Link>}<Link href={links.admissionStatus} onClick={() => setOpenMenu(null)}>Cek status pendaftaran <span>↗</span></Link>{user && <a href="/profile" onClick={() => setOpenMenu(null)}>Profil akademik <span>↗</span></a>}</div>}</div>
                </div>
            </div>
        </header>
        <main><FlashAlert />{children}</main>
        <footer className="campus-footer"><div className="public-container footer-main"><div className="footer-intro"><Link href="/" className="campus-brand footer-brand"><img src={campus.logo} alt={campus.name} /><span><strong>{campus.name}</strong><small>Sistem Informasi Akademik</small></span></Link><p>{campus.description}</p><div className="footer-accreditation"><span>TERAKREDITASI</span><b>BAIK SEKALI</b></div></div><div className="footer-column"><h4>Akademik</h4><Link href="/program-studi">Program Studi</Link><Link href="/akademik/kalender">Kalender Akademik</Link><Link href="/akademik/jadwal">Jadwal Kuliah</Link><Link href="/akademik/kurikulum">Kurikulum</Link></div><div className="footer-column"><h4>Penerimaan</h4><Link href={links.admission}>Pendaftaran Mahasiswa Baru</Link><Link href={links.admissionStatus}>Cek Status Pendaftaran</Link><Link href={links.requirements}>Syarat Pendaftaran</Link><Link href={links.tuition}>Biaya Pendidikan</Link></div><div className="footer-column footer-contact"><h4>Hubungi Kami</h4><p><b>Alamat kampus</b><br />Jl. Pendidikan No. 1, Indonesia</p><p><b>Email</b><br />info@{campus.name.toLowerCase().replace(/\s+/g, '')}.ac.id</p><Link href={links.contact} className="footer-contact-link">Lihat informasi kontak <span>→</span></Link></div></div><div className="public-container footer-bottom"><span>© {new Date().getFullYear()} {campus.name}. Semua hak dilindungi.</span><div><Link href="/">Kebijakan Privasi</Link><Link href="/">Peta Situs</Link><span>Bahasa: ID</span></div></div></footer>
    </div>;
}
