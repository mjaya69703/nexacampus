// Profil alumni — ringkasan data pribadi, akademik, dan karir (Inertia React).
import { Head } from '@inertiajs/react';
import {
    Briefcase, Building2, GraduationCap, Mail, MapPin, Pencil, Phone,
    UserRound, UserRoundX,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Profile = {
    nim: string;
    fullName: string;
    email: string;
    phone: string | null;
    birthDate: string | null;
    gender: string | null;
    address: string | null;
    currentCity: string | null;
    currentProvince: string | null;
    faculty: string | null;
    studyProgram: string | null;
    graduationYear: number | null;
    graduationDate: string | null;
    gpa: string | null;
    employmentStatus: string;
    employmentStatusLabel: string;
    employerName: string | null;
    jobTitle: string | null;
    jobIndustry: string | null;
    linkedinUrl: string | null;
    isActive: boolean;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    profile: Profile | null;
    completeness: number;
    urls: { index: string; edit: string; dashboard: string; jobs: string };
};

function DetailRow({ label, value }: { label: string; value: string | number | null }) {
    return (
        <tr>
            <td className="db-hint" style={{ width: '42%', padding: '9px 0', borderBottom: '1px solid var(--db-line)' }}>{label}</td>
            <td style={{ padding: '9px 0', borderBottom: '1px solid var(--db-line)', color: 'var(--db-heading)', fontWeight: 600 }}>
                {value !== null && value !== '' ? value : '-'}
            </td>
        </tr>
    );
}

export default function ProfileIndex({ shell, hasProfile, profile, completeness, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Profil Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UserRound}
                        eyebrow="Portal alumni"
                        title="Profil Saya"
                        description="Lihat dan kelola data profil alumni kamu agar informasi karir tetap mutakhir."
                        badges={profile ? [profile.nim, profile.employmentStatusLabel] : undefined}
                        actions={hasProfile ? (
                            <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit profil</a>
                        ) : undefined}
                    />

                    {!hasProfile || !profile ? (
                        <section className="db-card">
                            <div className="db-card-body" style={{ display: 'grid', justifyItems: 'center', gap: 10, padding: '48px 24px', textAlign: 'center' }}>
                                <span className="db-stat-icon red"><UserRoundX size={22} /></span>
                                <h2 style={{ margin: 0, color: 'var(--db-heading)', fontSize: 18 }}>Profil Alumni Belum Tersedia</h2>
                                <p className="db-hint" style={{ margin: 0, maxWidth: 420 }}>
                                    Hubungi administrator atau bagian alumni untuk mengaktifkan profil alumni kamu.
                                </p>
                                <a className="db-btn ghost" href={urls.dashboard}>Kembali ke dashboard</a>
                            </div>
                        </section>
                    ) : (
                        <>
                            <section className="db-card">
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                                        <span style={{ color: 'var(--db-heading)', fontSize: 13, fontWeight: 700 }}>Kelengkapan Profil</span>
                                        <span className={`db-badge ${completeness >= 100 ? 'green' : completeness >= 50 ? 'amber' : 'gray'}`}>
                                            {completeness}%
                                        </span>
                                    </div>
                                    <div className="db-progress"><i style={{ width: `${Math.min(100, completeness)}%` }} /></div>
                                    <p className="db-hint" style={{ margin: '8px 0 0' }}>
                                        {completeness >= 100
                                            ? 'Profil sudah lengkap. Data yang lengkap membuka lebih banyak peluang.'
                                            : 'Lengkapi telepon, kota domisili, status kerja, dan LinkedIn untuk mencapai 100%.'}
                                    </p>
                                    {completeness < 100 && (
                                        <a className="db-btn primary sm" style={{ marginTop: 10 }} href={urls.edit}>
                                            <Pencil size={13} /> Lengkapi profil
                                        </a>
                                    )}
                                </div>
                            </section>

                            <div className="db-grid-2">
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><UserRound size={16} /> Data Pribadi</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <table className="db-table" style={{ width: '100%' }}>
                                            <tbody>
                                                <DetailRow label="NIM" value={profile.nim} />
                                                <DetailRow label="Nama lengkap" value={profile.fullName} />
                                                <DetailRow label="Email" value={profile.email} />
                                                <DetailRow label="No. telepon" value={profile.phone} />
                                                <DetailRow label="Tanggal lahir" value={profile.birthDate} />
                                                <DetailRow label="Jenis kelamin" value={profile.gender} />
                                                <DetailRow label="Alamat" value={profile.address} />
                                                <DetailRow label="Kota" value={profile.currentCity} />
                                                <DetailRow label="Provinsi" value={profile.currentProvince} />
                                            </tbody>
                                        </table>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 14 }}>
                                            {profile.phone && <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><Phone size={13} /> {profile.phone}</span>}
                                            {profile.email && <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><Mail size={13} /> {profile.email}</span>}
                                            {(profile.currentCity || profile.currentProvince) && (
                                                <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                    <MapPin size={13} /> {[profile.currentCity, profile.currentProvince].filter(Boolean).join(', ')}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </section>

                                <div className="db-stack">
                                    <section className="db-card">
                                        <div className="db-card-head">
                                            <h2 className="db-card-title"><GraduationCap size={16} /> Data Akademik</h2>
                                        </div>
                                        <div className="db-card-body">
                                            <table className="db-table" style={{ width: '100%' }}>
                                                <tbody>
                                                    <DetailRow label="Fakultas" value={profile.faculty} />
                                                    <DetailRow label="Program studi" value={profile.studyProgram} />
                                                    <DetailRow label="Tahun lulus" value={profile.graduationYear} />
                                                    <DetailRow label="Tanggal lulus" value={profile.graduationDate} />
                                                    <DetailRow label="IPK" value={profile.gpa} />
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <section className="db-card">
                                        <div className="db-card-head">
                                            <h2 className="db-card-title"><Briefcase size={16} /> Data Karir</h2>
                                            <span className="db-badge green">{profile.employmentStatusLabel}</span>
                                        </div>
                                        <div className="db-card-body">
                                            <table className="db-table" style={{ width: '100%' }}>
                                                <tbody>
                                                    <DetailRow label="Status kerja" value={profile.employmentStatusLabel} />
                                                    <DetailRow label="Perusahaan" value={profile.employerName} />
                                                    <DetailRow label="Jabatan" value={profile.jobTitle} />
                                                    <DetailRow label="Industri" value={profile.jobIndustry} />
                                                </tbody>
                                            </table>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 12 }}>
                                                {profile.employerName && (
                                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                        <Building2 size={13} /> {profile.employerName}
                                                    </span>
                                                )}
                                                {profile.linkedinUrl && (
                                                    <a className="db-btn ghost sm" href={profile.linkedinUrl} target="_blank" rel="noreferrer">
                                                        LinkedIn
                                                    </a>
                                                )}
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
