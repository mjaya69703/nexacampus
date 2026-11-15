// Admin dashboard shell (Inertia React) — desain modern full-React,
// 100% lucide icons (tidak ada Font Awesome). Sidebar navy + topbar
// (breadcrumb, command palette, notifikasi, apps, periode, tema,
// menu user) + footer. Seluruh navigasi memakai <a> biasa agar
// halaman Blade/Livewire non-Inertia tidak kejebak error-modal.
import { Head } from '@inertiajs/react';
import {
    Bell,
    CalendarDays,
    ChevronDown,
    Grid2X2,
    House,
    LayoutDashboard,
    LogOut,
    Menu,
    Moon,
    Newspaper,
    PanelLeft,
    Repeat,
    Search,
    Sun,
    UserRound,
    X,
} from 'lucide-react';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { FaIcon } from './FaIcon';
import { FlashAlert } from './FlashAlert';
import '../../../css/admin-shell.css';

export type ShellMenuChild = { title: string; url: string; isActive: boolean };
export type ShellMenu = {
    id: string;
    type: 'link' | 'group';
    title: string;
    icon: string | null;
    url: string;
    isActive: boolean;
    children: ShellMenuChild[];
};
export type ShellCommandItem = { group: string | null; title: string; href: string; icon: string };
export type ShellUser = { name: string; photo: string; roleLabel: string };

export type ShellProps = {
    campusName: string;
    campusLogo: string;
    appName: string;
    appVersion: string;
    activePeriod: string | null;
    pretitle: string;
    pageTitle: string;
    dashboardUrl: string;
    profileUrl: string;
    switchRoleUrl: string;
    logoutUrl: string;
    homeUrl: string;
    admissionStatusUrl: string;
    announcementsUrl: string;
    sourceUrl: string;
    user: ShellUser;
    menus: ShellMenu[];
    commandItems: ShellCommandItem[];
};

type PopKey = 'notifications' | 'apps' | null;

const initialsFor = (name: string) =>
    name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

const storedTheme = () => {
    if (typeof window === 'undefined') return 'light';
    return window.localStorage.getItem('nexa-bs-theme')
        ?? window.localStorage.getItem('nexa-theme')
        ?? 'light';
};

const BULAN_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export function AdminShell({ shell, children }: { shell: ShellProps; children: ReactNode }) {
    const [dark, setDark] = useState(() => storedTheme() === 'dark');
    const [drawer, setDrawer] = useState(false);
    const [collapsed, setCollapsed] = useState(() => typeof window !== 'undefined' && window.localStorage.getItem('nexa-shell-collapsed') === '1');
    const [userOpen, setUserOpen] = useState(false);
    const [openPop, setOpenPop] = useState<PopKey>(null);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(shell.menus.filter((menu) => menu.type === 'group' && menu.isActive).map((menu) => [menu.id, true])),
    );
    const [avatarFailed, setAvatarFailed] = useState(false);
    const [logoFailed, setLogoFailed] = useState(false);
    const paletteInput = useRef<HTMLInputElement>(null);

    const hasLogo = Boolean(shell.campusLogo) && !logoFailed;
    const now = new Date();

    useEffect(() => {
        document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        window.localStorage.setItem('nexa-bs-theme', dark ? 'dark' : 'light');
        window.localStorage.setItem('nexa-theme', dark ? 'dark' : 'light');
    }, [dark]);

    useEffect(() => {
        window.localStorage.setItem('nexa-shell-collapsed', collapsed ? '1' : '0');
    }, [collapsed]);

    useEffect(() => {
        document.documentElement.setAttribute('data-bs-theme', storedTheme());
        document.documentElement.setAttribute('data-theme', storedTheme());
    }, []);

    useEffect(() => {
        if (!userOpen && !openPop) return;
        const close = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest('[data-shell-user]')) setUserOpen(false);
            if (!target.closest('[data-shell-pop]')) setOpenPop(null);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [userOpen, openPop]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPaletteOpen(true);
            }
            if (event.key === 'Escape') setPaletteOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (paletteOpen) {
            setQuery('');
            window.setTimeout(() => paletteInput.current?.focus(), 30);
        }
    }, [paletteOpen]);

    const results = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return shell.commandItems.slice(0, 8);
        return shell.commandItems
            .filter((item) => `${item.group ?? ''} ${item.title}`.toLowerCase().includes(needle))
            .slice(0, 12);
    }, [query, shell.commandItems]);

    const toggleGroup = (id: string) => setOpenGroups((current) => ({ ...current, [id]: !current[id] }));
    const togglePop = (key: Exclude<PopKey, null>) => setOpenPop((current) => (current === key ? null : key));

    return (
        <div className={`as-root${collapsed ? ' side-collapsed' : ''}`}>
            <Head title={`${shell.pageTitle} · ${shell.pretitle} · ${shell.appName}`} />

            {drawer && <button type="button" className="as-sidebar-overlay" aria-label="Tutup navigasi" onClick={() => setDrawer(false)} />}

            {/* ── Sidebar ── */}
            <aside className={`as-sidebar${drawer ? ' open' : ''}`} aria-label="Navigasi utama">
                <a className={`as-brand${hasLogo ? ' center' : ''}`} href={shell.dashboardUrl} aria-label={shell.appName}>
                    {hasLogo
                        ? (
                            <img
                                className="as-brand-logo"
                                src={shell.campusLogo}
                                alt={`${shell.campusName} Logo`}
                                onError={() => setLogoFailed(true)}
                            />
                        )
                        : (
                            <span className="as-brand-copy">
                                <strong>{shell.appName}</strong>
                                <small>{shell.campusName}</small>
                            </span>
                        )}
                </a>
                <nav className="as-nav">
                    {shell.menus.map((menu) => menu.type === 'link'
                        ? (
                            <a key={menu.id} className={`as-nav-link${menu.isActive ? ' active' : ''}`} href={menu.url} title={menu.title} onClick={() => setDrawer(false)}>
                                {menu.icon && <span className="as-nav-icon"><FaIcon name={menu.icon} size={15} /></span>}
                                <span className="as-nav-title">{menu.title}</span>
                            </a>
                        ) : (
                            <div key={menu.id}>
                                <button
                                    type="button"
                                    className={`as-nav-link${menu.isActive ? ' active' : ''}`}
                                    aria-expanded={Boolean(openGroups[menu.id])}
                                    title={menu.title}
                                    onClick={() => toggleGroup(menu.id)}
                                >
                                    {menu.icon && <span className="as-nav-icon"><FaIcon name={menu.icon} size={15} /></span>}
                                    <span className="as-nav-title">{menu.title}</span>
                                    <ChevronDown size={13} className={`as-nav-caret${openGroups[menu.id] ? ' open' : ''}`} />
                                </button>
                                {openGroups[menu.id] && (
                                    <div className="as-nav-children">
                                        {menu.children.map((child) => (
                                            <a
                                                key={`${menu.id}-${child.title}`}
                                                className={`as-nav-child${child.isActive ? ' active' : ''}`}
                                                href={child.url}
                                                onClick={() => setDrawer(false)}
                                            >
                                                {child.title}
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                </nav>
                <div className="as-side-foot">
                    <a className="as-side-user" href={shell.profileUrl} title="Buka profil saya">
                        <span className="as-side-avatar">
                            {shell.user.photo && !avatarFailed
                                ? <img src={shell.user.photo} alt={shell.user.name} onError={() => setAvatarFailed(true)} />
                                : <span className="as-side-avatar-fallback">{initialsFor(shell.user.name) || '?'}</span>}
                        </span>
                        <span className="as-side-user-meta"><b>{shell.user.name}</b><small>{shell.user.roleLabel}</small></span>
                        <span className="as-side-logout" role="button" aria-label="Keluar" title="Keluar"
                            onClick={(event) => { event.preventDefault(); window.location.href = shell.logoutUrl; }}>
                            <LogOut size={15} />
                        </span>
                    </a>
                </div>
            </aside>

            <div className="as-main">
                {/* ── Topbar ── */}
                <header className="as-topbar">
                    <div className="as-topbar-inner">
                        <button type="button" className="as-burger" aria-label="Buka navigasi" onClick={() => setDrawer(true)}>
                            <Menu size={17} />
                        </button>

                        <button
                            type="button"
                            className="as-collapse-btn"
                            aria-label={collapsed ? 'Bentangkan sidebar' : 'Ciutkan sidebar'}
                            title={collapsed ? 'Bentangkan sidebar' : 'Ciutkan sidebar'}
                            aria-pressed={collapsed}
                            onClick={() => setCollapsed(!collapsed)}
                        >
                            <PanelLeft size={17} />
                        </button>

                        <div className="as-crumb" aria-label="Posisi halaman">
                            <small>{shell.pretitle}</small>
                            <strong>{shell.pageTitle}</strong>
                        </div>

                        <button type="button" className="as-search-btn" onClick={() => setPaletteOpen(true)} aria-label="Cari menu atau halaman">
                            <Search size={15} />
                            <span>Cari menu atau halaman…</span>
                            <kbd>Ctrl K</kbd>
                        </button>

                        <div className="as-topbar-spacer" />

                        {shell.activePeriod && (
                            <div className="as-period" title="Tahun akademik aktif">
                                <CalendarDays size={15} />
                                <span><small>Periode aktif</small><strong>{shell.activePeriod}</strong></span>
                            </div>
                        )}

                        <button
                            type="button"
                            className="as-theme-toggle"
                            onClick={() => setDark(!dark)}
                            aria-label={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
                            aria-pressed={dark}
                            title={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
                        >
                            {dark ? <Moon size={14} /> : <Sun size={14} />}
                            <span>{dark ? 'Gelap' : 'Terang'}</span>
                        </button>

                        <div className="as-tool" data-shell-pop>
                            <button
                                type="button"
                                className="as-tool-btn"
                                aria-label="Notifikasi"
                                aria-expanded={openPop === 'notifications'}
                                aria-haspopup="menu"
                                onClick={() => togglePop('notifications')}
                            >
                                <Bell size={16} /><i />
                            </button>
                            {openPop === 'notifications' && (
                                <div className="as-pop" role="menu">
                                    <span className="as-pop-label">Notifikasi</span>
                                    <div className="as-pop-empty">Belum ada notifikasi baru.</div>
                                </div>
                            )}
                        </div>

                        <div className="as-tool" data-shell-pop>
                            <button
                                type="button"
                                className="as-tool-btn"
                                aria-label="Aplikasi kampus"
                                aria-expanded={openPop === 'apps'}
                                aria-haspopup="menu"
                                onClick={() => togglePop('apps')}
                            >
                                <Grid2X2 size={16} />
                            </button>
                            {openPop === 'apps' && (
                                <div className="as-pop" role="menu">
                                    <span className="as-pop-label">Aplikasi kampus</span>
                                    <a href={shell.homeUrl} onClick={() => setOpenPop(null)}><House size={14} /> Beranda <span>↗</span></a>
                                    <a href={shell.dashboardUrl} onClick={() => setOpenPop(null)}><LayoutDashboard size={14} /> Portal akademik <span>↗</span></a>
                                    <a href={shell.admissionStatusUrl} onClick={() => setOpenPop(null)}><Search size={14} /> Cek status pendaftaran <span>↗</span></a>
                                    <a href={shell.announcementsUrl} onClick={() => setOpenPop(null)}><Newspaper size={14} /> Informasi kampus <span>↗</span></a>
                                </div>
                            )}
                        </div>

                        <div className="as-user" data-shell-user>
                            <button type="button" className="as-user-btn" aria-haspopup="menu" aria-expanded={userOpen} onClick={() => setUserOpen(!userOpen)}>
                                <span className="as-avatar">
                                    {shell.user.photo && !avatarFailed
                                        ? <img src={shell.user.photo} alt={shell.user.name} onError={() => setAvatarFailed(true)} />
                                        : <span className="as-avatar-fallback">{initialsFor(shell.user.name) || '?'}</span>}
                                </span>
                                <span className="as-user-meta"><b>{shell.user.name}</b><small>{shell.user.roleLabel}</small></span>
                                <ChevronDown size={14} className={`as-nav-caret${userOpen ? ' open' : ''}`} color="var(--as-muted)" />
                            </button>
                            {userOpen && (
                                <div className="as-menu" role="menu">
                                    <a href={shell.dashboardUrl} onClick={() => setUserOpen(false)}><LayoutDashboard size={14} /> Dashboard</a>
                                    <a href={shell.profileUrl} onClick={() => setUserOpen(false)}><UserRound size={14} /> Profil saya</a>
                                    <hr />
                                    <a href={shell.switchRoleUrl} onClick={() => setUserOpen(false)}><Repeat size={14} /> Ganti peran</a>
                                    <a href={shell.logoutUrl} className="danger" onClick={() => setUserOpen(false)}><LogOut size={14} /> Keluar</a>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* ── Page body ── */}
                <main className="as-page-body">
                    <FlashAlert />
                    {children}
                </main>

                <footer className="as-footer">
                    <div className="as-footer-inner">
                        <span>
                            Copyright © {now.getFullYear()} {BULAN_ID[now.getMonth()]} - <b>{shell.appName}</b>.
                            {' '}<span className="as-footer-version">{shell.appVersion}</span>
                        </span>
                        <nav aria-label="Footer">
                            <a href="https://github.com/tabler/tabler" target="_blank" rel="noopener noreferrer">Build with Tabler</a>
                            <a href={shell.sourceUrl} target="_blank" rel="noopener noreferrer">Source Code NexaCampus</a>
                        </nav>
                    </div>
                </footer>
            </div>

            {/* ── Command palette ── */}
            {paletteOpen && (
                <div className="as-palette-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setPaletteOpen(false); }}>
                    <div className="as-palette" role="dialog" aria-label="Cari menu atau halaman">
                        <div className="as-palette-input">
                            <Search size={15} />
                            <input
                                ref={paletteInput}
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' && results[0]) window.location.href = results[0].href;
                                }}
                                placeholder="Ketik nama menu…"
                                aria-label="Cari menu"
                            />
                            <button type="button" className="as-burger" style={{ display: 'grid' }} aria-label="Tutup pencarian" onClick={() => setPaletteOpen(false)}>
                                <X size={15} />
                            </button>
                        </div>
                        <div className="as-palette-list">
                            {results.map((item) => (
                                <a key={`${item.group ?? ''}-${item.title}`} className="as-palette-item" href={item.href}>
                                    <span className="as-palette-icon"><FaIcon name={item.icon} size={14} /></span>
                                    <span className="as-palette-copy"><strong>{item.title}</strong><small>{item.group ?? 'Navigasi utama'}</small></span>
                                </a>
                            ))}
                            {results.length === 0 && <div className="as-palette-empty">Menu tidak ditemukan.</div>}
                        </div>
                        <div className="as-palette-foot"><span><kbd>Enter</kbd> buka</span><span><kbd>Esc</kbd> tutup</span></div>
                    </div>
                </div>
            )}
        </div>
    );
}
