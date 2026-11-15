// Public student organizations (organisasi kemahasiswaan) page.
import { Head } from '@inertiajs/react';
import {
    CheckCircle2, GraduationCap, HeartPulse, AtSign, Trophy, UserCheck, Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Organization = {
    id: number;
    name: string;
    type: string;
    typeLabel: string;
    typeShort: string;
    typeTone: string;
    faculty: string | null;
    studyProgram: string | null;
    advisor: string | null;
    description: string | null;
    foundedAt: string | null;
    activeMembers: number;
    logoUrl: string | null;
    instagramUrl: string | null;
    contactEmail: string | null;
};

type OrganisasiProps = CommunityBaseProps & {
    organizations: Organization[];
    stats: { organizations: number; members: number; types: number; achievements: number };
    types: Record<string, string>;
};

const toneFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
            return tone;
        case 'amber':
            return 'gold';
        default:
            return '';
    }
};

const badgeFor = (tone: string): string => {
    switch (tone) {
        case 'green':
        case 'red':
        case 'amber':
        case 'gray':
            return tone;
        default:
            return '';
    }
};

const initialsFor = (name: string): string =>
    name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

export default function Organisasi({ campus, links, user, organizations, stats, types }: OrganisasiProps) {
    const [activeType, setActiveType] = useState('all');

    const typeEntries = Object.entries(types);

    const filtered = useMemo(
        () => (activeType === 'all' ? organizations : organizations.filter((organization) => organization.type === activeType)),
        [activeType, organizations],
    );

    const grouped = useMemo(() => {
        return typeEntries
            .map(([value, label]) => ({
                value,
                label,
                items: filtered.filter((organization) => organization.type === value),
            }))
            .filter((group) => group.items.length > 0);
    }, [filtered, typeEntries]);

    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Organisasi" eyebrow="Organisasi Mahasiswa" title="Tumbuh lewat organisasi intra kampus." description="Kembangkan jiwa kepemimpinan dan jejaring Anda melalui berbagai wadah organisasi yang aktif dan dinamis." icon={Users}>
        <Head title={`Organisasi Mahasiswa · ${campus.name}`} />

        <div className="adm-hero">
            <div className="public-container">
                <div className="adm-hero-inner">
                    <div>
                        <span className="adm-crumb"><Users size={14} /> {stats.organizations} Ormawa Aktif</span>
                        <p className="adm-hero-desc" style={{ maxWidth: 560 }}>
                            Dari badan eksekutif hingga unit kegiatan seni, olahraga, dan penalaran — ada panggung untuk setiap minat dan potensi.
                        </p>
                    </div>
                    <div className="adm-hero-cta">
                        <a className="adm-btn outline" href="/kemahasiswaan/prestasi"><Trophy size={15} /> Galeri Prestasi</a>
                        <a className="adm-btn light" href="/kemahasiswaan/layanan"><HeartPulse size={15} /> Layanan Mahasiswa</a>
                    </div>
                </div>
            </div>
        </div>

        <StatStrip
            items={[
                { icon: Users, value: stats.organizations, label: 'Total Ormawa' },
                { icon: UserCheck, value: stats.members, label: 'Anggota Aktif', tone: 'green' },
                { icon: GraduationCap, value: stats.types, label: 'Jenis Organisasi' },
                { icon: Trophy, value: stats.achievements, label: 'Prestasi Terverifikasi', tone: 'gold' },
            ]}
        />

        {organizations.length === 0 ? (
            <EmptyState
                icon={Users}
                iconSize={28}
                title="Belum Ada Organisasi Terdaftar"
                description="Data organisasi kemahasiswaan akan ditampilkan di sini setelah admin menambahkan ormawa aktif."
            />
        ) : (
            <>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    <button
                        type="button"
                        className={`adm-btn ${activeType === 'all' ? '' : 'ghost'} sm`}
                        onClick={() => setActiveType('all')}
                    >
                        Semua <span className="adm-badge gray">{organizations.length}</span>
                    </button>
                    {typeEntries.map(([value, label]) => {
                        const count = organizations.filter((organization) => organization.type === value).length;

                        if (count === 0) return null;

                        return (
                            <button
                                key={value}
                                type="button"
                                className={`adm-btn ${activeType === value ? '' : 'ghost'} sm`}
                                onClick={() => setActiveType(value)}
                            >
                                {label} <span className="adm-badge gray">{count}</span>
                            </button>
                        );
                    })}
                </div>

                {grouped.map((group) => (
                    <section key={group.value} style={{ display: 'grid', gap: 12 }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
                            <h2 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 17px/1.3 'Source Serif 4', Georgia, serif" }}>
                                {group.label}
                            </h2>
                            <span className="adm-badge gray">{group.items.length} organisasi</span>
                        </div>

                        <div className="adm-grid adm-cols-3">
                            {group.items.map((organization) => (
                                <article className="adm-card" key={organization.id}>
                                    <div className="adm-card-body" style={{ display: 'grid', gap: 11, alignContent: 'start' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                            {organization.logoUrl ? (
                                                <img
                                                    src={organization.logoUrl}
                                                    alt={`Logo ${organization.name}`}
                                                    style={{ width: 46, height: 46, objectFit: 'cover', borderRadius: 13, border: '1px solid var(--adm-line)' }}
                                                />
                                            ) : (
                                                <span className={`adm-stat-icon ${toneFor(organization.typeTone)}`} style={{ width: 46, height: 46, borderRadius: 13, font: "700 14px 'Source Serif 4', Georgia, serif" }}>
                                                    {initialsFor(organization.name)}
                                                </span>
                                            )}
                                            <span className={`adm-badge ${badgeFor(organization.typeTone)}`.trim()}>
                                                {organization.typeShort}
                                            </span>
                                        </div>

                                        <div>
                                            <h3 style={{ margin: 0, color: 'var(--adm-heading)', font: "700 15px/1.35 'Source Serif 4', Georgia, serif" }}>
                                                {organization.name}
                                            </h3>
                                            <small className="adm-hint">
                                                {organization.studyProgram || organization.faculty || 'Tingkat universitas'}
                                            </small>
                                        </div>

                                        <p className="adm-hint" style={{ margin: 0 }}>
                                            {organization.description || 'Belum ada deskripsi organisasi.'}
                                        </p>

                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                            <span className="adm-badge gray"><UserCheck size={11} /> {organization.activeMembers} anggota</span>
                                            {organization.foundedAt && <span className="adm-badge gray">Berdiri {organization.foundedAt}</span>}
                                        </div>

                                        {organization.advisor && (
                                            <small className="adm-hint">Pembina: {organization.advisor}</small>
                                        )}

                                        {organization.instagramUrl && (
                                            <a
                                                className="adm-btn ghost sm"
                                                href={organization.instagramUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                style={{ justifySelf: 'start' }}
                                            >
                                                <AtSign size={13} /> Instagram
                                            </a>
                                        )}
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                ))}
            </>
        )}

        <div className="adm-note green">
            <CheckCircle2 size={16} />
            <div>
                <b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Aktif sepanjang tahun.</b>
                Seluruh ormawa aktif menyelenggarakan program kerja setiap tahun — pilih wadah yang paling sesuai dengan minat dan potensimu.
            </div>
        </div>
    </KemahasiswaanShell>;
}
