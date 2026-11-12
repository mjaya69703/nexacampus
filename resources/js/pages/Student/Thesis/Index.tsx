// Portal mahasiswa — pengajuan skripsi/tugas akhir, milestone, dokumen, dan sidang.
import { Head, router, useForm } from '@inertiajs/react';
import {
    BookOpen, CalendarDays, CheckCircle2, Clock, Download, FileText, GraduationCap,
    Link2, Pencil, Plus, Send, ShieldAlert, Trash2, Upload, UserRound, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Student = { name: string | null; nim: string | null; program: string | null; semester: number | null };

type Supervisor = { id: number; name: string | null; nidn: string | null; roleLabel: string; assignedAt: string | null };

type Milestone = {
    id: number; title: string; description: string | null; sequence: number;
    status: string; statusLabel: string; statusTone: string; dueDate: string | null;
    submittedAt: string | null; reviewedAt: string | null; reviewedBy: string | null;
    feedback: string | null; canSubmit: boolean; submitUrl: string;
};

type DocumentItem = {
    id: number; title: string; category: string; categoryLabel: string; fileName: string;
    fileSize: string; version: number; uploadedBy: string | null; createdAt: string | null;
    downloadUrl: string; deleteUrl: string;
};

type Defense = {
    id: number; typeLabel: string; scheduledAt: string | null; durationMinutes: number;
    room: string | null; meetingLink: string | null; status: string; statusLabel: string;
    statusTone: string; resultLabel: string | null; score: number | null; grade: string | null;
    notes: string | null; examiners: { name: string | null; roleLabel: string }[];
};

type Thesis = {
    id: number; title: string; topic: string | null; abstract: string | null; keywords: string | null;
    type: string; typeLabel: string; status: string; statusLabel: string; statusTone: string;
    year: string | null; academicYearId: number; createdAt: string | null;
    proposalSubmittedAt: string | null; approvedAt: string | null; approvedBy: string | null;
    rejectedAt: string | null; rejectionReason: string | null; finalScore: number | null;
    finalGrade: string | null; completedAt: string | null; repositoryPublished: boolean;
    repositoryPublishedAt: string | null; notes: string | null;
    canEdit: boolean; canCancel: boolean; canUpload: boolean; updateUrl: string;
    proposal: { fileName: string | null; downloadUrl: string } | null;
    supervisors: Supervisor[]; milestones: Milestone[]; documents: DocumentItem[]; defenses: Defense[];
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    student: Student | null;
    thesis: Thesis | null;
    years: { id: number; name: string }[];
    types: Record<string, string>;
    documentCategories: Record<string, string>;
    urls: { index: string; store: string; cancel: string; documentStore: string };
};

function ModalShell({ title, children, onClose, wide = false }: { title: string; children: ReactNode; onClose: () => void; wide?: boolean }) {
    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal" style={{ width: wide ? 'min(46rem, 100%)' : 'min(32rem, 100%)', textAlign: 'left' }} onClick={(event) => event.stopPropagation()}>
                    <h3>{title}</h3>
                    {children}
                </div>
            </div>
        </div>
    );
}

function Info({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <div style={{ fontWeight: 700, color: 'var(--db-heading)' }}>{value || '-'}</div>
            {hint && <div className="db-hint">{hint}</div>}
        </div>
    );
}

function ProposalForm({ thesis, years, types, urls }: { thesis: Thesis | null; years: Props['years']; types: Record<string, string>; urls: Props['urls'] }) {
    const isEdit = thesis !== null;
    const form = useForm({
        academic_year_id: (thesis?.academicYearId ?? years[0]?.id ?? '') as number | '',
        type: thesis?.type ?? 'skripsi',
        title: thesis?.title ?? '',
        topic: thesis?.topic ?? '',
        abstract: thesis?.abstract ?? '',
        keywords: thesis?.keywords ?? '',
        proposal_file: null as File | null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { forceFormData: true, preserveScroll: true };
        isEdit ? form.put(thesis.updateUrl, options) : form.post(urls.store, options);
    };

    return (
        <section className="db-card">
            <div className="db-card-head">
                <h2 className="db-card-title"><BookOpen size={16} /> {isEdit ? 'Perbarui Pengajuan' : 'Formulir Pengajuan'}</h2>
            </div>
            <div className="db-card-body">
                <form onSubmit={submit} style={{ display: 'grid', gap: 14 }}>
                    <div className="crud-grid">
                        <div>
                            <label className="crud-label">Tahun Akademik <span>*</span></label>
                            <select className="db-input" value={form.data.academic_year_id} onChange={(event) => form.setData('academic_year_id', event.target.value === '' ? '' : Number(event.target.value))}>
                                <option value="">Pilih tahun akademik</option>
                                {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                            </select>
                            {form.errors.academic_year_id && <div className="db-error">{form.errors.academic_year_id}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Jenis Karya <span>*</span></label>
                            <select className="db-input" value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                                {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            {form.errors.type && <div className="db-error">{form.errors.type}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Judul <span>*</span></label>
                            <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="Judul penelitian" />
                            {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Topik / Bidang</label>
                            <input className="db-input" value={form.data.topic} onChange={(event) => form.setData('topic', event.target.value)} placeholder="Contoh: Sistem Informasi" />
                            {form.errors.topic && <div className="db-error">{form.errors.topic}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Kata Kunci</label>
                            <input className="db-input" value={form.data.keywords} onChange={(event) => form.setData('keywords', event.target.value)} placeholder="Dipisahkan koma" />
                            {form.errors.keywords && <div className="db-error">{form.errors.keywords}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Abstrak</label>
                            <textarea className="db-input" rows={4} value={form.data.abstract} onChange={(event) => form.setData('abstract', event.target.value)} placeholder="Ringkasan latar belakang, tujuan, dan metode penelitian" />
                            {form.errors.abstract && <div className="db-error">{form.errors.abstract}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Berkas Proposal</label>
                            <input type="file" className="db-input" accept=".pdf,.doc,.docx" onChange={(event) => form.setData('proposal_file', event.target.files?.[0] ?? null)} />
                            {form.errors.proposal_file && <div className="db-error">{form.errors.proposal_file}</div>}
                            <div className="db-hint">PDF/DOC/DOCX maksimal 10 MB. Berkas baru akan menambah versi dokumen.</div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                        <button className="db-btn primary" type="submit" disabled={form.processing}>
                            <Send size={15} /> {form.processing ? 'Mengirim…' : isEdit ? 'Simpan & Ajukan Ulang' : 'Kirim Pengajuan'}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    );
}

function MilestoneSubmitModal({ milestone, onClose }: { milestone: Milestone; onClose: () => void }) {
    const form = useForm({ note: '', file: null as File | null });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(milestone.submitUrl, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title={`Kirim Milestone: ${milestone.title}`} onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Catatan untuk pembimbing</label>
                    <textarea className="db-input" rows={4} value={form.data.note} onChange={(event) => form.setData('note', event.target.value)} placeholder="Ringkasan progres yang sudah dikerjakan" />
                    {form.errors.note && <div className="db-error">{form.errors.note}</div>}
                </div>
                <div>
                    <label className="crud-label">Lampiran (opsional)</label>
                    <input type="file" className="db-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />
                    {form.errors.file && <div className="db-error">{form.errors.file}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Mengirim…' : 'Kirim Milestone'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function DocumentUploadModal({ categories, url, onClose }: { categories: Record<string, string>; url: string; onClose: () => void }) {
    const firstCategory = Object.keys(categories)[0] ?? 'chapter';
    const form = useForm({ category: firstCategory, title: '', notes: '', file: null as File | null });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Unggah Dokumen" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Kategori <span>*</span></label>
                    <select className="db-input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)}>
                        {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    {form.errors.category && <div className="db-error">{form.errors.category}</div>}
                </div>
                <div>
                    <label className="crud-label">Judul dokumen <span>*</span></label>
                    <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} placeholder="Contoh: Bab IV - Hasil dan Pembahasan" />
                    {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                </div>
                <div>
                    <label className="crud-label">Berkas <span>*</span></label>
                    <input type="file" className="db-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} />
                    {form.errors.file && <div className="db-error">{form.errors.file}</div>}
                </div>
                <div>
                    <label className="crud-label">Catatan</label>
                    <textarea className="db-input" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Mengunggah…' : 'Unggah'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function CancelModal({ url, onClose }: { url: string; onClose: () => void }) {
    const form = useForm({ reason: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Batalkan Pengajuan" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Alasan pembatalan <span>*</span></label>
                    <textarea className="db-input" rows={4} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    {form.errors.reason && <div className="db-error">{form.errors.reason}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Kembali</button>
                    <button className="db-btn danger" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Batalkan Pengajuan'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

export default function StudentThesisIndex({ shell, hasProfile, student, thesis, years, types, documentCategories, urls }: Props) {
    const [editing, setEditing] = useState(false);
    const [submitMilestone, setSubmitMilestone] = useState<Milestone | null>(null);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);

    if (!hasProfile) {
        return (
            <AdminShell shell={shell}>
                <Head title={`Skripsi / Tugas Akhir · ${shell.appName}`} />
                <div className="db-root">
                    <div className="db-stack">
                        <CrudHero icon={BookOpen} eyebrow="Akademik" title="Skripsi / Tugas Akhir" description="Pengajuan topik, bimbingan, sidang, dan arsip karya ilmiah." />
                        <div className="db-note danger">
                            <ShieldAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Hubungi admin akademik untuk sinkronisasi data.</span>
                        </div>
                    </div>
                </div>
            </AdminShell>
        );
    }

    const approvedMilestones = thesis?.milestones.filter((milestone) => milestone.status === 'approved').length ?? 0;
    const awaitingReview = thesis?.milestones.filter((milestone) => milestone.status === 'submitted').length ?? 0;
    const nextDefense = thesis?.defenses.find((defense) => defense.status === 'scheduled') ?? null;

    return (
        <AdminShell shell={shell}>
            <Head title={`Skripsi / Tugas Akhir · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title={thesis ? thesis.title : 'Skripsi / Tugas Akhir'}
                        description={thesis
                            ? `${thesis.typeLabel} · ${thesis.year ?? '-'} · ${thesis.statusLabel}`
                            : 'Ajukan topik penelitian, unggah proposal, lalu pantau proses bimbingan hingga sidang.'}
                        badges={thesis
                            ? [thesis.statusLabel, thesis.typeLabel, thesis.finalGrade ? `Nilai ${thesis.finalGrade}` : 'Belum dinilai']
                            : [`NIM ${student?.nim ?? '-'}`, student?.program ?? '-', `Semester ${student?.semester ?? '-'}`]}
                        actions={thesis ? (
                            <>
                                {thesis.canEdit && !editing && (
                                    <button className="db-btn light" type="button" onClick={() => setEditing(true)}><Pencil size={15} /> Edit Pengajuan</button>
                                )}
                                {thesis.canCancel && (
                                    <button className="db-btn ghost" type="button" onClick={() => setCancelOpen(true)}><XCircle size={15} /> Batalkan</button>
                                )}
                            </>
                        ) : undefined}
                    />

                    {thesis && (
                        <section className="db-stats">
                            <div className="db-stat">
                                <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                                <div><span className="db-stat-num">{approvedMilestones}/{thesis.milestones.length}</span><span className="db-stat-label">Milestone disetujui</span></div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon gold"><Clock size={20} /></span>
                                <div><span className="db-stat-num">{awaitingReview}</span><span className="db-stat-label">Menunggu review</span></div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon"><FileText size={20} /></span>
                                <div><span className="db-stat-num">{thesis.documents.length}</span><span className="db-stat-label">Dokumen</span></div>
                            </div>
                            <div className="db-stat">
                                <span className="db-stat-icon purple"><CalendarDays size={20} /></span>
                                <div><span className="db-stat-num">{nextDefense ? nextDefense.typeLabel : '-'}</span><span className="db-stat-label">{nextDefense ? nextDefense.scheduledAt : 'Belum ada jadwal'}</span></div>
                            </div>
                        </section>
                    )}

                    {thesis?.rejectionReason && (
                        <div className="db-note danger">
                            <ShieldAlert size={15} />
                            <span><b>Pengajuan ditolak:</b> {thesis.rejectionReason}. Perbaiki data lalu ajukan ulang.</span>
                        </div>
                    )}

                    {!thesis || editing ? (
                        <ProposalForm thesis={editing ? thesis : null} years={years} types={types} urls={urls} />
                    ) : (
                        <>
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><UserRound size={16} /> Ringkasan</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <Info label="Mahasiswa" value={student?.name} hint={`${student?.nim ?? '-'} · ${student?.program ?? '-'}`} />
                                        <Info label="Tahun Akademik" value={thesis.year} hint={thesis.typeLabel} />
                                        <Info label="Diajukan" value={thesis.proposalSubmittedAt ?? thesis.createdAt} />
                                        <Info label="Disetujui" value={thesis.approvedAt} hint={thesis.approvedBy} />
                                        <Info label="Topik" value={thesis.topic} hint={thesis.keywords} />
                                        <Info label="Nilai Akhir" value={thesis.finalGrade ? `${thesis.finalGrade} (${thesis.finalScore ?? '-'})` : '-'} hint={thesis.completedAt ? `Selesai ${thesis.completedAt}` : thesis.repositoryPublished ? 'Terpublikasi di repositori' : 'Belum difinalisasi'} />
                                    </div>
                                    {thesis.abstract && (
                                        <div style={{ marginTop: 18 }}>
                                            <span className="crud-label">Abstrak</span>
                                            <p style={{ margin: 0, color: 'var(--db-text)' }}>{thesis.abstract}</p>
                                        </div>
                                    )}
                                    {thesis.proposal && (
                                        <div style={{ marginTop: 16 }}>
                                            <a className="db-btn ghost" href={thesis.proposal.downloadUrl} target="_blank" rel="noreferrer">
                                                <Download size={14} /> Proposal: {thesis.proposal.fileName ?? 'unduh'}
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Pembimbing</h2></div>
                                <div className="db-card-body">
                                    {thesis.supervisors.length === 0 ? (
                                        <p className="db-hint">Pembimbing belum ditetapkan. Tunggu proses persetujuan admin/prodi.</p>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 8 }}>
                                            {thesis.supervisors.map((supervisor) => (
                                                <div key={supervisor.id} className="db-summary">
                                                    <div>
                                                        <b>{supervisor.name ?? '-'}</b>
                                                        <small>{supervisor.nidn ?? '-'} · {supervisor.roleLabel}{supervisor.assignedAt ? ` · sejak ${supervisor.assignedAt}` : ''}</small>
                                                    </div>
                                                    <span className="db-badge green">Aktif</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><CheckCircle2 size={16} /> Milestone Bimbingan</h2></div>
                                <div className="db-card-body">
                                    {thesis.milestones.length === 0 ? (
                                        <p className="db-hint">Milestone bimbingan belum dibuat.</p>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {thesis.milestones.map((milestone) => (
                                                <div key={milestone.id} className="db-summary" style={{ alignItems: 'flex-start' }}>
                                                    <div style={{ flex: 1 }}>
                                                        <b>{milestone.sequence}. {milestone.title}</b>
                                                        {milestone.description && <div className="db-hint">{milestone.description}</div>}
                                                        <small>
                                                            {milestone.dueDate ? `Tenggat ${milestone.dueDate}` : 'Tanpa tenggat'}
                                                            {milestone.submittedAt ? ` · Dikirim ${milestone.submittedAt}` : ''}
                                                            {milestone.reviewedBy ? ` · Direview ${milestone.reviewedBy}` : ''}
                                                        </small>
                                                        {milestone.feedback && <div className="db-hint" style={{ marginTop: 4 }}>Catatan pembimbing: {milestone.feedback}</div>}
                                                    </div>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                                                        <span className={`db-badge ${milestone.statusTone}`}>{milestone.statusLabel}</span>
                                                        {milestone.canSubmit && (
                                                            <button className="db-btn ghost sm" type="button" onClick={() => setSubmitMilestone(milestone)}>
                                                                <Send size={13} /> Kirim
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileText size={16} /> Dokumen</h2>
                                    {thesis.canUpload && (
                                        <button className="db-btn ghost sm" type="button" onClick={() => setUploadOpen(true)}><Upload size={13} /> Unggah</button>
                                    )}
                                </div>
                                <div className="db-card-body">
                                    {thesis.documents.length === 0 ? (
                                        <p className="db-hint">Belum ada dokumen diunggah.</p>
                                    ) : (
                                        <div className="crud-table-wrap">
                                            <table className="crud-table">
                                                <thead><tr><th>Judul</th><th>Kategori</th><th>Versi</th><th>Diunggah</th><th style={{ width: 100 }}>Aksi</th></tr></thead>
                                                <tbody>
                                                    {thesis.documents.map((document) => (
                                                        <tr key={document.id}>
                                                            <td>
                                                                <b className="crud-name">{document.title}</b>
                                                                <div className="db-hint">{document.fileName} · {document.fileSize}</div>
                                                            </td>
                                                            <td><span className="db-badge gray">{document.categoryLabel}</span></td>
                                                            <td>v{document.version}</td>
                                                            <td>{document.createdAt ?? '-'}<div className="db-hint">{document.uploadedBy ?? '-'}</div></td>
                                                            <td>
                                                                <div style={{ display: 'flex', gap: 6 }}>
                                                                    <a className="db-btn ghost sm" href={document.downloadUrl} target="_blank" rel="noreferrer" title="Unduh"><Download size={13} /></a>
                                                                    {thesis.canUpload && (
                                                                        <button
                                                                            className="db-btn ghost sm"
                                                                            type="button"
                                                                            title="Hapus"
                                                                            onClick={() => {
                                                                                if (window.confirm(`Hapus dokumen "${document.title}"?`)) {
                                                                                    router.delete(document.deleteUrl, { preserveScroll: true });
                                                                                }
                                                                            }}
                                                                        >
                                                                            <Trash2 size={13} />
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title"><CalendarDays size={16} /> Jadwal Sidang</h2></div>
                                <div className="db-card-body">
                                    {thesis.defenses.length === 0 ? (
                                        <p className="db-hint">Belum ada jadwal sidang.</p>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 12 }}>
                                            {thesis.defenses.map((defense) => (
                                                <div key={defense.id} className="db-summary" style={{ alignItems: 'flex-start' }}>
                                                    <div style={{ flex: 1 }}>
                                                        <b>{defense.typeLabel} · {defense.scheduledAt ?? '-'}</b>
                                                        <small>
                                                            {defense.durationMinutes} menit
                                                            {defense.room ? ` · ${defense.room}` : ''}
                                                            {defense.meetingLink ? ' · Daring' : ''}
                                                        </small>
                                                        {defense.meetingLink && (
                                                            <div className="db-hint" style={{ marginTop: 4 }}>
                                                                <a href={defense.meetingLink} target="_blank" rel="noreferrer"><Link2 size={12} /> {defense.meetingLink}</a>
                                                            </div>
                                                        )}
                                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                                            {defense.examiners.map((examiner, index) => (
                                                                <span key={`${defense.id}-${index}`} className="db-badge gray">{examiner.name ?? '-'} · {examiner.roleLabel}</span>
                                                            ))}
                                                        </div>
                                                        {defense.notes && <div className="db-hint" style={{ marginTop: 6 }}>Catatan: {defense.notes}</div>}
                                                    </div>
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                                                        <span className={`db-badge ${defense.statusTone}`}>{defense.statusLabel}</span>
                                                        {defense.resultLabel && <span className="db-badge green">{defense.resultLabel}</span>}
                                                        {defense.score !== null && <span className="db-badge">{defense.score}{defense.grade ? ` · ${defense.grade}` : ''}</span>}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {thesis.status === 'completed' && (
                                <section className="db-card">
                                    <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Hasil Akhir & Repositori</h2></div>
                                    <div className="db-card-body">
                                        <div className="crud-grid">
                                            <Info label="Nilai Akhir" value={thesis.finalScore !== null ? `${thesis.finalScore} (${thesis.finalGrade ?? '-'})` : '-'} />
                                            <Info label="Selesai" value={thesis.completedAt} />
                                            <Info label="Repositori" value={thesis.repositoryPublished ? 'Terpublikasi' : 'Belum dipublikasikan'} hint={thesis.repositoryPublishedAt} />
                                        </div>
                                    </div>
                                </section>
                            )}
                        </>
                    )}
                </div>

                {submitMilestone && <MilestoneSubmitModal milestone={submitMilestone} onClose={() => setSubmitMilestone(null)} />}
                {uploadOpen && <DocumentUploadModal categories={documentCategories} url={urls.documentStore} onClose={() => setUploadOpen(false)} />}
                {cancelOpen && <CancelModal url={urls.cancel} onClose={() => setCancelOpen(false)} />}
            </div>
        </AdminShell>
    );
}
