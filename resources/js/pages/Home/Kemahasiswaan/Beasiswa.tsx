// Public scholarship (beasiswa) page.
import { Head } from '@inertiajs/react';
import { Award, Building2, Coins, FileText, Handshake, Landmark, LucideIcon, Send, Wallet } from 'lucide-react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Scholarship = { id: number; name: string; type: string; description: string; requirements: string; discount: string; duration: number | null };
type Props = CommunityBaseProps & { scholarships: Scholarship[] };

const typeMeta: Record<string, { icon: LucideIcon; tone: string; label: string }> = {
    internal: { icon: Award, tone: '', label: 'Internal' },
    government: { icon: Landmark, tone: 'red', label: 'Pemerintah' },
    corporate: { icon: Building2, tone: 'green', label: 'Korporasi' },
    foundation: { icon: Handshake, tone: 'amber', label: 'Yayasan' },
};
const resolveType = (type: string): { icon: LucideIcon; tone: string; label: string } =>
    typeMeta[type] ?? { icon: Wallet, tone: 'gray', label: type.replace(/_/g, ' ') };

export default function Beasiswa({ campus, links, user, scholarships }: Props) {
    const sources = new Set(scholarships.map((scholarship) => scholarship.type));
    const longest = scholarships.reduce<number | null>((max, scholarship) => scholarship.duration === null ? max : max === null || scholarship.duration > max ? scholarship.duration : max, null);

    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Beasiswa" eyebrow="Program Beasiswa" title="Beasiswa & keringanan biaya pendidikan." description="Kami berkomitmen memberikan akses pendidikan tinggi terbaik bagi seluruh mahasiswa berprestasi dan yang membutuhkan bantuan finansial." icon={Wallet}
        action={<div className="adm-hero-cta"><a className="adm-btn light" href="/admission/apply"><Send size={14} /> Daftar Sekarang</a><a className="adm-btn outline" href={links.tuition}><Coins size={14} /> Cek Biaya UKT</a></div>}>
        <Head title={`Beasiswa · ${campus.name}`} />

        <StatStrip
            items={[
                { icon: Wallet, value: scholarships.length, label: 'Program Aktif' },
                { icon: Handshake, value: sources.size, label: 'Sumber Pendanaan', tone: 'green' },
                { icon: Coins, value: longest === null ? '–' : longest, label: 'Durasi Terlama (Semester)', tone: 'gold' },
            ]}
        />

        {scholarships.length > 0 ? <>
            <div className="adm-grid adm-cols-2">
                {scholarships.map((scholarship) => {
                    const meta = resolveType(scholarship.type);
                    const Icon = meta.icon;
                    return <article className="adm-card" key={scholarship.id}>
                        <div className="adm-card-head">
                            <div style={{ display: 'flex', alignItems: 'center', gap: 13 }}>
                                <span className={`adm-stat-icon ${meta.tone}`} style={{ width: 44, height: 44, borderRadius: 13 }}><Icon size={21} /></span>
                                <div>
                                    <h2 className="adm-card-title">{scholarship.name}</h2>
                                    <span className={`adm-badge ${meta.tone}`}>{meta.label}</span>
                                </div>
                            </div>
                            <span className="adm-badge solid">Tersedia</span>
                        </div>
                        <div className="adm-card-body">
                            <p className="adm-hint" style={{ margin: 0 }}>{scholarship.description}</p>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: 10, marginTop: 15 }}>
                                <div style={{ padding: '12px 15px', border: '1px solid var(--adm-line)', borderRadius: 10, background: 'var(--adm-soft)' }}>
                                    <div className="adm-list-row"><small>Bantuan / Potongan</small><b style={{ color: 'var(--adm-brand)' }}>{scholarship.discount}</b></div>
                                </div>
                                <div style={{ padding: '12px 15px', border: '1px solid var(--adm-line)', borderRadius: 10, background: 'var(--adm-soft)' }}>
                                    <div className="adm-list-row"><small>Durasi</small><b>{scholarship.duration === null ? '–' : `${scholarship.duration} Semester`}</b></div>
                                </div>
                            </div>
                            {scholarship.requirements.trim().length > 0 && <>
                                <hr className="adm-divider" style={{ margin: '16px 0 12px' }} />
                                <span className="adm-kicker"><FileText size={12} /> Persyaratan Umum</span>
                                <div className="adm-doc" style={{ marginTop: 9 }} dangerouslySetInnerHTML={{ __html: scholarship.requirements }} />
                            </>}
                        </div>
                    </article>;
                })}
            </div>

            <div className="adm-note gold"><Handshake size={16} /><div><b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Didukung mitra terpercaya.</b>Sebagian program disponsori pemerintah dan mitra industri terkemuka — pastikan seluruh persyaratan terpenuhi sebelum mengajukan.</div></div>
        </> : <EmptyState
            icon={Wallet}
            title="Belum Ada Program Beasiswa"
            description="Informasi program beasiswa akan segera diperbarui oleh biro kemahasiswaan. Pantau terus halaman ini agar tidak ketinggalan kesempatan."
            action={<a className="adm-btn primary" href="/admission/apply">Daftar Sekarang</a>}
        />}
    </KemahasiswaanShell>;
}
