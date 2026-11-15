// Home kemahasiswaan/alumni shell (mirrors AcademicShell).
import { Link } from '@inertiajs/react';
import { Briefcase, GraduationCap, HeartPulse, LucideIcon, Search, Trophy, Users, Wallet } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';
import '../../../../css/admission-public.css';

export type CommunityUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
export type CommunityBaseProps = { campus: Campus; links: PublicLinks; user: CommunityUser };

type ShellProps = CommunityBaseProps & {
    section: 'kemahasiswaan' | 'alumni';
    eyebrow: string;
    title: string;
    description: string;
    icon: LucideIcon;
    children: ReactNode;
    action?: ReactNode;
    activeTab?: string;
};

const kemahasiswaanTabs = [
    { label: 'Layanan', href: '/kemahasiswaan/layanan', icon: HeartPulse },
    { label: 'Beasiswa', href: '/beasiswa', icon: Wallet },
    { label: 'Organisasi', href: '/kemahasiswaan/organisasi', icon: Users },
    { label: 'Prestasi', href: '/kemahasiswaan/prestasi', icon: Trophy },
];
const alumniTabs = [
    { label: 'Profil Alumni', href: '/alumni', icon: Users },
    { label: 'Karir', href: '/alumni/karir', icon: Briefcase },
];

export function KemahasiswaanShell(props: Omit<ShellProps, 'section'>) {
    return <CommunityShell {...props} section="kemahasiswaan" />;
}

export function AlumniShell(props: Omit<ShellProps, 'section'>) {
    return <CommunityShell {...props} section="alumni" />;
}

function CommunityShell({ campus, links, user, section, eyebrow, title, description, icon: Icon, children, action, activeTab = eyebrow }: ShellProps) {
    const tabs = section === 'kemahasiswaan' ? kemahasiswaanTabs : alumniTabs;
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const searchResults = useMemo(() => tabs.filter((tab) => tab.label.toLowerCase().includes(query.toLowerCase())), [query]);

    return <PublicShell campus={campus} links={links} user={user} activeNav="Kemahasiswaan">
        <div className="academic-page adm-root">
            <div className="academic-subnav"><div className="public-container academic-subnav-inner"><div className="academic-subnav-title">{section === 'kemahasiswaan' ? <GraduationCap size={16} /> : <Users size={16} />}<span>{section === 'kemahasiswaan' ? 'Pusat Kemahasiswaan' : 'Jaringan Alumni'}</span></div><nav aria-label={`Navigasi ${section === 'kemahasiswaan' ? 'kemahasiswaan' : 'alumni'}`}>{tabs.map(({ label, href, icon: TabIcon }) => <Link href={href} key={label} className={label === activeTab ? 'active' : ''}><TabIcon size={14} />{label}</Link>)}</nav><div className="academic-search-wrap"><button type="button" className="academic-search" onClick={() => setSearchOpen(!searchOpen)} aria-label="Cari halaman" aria-expanded={searchOpen}><Search size={15} /></button>{searchOpen && <div className="academic-search-popover"><label htmlFor="community-search-input">Cari halaman</label><input id="community-search-input" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: beasiswa" />{query && (searchResults.length ? searchResults.map(({ label, href }) => <Link href={href} key={label} onClick={() => { setSearchOpen(false); setQuery(''); }}>{label}</Link>) : <span className="academic-search-empty">Tidak ada halaman yang cocok.</span>)}</div>}</div></div></div>
            <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">{section === 'kemahasiswaan' ? 'Kemahasiswaan' : 'Alumni'} <span>/</span> {eyebrow}</span><div className="academic-title-row"><span className="academic-title-icon"><Icon size={25} /></span><div><h1>{title}</h1><p>{description}</p></div></div></div>{action}</div></header>
            <main className="public-container adm-content">{children}</main>
        </div>
    </PublicShell>;
}
