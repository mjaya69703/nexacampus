// Public publikasi section shell (mirrors AcademicShell).
import { Link } from '@inertiajs/react';
import { CalendarDays, HelpCircle, Images, LucideIcon, Megaphone, Newspaper, Search } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';
import '../../../../css/admission-public.css';

export type PublicationUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
export type PublicationBaseProps = { campus: Campus; links: PublicLinks; user: PublicationUser };

type ShellProps = PublicationBaseProps & {
    eyebrow: string;
    title: string;
    description: string;
    icon: LucideIcon;
    children: ReactNode;
    action?: ReactNode;
    activeTab?: string;
    activeNav?: string;
};

const tabs = [
    { label: 'Pengumuman', href: '/pengumuman', icon: Megaphone },
    { label: 'Berita', href: '/berita', icon: Newspaper },
    { label: 'Agenda', href: '/agenda', icon: CalendarDays },
    { label: 'Galeri', href: '/galeri', icon: Images },
    { label: 'FAQ', href: '/faq', icon: HelpCircle },
];

export function PublicationShell({ campus, links, user, eyebrow, title, description, icon: Icon, children, action, activeTab = '', activeNav = 'Publikasi' }: ShellProps) {
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const searchResults = useMemo(() => tabs.filter((tab) => tab.label.toLowerCase().includes(query.toLowerCase())), [query]);

    return <PublicShell campus={campus} links={links} user={user} activeNav={activeNav}>
        <div className="academic-page adm-root">
            <div className="academic-subnav"><div className="public-container academic-subnav-inner"><div className="academic-subnav-title"><Newspaper size={16} /><span>Publikasi Kampus</span></div><nav aria-label="Navigasi publikasi">{tabs.map(({ label, href, icon: TabIcon }) => <Link href={href} key={label} className={label === activeTab ? 'active' : ''}><TabIcon size={14} />{label}</Link>)}</nav><div className="academic-search-wrap"><button type="button" className="academic-search" onClick={() => setSearchOpen(!searchOpen)} aria-label="Cari halaman" aria-expanded={searchOpen}><Search size={15} /></button>{searchOpen && <div className="academic-search-popover"><label htmlFor="publication-search-input">Cari halaman</label><input id="publication-search-input" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: agenda" />{query && (searchResults.length ? searchResults.map(({ label, href }) => <Link href={href} key={label} onClick={() => { setSearchOpen(false); setQuery(''); }}>{label}</Link>) : <span className="academic-search-empty">Tidak ada halaman yang cocok.</span>)}</div>}</div></div></div>
            <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">Publikasi <span>/</span> {eyebrow}</span><div className="academic-title-row"><span className="academic-title-icon"><Icon size={25} /></span><div><h1>{title}</h1><p>{description}</p></div></div></div>{action}</div></header>
            <main className="public-container adm-content">{children}</main>
        </div>
    </PublicShell>;
}
