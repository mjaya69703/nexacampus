// Detail pendaftar PMB — review, verifikasi dokumen, nilai, dan konversi.
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ExternalLink, FileText, GraduationCap, History, Star, Trash2, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Document = {
    id: number; label: string; fileName: string; fileSizeKb: number;
    verificationStatus: string; verificationNotes: string | null;
    verifiedBy: string | null; verifiedAt: string | null;
    previewUrl: string; verifyUrl: string; isImage: boolean; isPdf: boolean;
};

type Participant = {
    id: number; title: string | null; examType: string | null; examDate: string | null;
    examTime: string | null; attendanceStatus: string;
};

type Score = {
    id: number; scoreType: string; schedule: string | null; score: string | number;
    weight: string | number; scorer: string | null; notes: string | null;
};

type HistoryRow = {
    id: number; fromStatus: string | null; toStatus: string; notes: string | null;
    changedBy: string; createdAt: string | null;
};

type Application = {
    id: number; applicationNumber: string; fullName: string; email: string; phone: string;
    birthDate: string | null; gender: string; genderLabel: string; address: string | null;
    emergencyContactName: string | null; emergencyContactPhone: string | null;
    highSchoolName: string | null; highSchoolMajor: string | null; highSchoolGraduationYear: number | null;
    period: string | null; academicYear: string | null; faculty: string | null; studyProgram: string | null;
    classType: string; status: string; statusLabel: string; finalScore: string | number | null;
    reviewNotes: string | null; submittedAt: string | null; reviewedAt: string | null;
    reviewedBy: string | null; acceptedAt: string | null; convertedAt: string | null; nim: string | null;
    portalUrl: string; acceptanceLetterUrl: string | null;
    documents: Document[]; examParticipants: Participant[]; scores: Score[]; histories: HistoryRow[];
    review: { status: string; review_notes: string; final_score: string | number | null };
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; convert: boolean; delete: boolean };
    application: Application;
    nimPreview: string | null;
    statusOptions: { id: string; label: string }[];
    documentStatuses: string[];
    urls: { index: string; updateReview: string; convert: string; delete: string };
};

function statusBadge(status: string | null) {
    switch (status) {
        case 'accepted':
        case 'verified':
        case 'present':
            return 'green';
        case 'rejected':
        case 'absent':
            return 'red';
        case 'under_review':
        case 'waitlisted':
        case 'pending':
            return 'amber';
        default:
            return 'gray';
    }
}

function InfoItem({ label, value }: { label: string; value: string | number | null }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <div>{value ?? '-'}</div>
        </div>
    );
}

function DocumentCard({ document, canUpdate }: { document: Document; canUpdate: boolean }) {
    const [notes, setNotes] = useState(document.verificationNotes ?? '');
    const [processing, setProcessing] = useState(false);

    const verify = (status: string) => {
        setProcessing(true);
        router.patch(document.verifyUrl, { status, notes }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div style={{ border: '1px solid var(--db-line)', borderRadius: 12, padding: 14, marginBottom: 12 }}>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 10 }}>
                <div>
                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{document.label}</b>
                    <small className="db-hint"><FileText size={12} /> {document.fileName} • {document.fileSizeKb} KB</small>
                </div>
                <span className={`db-badge ${statusBadge(document.verificationStatus)}`}>{document.verificationStatus}</span>
            </div>

            {document.isImage && (
                <div style={{ textAlign: 'center', marginBottom: 10 }}>
                    <a href={document.previewUrl} target="_blank" rel="noreferrer">
                        <img
                            src={document.previewUrl}
                            alt={document.fileName}
                            style={{ maxHeight: 280, maxWidth: '100%', borderRadius: 8, border: '1px solid var(--db-line)' }}
                        />
                    </a>
                </div>
            )}

            {document.isPdf && (
                <iframe
                    src={document.previewUrl}
                    title={document.fileName}
                    style={{ width: '100%', height: 320, border: '1px solid var(--db-line)', borderRadius: 8, marginBottom: 10 }}
                />
            )}

            {canUpdate && (
                <>
                    <input
                        className="db-input"
                        placeholder="Catatan verifikator (alasan penolakan / keterangan tambahan)"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        aria-label={`Catatan verifikasi ${document.label}`}
                    />
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 8 }}>
                        <a className="db-btn ghost sm" href={document.previewUrl} target="_blank" rel="noreferrer">
                            <ExternalLink size={13} /> Buka Penuh
                        </a>
                        <button className="db-btn primary sm" type="button" onClick={() => verify('verified')} disabled={processing}>
                            Verifikasi Sah
                        </button>
                        <button className="db-btn danger sm" type="button" onClick={() => verify('rejected')} disabled={processing}>
                            Tolak / Tidak Sah
                        </button>
                        <button className="db-btn ghost sm" type="button" onClick={() => verify('pending')} disabled={processing}>
                            Reset Status
                        </button>
                    </div>
                </>
            )}

            {document.verifiedBy && (
                <small className="db-hint" style={{ display: 'block', marginTop: 8 }}>
                    Diverifikasi oleh {document.verifiedBy} {document.verifiedAt ? `• ${document.verifiedAt}` : ''}
                </small>
            )}
        </div>
    );
}

export default function AdmissionApplicationShow({ shell, can, application, nimPreview, statusOptions, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [converting, setConverting] = useState(false);

    const review = useForm({
        status: application.review.status,
        review_notes: application.review.review_notes,
        final_score: application.review.final_score ?? '',
    });

    const submitReview = (event: FormEvent) => {
        event.preventDefault();
        review.put(urls.updateReview, { preserveScroll: true });
    };

    const convert = () => {
        setConverting(true);
        router.post(urls.convert, {}, {
            preserveScroll: true,
            onFinish: () => setConverting(false),
        });
    };

    const verifiedDocuments = application.documents.filter((document) => document.verificationStatus === 'verified').length;

    return (
        <AdminShell shell={shell}>
            <Head title={`${application.fullName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UserRound}
                        eyebrow="Admission • Pendaftar"
                        title={application.fullName}
                        description={`${application.applicationNumber} • ${application.period ?? 'Periode tidak diketahui'} • ${application.studyProgram ?? 'Program studi belum dipilih'}`}
                        badges={[application.statusLabel, `${verifiedDocuments}/${application.documents.length} dokumen terverifikasi`]}
                        actions={(
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                <a className="db-btn light" href={application.portalUrl} target="_blank" rel="noreferrer">
                                    <ExternalLink size={14} /> Portal Pendaftar
                                </a>
                                <a className="db-btn ghost" href={urls.index} style={{ color: '#fff', borderColor: 'rgba(255,255,255,.4)' }}>
                                    <ArrowLeft size={14} /> Kembali
                                </a>
                            </div>
                        )}
                    />

                    <section className="db-stats" aria-label="Ringkasan pendaftar">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num" style={{ fontSize: 17 }}>{application.statusLabel}</span><span className="db-stat-label">Status seleksi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{verifiedDocuments}/{application.documents.length}</span><span className="db-stat-label">Dokumen terverifikasi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Star size={20} /></span>
                            <div><span className="db-stat-num">{application.finalScore ?? '-'}</span><span className="db-stat-label">Skor akhir</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><History size={20} /></span>
                            <div><span className="db-stat-num" style={{ fontSize: 16 }}>{application.submittedAt ?? '-'}</span><span className="db-stat-label">Tanggal submit</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><UserRound size={16} /> Profil Calon Mahasiswa</h2>
                            {can.delete && (
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmDelete(true)}>
                                    <Trash2 size={14} /> Hapus
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <InfoItem label="Email" value={application.email} />
                                <InfoItem label="No. HP / WhatsApp" value={application.phone} />
                                <InfoItem label="Tanggal lahir & jenis kelamin" value={`${application.birthDate ?? '-'} / ${application.genderLabel}`} />
                                <InfoItem label="Kontak darurat" value={`${application.emergencyContactName ?? '-'} - ${application.emergencyContactPhone ?? '-'}`} />
                                <InfoItem label="Tahun akademik" value={application.academicYear} />
                                <InfoItem label="Fakultas / program studi" value={`${application.faculty ?? '-'} / ${application.studyProgram ?? '-'}`} />
                                <InfoItem label="Kelas" value={application.classType} />
                                <InfoItem label="Review terakhir" value={application.reviewedBy ? `${application.reviewedBy} • ${application.reviewedAt ?? ''}` : '-'} />
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <span className="crud-label">Alamat domisili</span>
                                    <div>{application.address ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Latar Belakang Pendidikan</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <InfoItem label="Asal sekolah (SMA/SMK)" value={application.highSchoolName} />
                                <InfoItem label="Jurusan sekolah" value={application.highSchoolMajor} />
                                <InfoItem label="Tahun lulus" value={application.highSchoolGraduationYear} />
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CheckCircle2 size={16} /> Keputusan Review & Skor</h2>
                        </div>
                        <div className="db-card-body">
                            {can.update ? (
                                <form onSubmit={submitReview}>
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Status kelulusan / seleksi"
                                            value={review.data.status}
                                            onChange={(e) => review.setData('status', e.target.value)}
                                            error={review.errors.status}
                                        >
                                            {statusOptions.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Skor akhir gabungan (0 - 100)"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            max={100}
                                            value={String(review.data.final_score ?? '')}
                                            onChange={(e) => review.setData('final_score', e.target.value)}
                                            error={review.errors.final_score}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan keputusan / alasan"
                                                placeholder="Catatan internal penguji atau panitia seleksi…"
                                                value={review.data.review_notes}
                                                onChange={(e) => review.setData('review_notes', e.target.value)}
                                                error={review.errors.review_notes}
                                            />
                                        </div>
                                    </div>
                                    <div className="crud-actions">
                                        <button className="db-btn primary" type="submit" disabled={review.processing}>
                                            Simpan Hasil Review
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <div className="crud-grid">
                                    <InfoItem label="Status seleksi" value={application.statusLabel} />
                                    <InfoItem label="Skor akhir" value={application.finalScore} />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <InfoItem label="Catatan review" value={application.reviewNotes} />
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Verifikasi Berkas & Dokumen</h2>
                        </div>
                        <div className="db-card-body">
                            {application.documents.length === 0 && (
                                <p className="db-hint">Belum ada dokumen pendaftaran yang diunggah.</p>
                            )}
                            {application.documents.map((document) => (
                                <DocumentCard key={document.id} document={document} canUpdate={can.update} />
                            ))}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><History size={16} /> Jadwal Ujian & Penilaian</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <span className="crud-label">Sesi seleksi ditugaskan</span>
                                    {application.examParticipants.length === 0 && <div className="db-hint">Belum ada jadwal ujian yang ditetapkan.</div>}
                                    {application.examParticipants.map((participant) => (
                                        <div key={participant.id} style={{ marginBottom: 10 }}>
                                            <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{participant.title ?? '-'}</b>
                                            <small className="db-hint" style={{ display: 'block' }}>
                                                {participant.examType ?? '-'} • {participant.examDate ?? '-'} {participant.examTime ? `• ${participant.examTime}` : ''}
                                            </small>
                                            <span className={`db-badge ${statusBadge(participant.attendanceStatus)}`}>{participant.attendanceStatus}</span>
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <span className="crud-label">Komponen penilaian</span>
                                    {application.scores.length === 0 && <div className="db-hint">Belum ada skor yang tercatat.</div>}
                                    {application.scores.map((score) => (
                                        <div key={score.id} style={{ marginBottom: 10 }}>
                                            <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{score.scoreType}</b>
                                            <span className="db-badge" style={{ marginLeft: 8 }}>{score.score}</span>
                                            <small className="db-hint" style={{ display: 'block' }}>
                                                {score.schedule ?? 'Penilaian manual'} • bobot {score.weight}
                                            </small>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><History size={16} /> Riwayat Perubahan Status</h2>
                        </div>
                        <div className="db-card-body">
                            {application.histories.length === 0 && <p className="db-hint">Belum ada riwayat perubahan status.</p>}
                            {application.histories.map((history) => (
                                <div key={history.id} style={{ borderBottom: '1px solid var(--db-line)', paddingBottom: 10, marginBottom: 10 }}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
                                        <span className="db-badge gray">{history.fromStatus ?? 'Baru'}</span>
                                        <span>→</span>
                                        <span className="db-badge">{history.toStatus}</span>
                                    </div>
                                    <small className="db-hint" style={{ display: 'block', marginTop: 4 }}>
                                        {history.createdAt ?? '-'} oleh {history.changedBy}
                                    </small>
                                    {history.notes && <div style={{ marginTop: 6 }}>{history.notes}</div>}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Konversi ke Mahasiswa Aktif</h2>
                        </div>
                        <div className="db-card-body">
                            {application.convertedAt ? (
                                <>
                                    <div className="db-note" style={{ marginBottom: 12 }}>
                                        <b>Berhasil dikonversi</b>
                                        <div className="db-hint">Pada {application.convertedAt}</div>
                                        <div style={{ marginTop: 8 }}>
                                            <span className="db-badge green">NIM: {application.nim ?? '-'}</span>
                                        </div>
                                    </div>
                                    {application.acceptanceLetterUrl && (
                                        <a className="db-btn primary" href={application.acceptanceLetterUrl} target="_blank" rel="noreferrer">
                                            <FileText size={14} /> Unduh Surat Diterima
                                        </a>
                                    )}
                                </>
                            ) : application.status === 'accepted' ? (
                                <>
                                    <div className="db-note" style={{ marginBottom: 12 }}>
                                        <span className="crud-label">Preview calon NIM</span>
                                        <b style={{ fontSize: 18 }}>{nimPreview ?? 'Belum ada aturan NIM aktif'}</b>
                                    </div>
                                    {can.convert && (
                                        <button className="db-btn primary" type="button" onClick={convert} disabled={converting}>
                                            <GraduationCap size={14} /> {converting ? 'Memproses konversi…' : 'Generate NIM & Konversi Mahasiswa'}
                                        </button>
                                    )}
                                </>
                            ) : (
                                <div className="db-note">
                                    Calon mahasiswa harus berstatus <b>Diterima (accepted)</b> sebelum dapat dikonversi menjadi mahasiswa aktif dan mendapatkan NIM.
                                </div>
                            )}
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus aplikasi pendaftaran?"
                    message={`Aplikasi ${application.applicationNumber} dipindah ke sampah dan bisa dipulihkan.`}
                    confirmLabel="Ya, hapus"
                    processing={deleting}
                    onConfirm={() => {
                        setDeleting(true);
                        router.delete(urls.delete, {
                            onFinish: () => {
                                setDeleting(false);
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
