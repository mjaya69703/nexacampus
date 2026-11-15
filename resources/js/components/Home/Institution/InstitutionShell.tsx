// Public institusi section shell (mirrors AcademicShell).
import { Link } from '@inertiajs/react';
import { Building2, Handshake, Landmark, Library, LucideIcon, Network, Search, ShieldCheck, Target } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';
import '../../../../css/admission-public.css';

export type InstitutionUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
export type InstitutionBaseProps = { campus: Campus; links: PublicLinks; user: InstitutionUser };

type ShellProps = InstitutionBaseProps & {
    eyebrow: string;
    title: string;
    description: string;
    icon: LucideIcon;
    children: ReactNode;
    action?: ReactNode;
    activeTab?: string;
};

const tabs = [
    { label: 'Profil', href: '/institusi/profil', icon: Building2 },
    { label: 'Visi & Misi', href: '/institusi/visi-misi', icon: Target },
    { label: 'Struktur', href: '/institusi/struktur', icon: Network },
    { label: 'Fasilitas', href: '/institusi/fasilitas', icon: Library },
    { label: 'Akreditasi', href: '/institusi/akreditasi', icon: ShieldCheck },
    { label: 'Kerjasama', href: '/institusi/kerjasama', icon: Handshake },
];

export function InstitutionShell({ campus, links, user, eyebrow, title, description, icon: Icon, children, action, activeTab = '' }: ShellProps) {
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const searchResults = useMemo(() => tabs.filter((tab) => tab.label.toLowerCase().includes(query.toLowerCase())), [query]);

    return <PublicShell campus={campus} links={links} user={user} activeNav="Institusi">
        <div className="academic-page adm-root">
            <div className="academic-subnav"><div className="public-container academic-subnav-inner"><div className="academic-subnav-title"><Landmark size={16} /><span>Profil Institusi</span></div><nav aria-label="Navigasi institusi">{tabs.map(({ label, href, icon: TabIcon }) => <Link href={href} key={label} className={label === activeTab ? 'active' : ''}><TabIcon size={14} />{label}</Link>)}</nav><div className="academic-search-wrap"><button type="button" className="academic-search" onClick={() => setSearchOpen(!searchOpen)} aria-label="Cari halaman" aria-expanded={searchOpen}><Search size={15} /></button>{searchOpen && <div className="academic-search-popover"><label htmlFor="institution-search-input">Cari halaman</label><input id="institution-search-input" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: akreditasi" />{query && (searchResults.length ? searchResults.map(({ label, href }) => <Link href={href} key={label} onClick={() => { setSearchOpen(false); setQuery(''); }}>{label}</Link>) : <span className="academic-search-empty">Tidak ada halaman yang cocok.</span>)}</div>}</div></div></div>
            <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">Institusi <span>/</span> {eyebrow}</span><div className="academic-title-row"><span className="academic-title-icon"><Icon size={25} /></span><div><h1>{title}</h1><p>{description}</p></div></div></div>{action}</div></header>
            <main className="public-container adm-content">{children}</main>
        </div>
    </PublicShell>;
}
