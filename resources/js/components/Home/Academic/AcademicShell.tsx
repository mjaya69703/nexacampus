// Home academic shell.
import { Link } from '@inertiajs/react';
import { ArrowUpRight, BookOpen, CalendarDays, GraduationCap, Laptop, Library, LucideIcon, Search } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';

export type AcademicUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
export type AcademicBaseProps = { campus: Campus; links: PublicLinks; user: AcademicUser };

type Props = AcademicBaseProps & {
    eyebrow: string;
    title: string;
    description: string;
    icon: LucideIcon;
    children: ReactNode;
    action?: ReactNode;
    activeTab?: string;
};

const tabs = [
    { label: 'Program Studi', href: '/program-studi', icon: GraduationCap },
    { label: 'Kalender', href: '/akademik/kalender', icon: CalendarDays },
    { label: 'Jadwal Kuliah', href: '/akademik/jadwal', icon: BookOpen },
    { label: 'Kurikulum', href: '/akademik/kurikulum', icon: Library },
    { label: 'E-Learning', href: '/akademik/elearning', icon: Laptop },
];

export function AcademicShell({ campus, links, user, eyebrow, title, description, icon: Icon, children, action, activeTab = eyebrow }: Props) {
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const searchResults = useMemo(() => tabs.filter((tab) => tab.label.toLowerCase().includes(query.toLowerCase())), [query]);

    return <PublicShell campus={campus} links={links} user={user} activeNav="Akademik">
        <div className="academic-page">
            <div className="academic-subnav"><div className="public-container academic-subnav-inner"><div className="academic-subnav-title"><BookOpen size={16} /><span>Pusat Akademik</span></div><nav aria-label="Navigasi akademik">{tabs.map(({ label, href, icon: TabIcon }) => <Link href={href} key={label} className={label === activeTab ? 'active' : ''}><TabIcon size={14} />{label}</Link>)}</nav><div className="academic-search-wrap"><button type="button" className="academic-search" onClick={() => setSearchOpen(!searchOpen)} aria-label="Cari informasi akademik" aria-expanded={searchOpen}><Search size={15} /></button>{searchOpen && <div className="academic-search-popover"><label htmlFor="academic-search-input">Cari halaman akademik</label><input id="academic-search-input" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: kalender" />{query && (searchResults.length ? searchResults.map(({ label, href, icon: ResultIcon }) => <Link href={href} key={label} onClick={() => { setSearchOpen(false); setQuery(''); }}><ResultIcon size={13} />{label}</Link>) : <span className="academic-search-empty">Tidak ada halaman yang cocok.</span>)}</div>}</div></div></div>
            <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">Akademik <span>/</span> {eyebrow}</span><div className="academic-title-row"><span className="academic-title-icon"><Icon size={25} /></span><div><h1>{title}</h1><p>{description}</p></div></div></div>{action}</div></header>
            <main className="public-container academic-content">{children}</main>
        </div>
    </PublicShell>;
}

export function AcademicStat({ value, label }: { value: string | number; label: string }) {
    return <div className="academic-stat"><strong>{value}</strong><span>{label}</span></div>;
}

export function AcademicLink({ href, children }: { href: string; children: ReactNode }) {
    return <Link href={href} className="academic-link">{children}<ArrowUpRight size={15} /></Link>;
}
