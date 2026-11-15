// Detail prestasi mahasiswa: data capaian, sertifikat, dan aksi verifikasi.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft, Award, CalendarDays, CheckCircle2, FileText, GraduationCap,
    Pencil, Star, Trash2, Trophy, User, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { StudentAffairsModal, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; verify: boolean };
    achievement: {
        id: number;
        title: string;
        category: string;
        categoryLabel: string;
        categoryTone: string;
        level: string;
        levelLabel: string;
        levelTone: string;
        rank: string;
        rankLabel: string;
        rankTone: string;
        achievedAt: string | null;
        organizer: string | null;
        description: string | null;
        status: string;
        statusLabel: string;
        statusTone: string;
        isFeatured: boolean;
        rejectionReason: string | null;
        verifiedBy: string | null;
        verifiedAt: string | null;
        certificateUrl: string | null;
        certificateName: string | null;
        createdAt: string | null;
        updatedAt: string | null;
        student: { id: number | null; name: string; nim: string | null; program: string | null };
        organization: { id: number; name: string } | null;
    };
    urls: {
        index: string;
        edit: string;
        delete: string;
        verify: string;
        reject: string;
        feature: string;
        certificate: string | null;
    };
};

export default function AchievementShow({ shell, can, achievement, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState(achievement.rejectionReason ?? '');
    const [processing, setProcessing] = useState(false);

    const verify = () => {
        setProcessing(true);
        router.post(urls.verify, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const reject = (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.post(urls.reject, { rejection_reason: reason }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setRejecting(false);
            },
        });
    };

    const toggleFeatured = () => {
        router.post(urls.feature, {}, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${achievement.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Trophy}
                        eyebrow="Kemahasiswaan"
                        title={achievement.title}
                        description={`${achievement.student.name}${achievement.student.nim ? ` · ${achievement.student.nim}` : ''}${achievement.student.program ? ` · ${achievement.student.program}` : ''}`}
                        badges={[
                            achievement.categoryLabel,
                            achievement.levelLabel,
                            achievement.rankLabel,
                            achievement.statusLabel,
                        ]}
                        actions={(
                            <>
                                <a className="db-btn ghost" href={urls.index}><ArrowLeft size={15} /> Daftar</a>
                                {can.update && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>}
                            </>
                        )}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Award size={15} /> Rincian Prestasi</h2>
                                    <span className={`db-badge ${toneClass(achievement.statusTone)}`}>{achievement.statusLabel}</span>
                                </div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 16 }}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                        <span className={`db-badge ${toneClass(achievement.categoryTone)}`}>{achievement.categoryLabel}</span>
                                        <span className={`db-badge ${toneClass(achievement.levelTone)}`}>{achievement.levelLabel}</span>
                                        <span className={`db-badge ${toneClass(achievement.rankTone)}`}>{achievement.rankLabel}</span>
                                        {achievement.isFeatured && <span className="db-badge gold"><Star size={11} /> Sorotan publik</span>}
                                    </div>

                                    <div className="db-facts">
                                        <div className="db-fact">
                                            <span className="db-hint">Tanggal capaian</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{achievement.achievedAt || '—'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Penyelenggara</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{achievement.organizer || '—'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Organisasi</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{achievement.organization?.name || 'Prestasi individu'}</b>
                                        </div>
                                        <div className="db-fact">
                                            <span className="db-hint">Diverifikasi oleh</span>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{achievement.verifiedBy || 'Belum diverifikasi'}</b>
                                        </div>
                                        <div className="db-fact wide">
                                            <span className="db-hint">Deskripsi</span>
                                            <p style={{ margin: '4px 0 0', color: 'var(--db-ink)', fontSize: 12.5, lineHeight: 1.7 }}>
                                                {achievement.description || 'Belum ada deskripsi prestasi.'}
                                            </p>
                                        </div>
                                        {achievement.rejectionReason && (
                                            <div className="db-fact wide" style={{ borderColor: 'var(--db-red)' }}>
                                                <span className="db-hint">Alasan penolakan</span>
                                                <p style={{ margin: '4px 0 0', color: 'var(--db-red)', fontSize: 12.5, lineHeight: 1.7 }}>
                                                    {achievement.rejectionReason}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileText size={15} /> Sertifikat</h2>
                                    {achievement.certificateUrl && <span className="db-badge green">Tersedia</span>}
                                </div>
                                <div className="db-card-body">
                                    {achievement.certificateUrl ? (
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                                            <span className="db-stat-icon" style={{ width: 42, height: 42, borderRadius: 12 }}><FileText size={19} /></span>
                                            <div style={{ flex: 1, minWidth: 200 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>
                                                    {achievement.certificateName || 'Sertifikat prestasi'}
                                                </b>
                                                <span className="db-hint">Dokumen pendukung capaian mahasiswa.</span>
                                            </div>
                                            <a className="db-btn ghost" href={achievement.certificateUrl} target="_blank" rel="noopener noreferrer">
                                                <FileText size={14} /> Unduh
                                            </a>
                                        </div>
                                    ) : (
                                        <span className="db-hint">Belum ada sertifikat yang diunggah.</span>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><User size={15} /> Mahasiswa</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <b style={{ color: 'var(--db-heading)', fontSize: 14 }}>{achievement.student.name}</b>
                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                        <GraduationCap size={13} /> {achievement.student.nim || 'Tanpa NIM'}
                                    </span>
                                    <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                        <GraduationCap size={13} /> {achievement.student.program || 'Program studi tidak tersedia'}
                                    </span>
                                </div>
                            </section>

                            {can.verify && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title">Verifikasi</h2></div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                        <button className="db-btn primary" type="button" onClick={verify} disabled={processing}>
                                            <CheckCircle2 size={14} /> Verifikasi Prestasi
                                        </button>
                                        <button className="db-btn danger" type="button" onClick={() => setRejecting(true)} disabled={processing}>
                                            <XCircle size={14} /> Tolak Prestasi
                                        </button>
                                        {achievement.status === 'verified' && can.update && (
                                            <button className="db-btn ghost" type="button" onClick={toggleFeatured}>
                                                <Star size={14} /> {achievement.isFeatured ? 'Keluarkan dari Sorotan' : 'Jadikan Sorotan'}
                                            </button>
                                        )}
                                        <span className="db-hint">
                                            Verifikasi membuat prestasi tampil di halaman publik Prestasi Mahasiswa.
                                        </span>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><CalendarDays size={15} /> Riwayat Data</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                                    <span className="db-hint">Dibuat: {achievement.createdAt || '—'}</span>
                                    <span className="db-hint">Diperbarui: {achievement.updatedAt || '—'}</span>
                                    <span className="db-hint">Diverifikasi: {achievement.verifiedAt || '—'}</span>
                                </div>
                            </section>

                            {can.delete && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title">Zona Hati-hati</h2></div>
                                    <div className="db-card-body">
                                        <button className="db-btn danger" type="button" onClick={() => setConfirmDelete(true)}>
                                            <Trash2 size={14} /> Hapus Prestasi
                                        </button>
                                    </div>
                                </section>
                            )}
                        </div>
                    </div>
                </div>

                {rejecting && (
                    <StudentAffairsModal
                        title="Tolak Prestasi"
                        description="Tuliskan alasan penolakan agar mahasiswa memahami perbaikan yang diperlukan."
                        onClose={() => setRejecting(false)}
                    >
                        <form onSubmit={reject} style={{ display: 'grid', gap: 12 }}>
                            <div>
                                <label className="crud-label" htmlFor="rejection-reason">Alasan penolakan <span>*</span></label>
                                <textarea
                                    id="rejection-reason"
                                    className="db-input"
                                    rows={3}
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                    required
                                />
                            </div>
                            <div className="db-modal-actions">
                                <button type="button" className="db-btn ghost" onClick={() => setRejecting(false)}>Batal</button>
                                <button type="submit" className="db-btn danger" disabled={processing}>Tolak Prestasi</button>
                            </div>
                        </form>
                    </StudentAffairsModal>
                )}

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus prestasi?"
                    message={`"${achievement.title}" akan dihapus beserta sertifikatnya.`}
                    confirmLabel="Ya, hapus"
                    danger
                    onConfirm={() => router.delete(urls.delete)}
                    onCancel={() => setConfirmDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
