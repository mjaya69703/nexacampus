// Detail lowongan kerja alumni (read-only).
import { Head, router } from '@inertiajs/react';
import { Briefcase, Building2, CalendarClock, ExternalLink, Mail, MapPin, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Job = {
    id: number;
    title: string;
    companyName: string;
    partnerName: string | null;
    industry: string | null;
    description: string;
    requirements: string | null;
    location: string | null;
    jobType: string | null;
    jobTypeLabel: string | null;
    salaryRange: string | null;
    applyUrl: string | null;
    contactEmail: string | null;
    postedDate: string | null;
    deadlineDate: string | null;
    isExpired: boolean;
    isActive: boolean;
    createdAt: string | null;
    updatedAt: string | null;
    createdBy: string | null;
    updatedBy: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    job: Job;
    urls: { index: string; edit: string | null; destroy: string };
};

function Detail({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <div style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{value ?? '-'}</div>
        </div>
    );
}

export default function JobPostingShow({ shell, can, job, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${job.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Briefcase}
                        eyebrow="Alumni · Lowongan"
                        title={job.title}
                        description={`${job.companyName}${job.location ? ` · ${job.location}` : ''}${job.jobTypeLabel ? ` · ${job.jobTypeLabel}` : ''}`}
                        badges={[
                            job.isActive ? 'Aktif' : 'Nonaktif',
                            job.isExpired ? 'Lewat deadline' : 'Masih terbuka',
                            job.deadlineDate ? `Deadline ${job.deadlineDate}` : 'Tanpa deadline',
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
                                    <span
                                        aria-hidden
                                        style={{
                                            width: 88, height: 88, borderRadius: 14, margin: '0 auto 14px',
                                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                                            background: 'var(--db-brand-soft)', color: 'var(--db-brand)',
                                        }}
                                    >
                                        <Building2 size={36} />
                                    </span>
                                    <h3 style={{ margin: 0, color: 'var(--db-heading)' }}>{job.companyName}</h3>
                                    <p className="db-hint" style={{ margin: '4px 0 12px' }}>
                                        {job.industry ?? 'Sektor umum'}{job.location ? ` · ${job.location}` : ''}
                                    </p>
                                    <span className={`db-badge ${job.isActive ? 'green' : 'gray'}`}>
                                        {job.isActive ? 'Lowongan terbuka & aktif' : 'Lowongan ditutup / nonaktif'}
                                    </span>
                                    {job.applyUrl && (
                                        <div style={{ marginTop: 14 }}>
                                            <a className="db-btn primary" href={job.applyUrl} target="_blank" rel="noreferrer" style={{ display: 'flex', justifyContent: 'center' }}>
                                                <ExternalLink size={14} /> Kunjungi portal lamaran
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body">
                                    <h3 className="db-card-title" style={{ marginBottom: 8 }}>Verifikasi Rekrutmen</h3>
                                    <p className="db-hint" style={{ margin: 0, lineHeight: 1.7 }}>
                                        Publikasi lowongan pada portal resmi kampus disarankan melalui verifikasi pengelola alumni untuk menghindari penipuan rekrutmen.
                                    </p>
                                    <div className="db-hint" style={{ marginTop: 12 }}>
                                        Dibuat {job.createdAt ?? '-'}{job.createdBy ? ` oleh ${job.createdBy}` : ''}.<br />
                                        Diubah {job.updatedAt ?? '-'}{job.updatedBy ? ` oleh ${job.updatedBy}` : ''}.
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Briefcase size={16} /> Informasi Lowongan</h2>
                                    {can.delete && (
                                        <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                            <Trash2 size={14} /> Hapus
                                        </button>
                                    )}
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <Detail label="Judul posisi" value={job.title} />
                                        <Detail label="Nama perusahaan" value={job.companyName} />
                                        <Detail label="Mitra terhubung" value={job.partnerName} />
                                        <Detail label="Sektor industri" value={job.industry} />
                                        <Detail label="Tipe pekerjaan" value={job.jobTypeLabel} />
                                        <Detail label="Lokasi penempatan" value={job.location} />
                                        <Detail label="Rentang gaji" value={job.salaryRange} />
                                        <Detail label="Tanggal publikasi" value={job.postedDate} />
                                        <div>
                                            <span className="crud-label">Deadline lamaran</span>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                <b style={{ color: 'var(--db-heading)' }}>{job.deadlineDate ?? '-'}</b>
                                                <span className={`db-badge ${job.isExpired ? 'red' : 'green'}`}>{job.isExpired ? 'Lewat' : 'Terbuka'}</span>
                                            </div>
                                        </div>
                                        <Detail label="Email pengiriman CV" value={job.contactEmail} />
                                        <div>
                                            <span className="crud-label">Kontak cepat</span>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                                {job.contactEmail && <a className="db-btn ghost sm" href={`mailto:${job.contactEmail}`}><Mail size={13} /> Email</a>}
                                                {job.applyUrl && <a className="db-btn ghost sm" href={job.applyUrl} target="_blank" rel="noreferrer"><ExternalLink size={13} /> Lamar</a>}
                                                {job.location && <span className="db-btn ghost sm"><MapPin size={13} /> {job.location}</span>}
                                                {job.deadlineDate && <span className="db-btn ghost sm"><CalendarClock size={13} /> {job.deadlineDate}</span>}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Deskripsi Pekerjaan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ color: 'var(--db-ink)', lineHeight: 1.8, whiteSpace: 'pre-line' }}>
                                        {job.description}
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title">Kualifikasi & Persyaratan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ color: 'var(--db-ink)', lineHeight: 1.8, whiteSpace: 'pre-line' }}>
                                        {job.requirements || 'Tidak ada persyaratan khusus tertulis.'}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus lowongan kerja?"
                    message={`"${job.title}" dipindah ke sampah dan tidak tampil di bursa kerja alumni.`}
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
