// Detail profil alumni (read-only).
import { Head, router } from '@inertiajs/react';
import { Briefcase, Globe, GraduationCap, Mail, MapPin, Pencil, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Profile = {
    id: number;
    nim: string;
    fullName: string;
    email: string;
    phone: string | null;
    gender: string | null;
    genderLabel: string | null;
    birthDate: string | null;
    graduationDate: string | null;
    graduationYear: number;
    gpa: number | null;
    faculty: string | null;
    studyProgram: string | null;
    address: string | null;
    currentCity: string | null;
    currentProvince: string | null;
    employmentStatus: string;
    employmentStatusLabel: string;
    employerName: string | null;
    jobTitle: string | null;
    jobIndustry: string | null;
    linkedinUrl: string | null;
    photoUrl: string | null;
    isActive: boolean;
    completeness: number;
    hasAccount: boolean;
    accountName: string | null;
    accountEmail: string | null;
    createdAt: string | null;
    updatedAt: string | null;
    createdBy: string | null;
    updatedBy: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    profile: Profile;
    urls: { index: string; edit: string | null; destroy: string };
};

function initials(name: string) {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function Detail({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <div style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{value ?? '-'}</div>
        </div>
    );
}

export default function AlumniProfileShow({ shell, can, profile, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${profile.fullName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Alumni · Detail"
                        title={profile.fullName}
                        description={`${profile.nim} · ${profile.studyProgram ?? '-'} · ${profile.faculty ?? '-'}`}
                        badges={[
                            profile.employmentStatusLabel,
                            `${profile.completeness}% lengkap`,
                            profile.isActive ? 'Aktif' : 'Nonaktif',
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && urls.edit && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>
                                )}
                            </>
                        )}
                    />

                    <div className="cx-form-split">
                        <div className="db-stack cx-sticky">
                            <section className="db-card">
                                <div className="db-card-body" style={{ textAlign: 'center' }}>
                                    {profile.photoUrl ? (
                                        <img
                                            src={profile.photoUrl}
                                            alt={profile.fullName}
                                            style={{ width: 112, height: 112, borderRadius: '50%', objectFit: 'cover', margin: '0 auto 14px', display: 'block' }}
                                        />
                                    ) : (
                                        <span
                                            aria-hidden
                                            style={{
                                                width: 112, height: 112, borderRadius: '50%', margin: '0 auto 14px',
                                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                                background: 'var(--db-brand-soft)', color: 'var(--db-brand)',
                                                fontSize: 32, fontWeight: 800,
                                            }}
                                        >
                                            {initials(profile.fullName)}
                                        </span>
                                    )}
                                    <h3 style={{ margin: 0, color: 'var(--db-heading)' }}>{profile.fullName}</h3>
                                    <p className="db-hint" style={{ margin: '4px 0 12px' }}>{profile.nim} · {profile.studyProgram ?? '-'}</p>
                                    <span className={`db-badge ${profile.isActive ? 'green' : 'gray'}`}>
                                        {profile.isActive ? 'Keanggotaan aktif' : 'Keanggotaan nonaktif'}
                                    </span>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body">
                                    <h3 className="db-card-title" style={{ marginBottom: 8 }}>Kelengkapan Profil</h3>
                                    <div
                                        role="progressbar"
                                        aria-valuenow={profile.completeness}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        style={{ height: 10, borderRadius: 99, background: 'var(--db-soft)', overflow: 'hidden' }}
                                    >
                                        <span style={{ display: 'block', height: '100%', width: `${profile.completeness}%`, background: 'var(--db-brand)' }} />
                                    </div>
                                    <div className="db-hint" style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8 }}>
                                        <span>Status data</span>
                                        <b>{profile.completeness}% lengkap</b>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body">
                                    <h3 className="db-card-title" style={{ marginBottom: 8 }}>Akun Pengguna</h3>
                                    {profile.hasAccount ? (
                                        <div className="db-stack" style={{ gap: 6 }}>
                                            <div><b>{profile.accountName ?? '-'}</b></div>
                                            <div className="db-hint">{profile.accountEmail ?? '-'}</div>
                                        </div>
                                    ) : (
                                        <p className="db-hint" style={{ margin: 0 }}>Profil ini belum tertaut ke akun pengguna.</p>
                                    )}
                                    <div className="db-hint" style={{ marginTop: 12, lineHeight: 1.65 }}>
                                        Dibuat {profile.createdAt ?? '-'}{profile.createdBy ? ` oleh ${profile.createdBy}` : ''}.<br />
                                        Diubah {profile.updatedAt ?? '-'}{profile.updatedBy ? ` oleh ${profile.updatedBy}` : ''}.
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><GraduationCap size={16} /> Informasi Pribadi & Akademik</h2>
                                    {can.delete && (
                                        <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                            <Trash2 size={14} /> Hapus
                                        </button>
                                    )}
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <Detail label="NIM" value={profile.nim} />
                                        <Detail label="Nama lengkap" value={profile.fullName} />
                                        <Detail label="Jenis kelamin" value={profile.genderLabel} />
                                        <Detail label="Tanggal lahir" value={profile.birthDate} />
                                        <Detail label="Fakultas" value={profile.faculty} />
                                        <Detail label="Program studi" value={profile.studyProgram} />
                                        <Detail label="Tanggal lulus" value={profile.graduationDate} />
                                        <Detail label="Tahun lulus" value={profile.graduationYear} />
                                        <Detail label="IPK akhir" value={profile.gpa !== null ? profile.gpa.toFixed(2) : null} />
                                        <Detail label="Email" value={profile.email} />
                                        <Detail label="Telepon" value={profile.phone} />
                                        <Detail label="Domisili" value={[profile.currentCity, profile.currentProvince].filter(Boolean).join(', ') || null} />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <span className="crud-label">Alamat lengkap</span>
                                            <div style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{profile.address || 'Alamat belum tercatat.'}</div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Briefcase size={16} /> Status Kepekerjaan & Karir</h2>
                                    <span className={`db-badge ${profile.employmentStatus === 'working' ? 'green' : profile.employmentStatus === 'entrepreneur' ? 'amber' : 'gray'}`}>
                                        {profile.employmentStatusLabel}
                                    </span>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <Detail label="Perusahaan / instansi" value={profile.employerName} />
                                        <Detail label="Jabatan / posisi" value={profile.jobTitle} />
                                        <Detail label="Bidang industri" value={profile.jobIndustry} />
                                        <div>
                                            <span className="crud-label">LinkedIn</span>
                                            {profile.linkedinUrl ? (
                                                <a href={profile.linkedinUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm" style={{ display: 'inline-flex' }}>
                                                    <Globe size={14} /> Lihat profil
                                                </a>
                                            ) : (
                                                <div className="db-hint">-</div>
                                            )}
                                        </div>
                                        <div>
                                            <span className="crud-label">Kontak cepat</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                                <a className="db-btn ghost sm" href={`mailto:${profile.email}`}><Mail size={13} /> Email</a>
                                                {profile.phone && <a className="db-btn ghost sm" href={`tel:${profile.phone}`}><Phone size={13} /> Telepon</a>}
                                                {profile.currentCity && <span className="db-btn ghost sm"><MapPin size={13} /> {profile.currentCity}</span>}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus profil alumni?"
                    message={`"${profile.fullName}" dipindah ke sampah. Foto profil ikut dihapus saat data dibersihkan permanen.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={() => {
                        setProcessing(true);
                        router.delete(urls.destroy, {
                            onFinish: () => {
                                setProcessing(false);
                                setConfirmDelete(false);
                            },
                        });
                    }}
                    onCancel={() => setConfirmDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
