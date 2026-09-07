// Home admission shell.
import { Link } from '@inertiajs/react';
import { ClipboardList, Coins, GraduationCap, HelpCircle, LucideIcon, Search, Send } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../PublicShell';
import '../../../../css/academic.css';
import '../../../../css/academic-overrides.css';
import '../../../../css/admission-public.css';

export type AdmissionUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
export type AdmissionBaseProps = { campus: Campus; links: PublicLinks; user: AdmissionUser };

type Props = AdmissionBaseProps & {
    eyebrow: string;
    title: string;
    description: string;
    icon: LucideIcon;
    children: ReactNode;
    action?: ReactNode;
    activeTab?: string;
};

const tabs = [
    { label: 'Pendaftaran', href: '/admission/apply', icon: Send },
    { label: 'Cek Status', href: '/admission/status', icon: Search },
    { label: 'Syarat', href: '/admission/requirements', icon: ClipboardList },
    { label: 'Biaya UKT', href: '/admission/tuition', icon: Coins },
    { label: 'FAQ', href: '/admission/faq', icon: HelpCircle },
];

export function AdmissionShell({ campus, links, user, eyebrow, title, description, icon: Icon, children, action, activeTab = eyebrow }: Props) {
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const searchResults = useMemo(() => tabs.filter((tab) => tab.label.toLowerCase().includes(query.toLowerCase())), [query]);

    return <PublicShell campus={campus} links={links} user={user} activeNav="Penerimaan">
        <div className="academic-page adm-root">
            <div className="academic-subnav"><div className="public-container academic-subnav-inner"><div className="academic-subnav-title"><GraduationCap size={16} /><span>Portal Penerimaan</span></div><nav aria-label="Navigasi penerimaan">{tabs.map(({ label, href, icon: TabIcon }) => <Link href={href} key={label} className={label === activeTab ? 'active' : ''}><TabIcon size={14} />{label}</Link>)}</nav><div className="academic-search-wrap"><button type="button" className="academic-search" onClick={() => setSearchOpen(!searchOpen)} aria-label="Cari informasi penerimaan" aria-expanded={searchOpen}><Search size={15} /></button>{searchOpen && <div className="academic-search-popover"><label htmlFor="admission-search-input">Cari halaman penerimaan</label><input id="admission-search-input" autoFocus value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Contoh: biaya" />{query && (searchResults.length ? searchResults.map(({ label, href, icon: ResultIcon }) => <Link href={href} key={label} onClick={() => { setSearchOpen(false); setQuery(''); }}><ResultIcon size={13} />{label}</Link>) : <span className="academic-search-empty">Tidak ada halaman yang cocok.</span>)}</div>}</div></div></div>
            <header className="academic-heading"><div className="public-container academic-heading-inner"><div className="academic-heading-copy"><span className="academic-breadcrumb">Penerimaan <span>/</span> {eyebrow}</span><div className="academic-title-row"><span className="academic-title-icon"><Icon size={25} /></span><div><h1>{title}</h1><p>{description}</p></div></div></div>{action}</div></header>
            <main className="public-container academic-content">{children}</main>
        </div>
    </PublicShell>;
}
