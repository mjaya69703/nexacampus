// Detail bimbingan dosen — review milestone, unduh dokumen, dan nilai sidang.
import { Head, useForm } from '@inertiajs/react';
import {
    BookOpen, CalendarDays, CheckCircle2, Download, FileText, GraduationCap, Link2,
    Send, ShieldCheck, UserRound, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Supervisor = { id: number; name: string | null; nidn: string | null; roleLabel: string; assignedAt: string | null; isMe: boolean };

type Milestone = {
    id: number; title: string; description: string | null; sequence: number;
    status: string; statusLabel: string; statusTone: string; dueDate: string | null;
    submittedAt: string | null; reviewedAt: string | null; reviewedBy: string | null;
    feedback: string | null; canReview: boolean; reviewUrl: string;
};

type DocumentItem = {
    id: number; title: string; categoryLabel: string; fileName: string; fileSize: string;
    version: number; uploadedBy: string | null; createdAt: string | null; downloadUrl: string;
};

type Examiner = { name: string | null; roleLabel: string; score: number | null };

type MyExaminer = { id: number; role: string; roleLabel: string; score: number | null; notes: string | null; scoreUrl: string; canScore: boolean };

type Defense = {
    id: number; typeLabel: string; scheduledAt: string | null; durationMinutes: number;
    room: string | null; meetingLink: string | null; status: string; statusLabel: string;
    statusTone: string; resultLabel: string | null; score: number | null; grade: string | null;
    notes: string | null; completedAt: string | null; myExaminer: MyExaminer | null; examiners: Examiner[];
};

type Thesis = {
    id: number; title: string; topic: string | null; abstract: string | null; keywords: string | null;
    typeLabel: string; status: string; statusLabel: string; statusTone: string; year: string | null;
    createdAt: string | null; proposalSubmittedAt: string | null; approvedAt: string | null;
    approvedBy: string | null; finalScore: number | null; finalGrade: string | null;
    completedAt: string | null; repositoryPublished: boolean; notes: string | null;
    student: { name: string | null; nim: string | null; program: string | null; entryYear: number | null; semester: number | null; email: string | null };
    proposal: { fileName: string | null; downloadUrl: string } | null;
    supervisors: Supervisor[]; milestones: Milestone[]; documents: DocumentItem[]; defenses: Defense[];
};

type Props = { shell: ShellProps; thesis: Thesis; urls: { index: string } };

function ModalShell({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal" style={{ width: 'min(32rem, 100%)', textAlign: 'left' }} onClick={(event) => event.stopPropagation()}>
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

function ReviewModal({ milestone, onClose }: { milestone: Milestone; onClose: () => void }) {
    const form = useForm({ status: 'approved', feedback: milestone.feedback ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(milestone.reviewUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title={`Review Milestone: ${milestone.title}`} onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Keputusan <span>*</span></label>
                    <select className="db-input" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="approved">Setujui</option>
                        <option value="revision">Minta Revisi</option>
                    </select>
                    {form.errors.status && <div className="db-error">{form.errors.status}</div>}
                </div>
                <div>
                    <label className="crud-label">Catatan pembimbing</label>
                    <textarea className="db-input" rows={4} value={form.data.feedback} onChange={(event) => form.setData('feedback', event.target.value)} placeholder="Masukan perbaikan atau arahan lanjutan" />
                    {form.errors.feedback && <div className="db-error">{form.errors.feedback}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan Review'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function ScoreModal({ defense, onClose }: { defense: Defense; onClose: () => void }) {
    const examiner = defense.myExaminer!;
    const form = useForm({
        score: examiner.score !== null ? String(examiner.score) : '',
        notes: examiner.notes ?? '',
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(examiner.scoreUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title={`Nilai ${defense.typeLabel}`} onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Nilai (0–100) <span>*</span></label>
                    <input type="number" min={0} max={100} step="0.01" className="db-input" value={form.data.score} onChange={(event) => form.setData('score', event.target.value)} />
                    {form.errors.score && <div className="db-error">{form.errors.score}</div>}
                </div>
                <div>
                    <label className="crud-label">Catatan penilaian</label>
                    <textarea className="db-input" rows={3} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan Nilai'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

export default function LecturerThesisShow({ shell, thesis, urls }: Props) {
    const [reviewMilestone, setReviewMilestone] = useState<Milestone | null>(null);
    const [scoreDefense, setScoreDefense] = useState<Defense | null>(null);

    const approvedMilestones = thesis.milestones.filter((milestone) => milestone.status === 'approved').length;

    return (
        <AdminShell shell={shell}>
            <Head title={`${thesis.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Bimbingan · Skripsi / Tugas Akhir"
                        title={thesis.title}
                        description={`${thesis.student.name ?? '-'} · ${thesis.student.nim ?? '-'} · ${thesis.student.program ?? '-'}`}
                        badges={[thesis.statusLabel, thesis.typeLabel, thesis.year ?? '-', thesis.finalGrade ? `Nilai ${thesis.finalGrade}` : 'Belum dinilai']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali</a>}
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num">{approvedMilestones}/{thesis.milestones.length}</span><span className="db-stat-label">Milestone disetujui</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Send size={20} /></span>
                            <div><span className="db-stat-num">{thesis.milestones.filter((milestone) => milestone.status === 'submitted').length}</span><span className="db-stat-label">Menunggu review</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{thesis.documents.length}</span><span className="db-stat-label">Dokumen</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon purple"><CalendarDays size={20} /></span>
                            <div><span className="db-stat-num">{thesis.defenses.length}</span><span className="db-stat-label">Sesi sidang</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><UserRound size={16} /> Ringkasan Mahasiswa</h2></div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <Info label="Mahasiswa" value={thesis.student.name} hint={`${thesis.student.nim ?? '-'} · ${thesis.student.program ?? '-'}`} />
                                <Info label="Angkatan / Semester" value={`${thesis.student.entryYear ?? '-'} / ${thesis.student.semester ?? '-'}`} hint={thesis.student.email} />
                                <Info label="Topik" value={thesis.topic} hint={thesis.keywords} />
                                <Info label="Diajukan" value={thesis.proposalSubmittedAt ?? thesis.createdAt} hint={thesis.approvedAt ? `Disetujui ${thesis.approvedAt}` : 'Belum disetujui'} />
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
                        <div className="db-card-head"><h2 className="db-card-title"><Users size={16} /> Pembimbing</h2></div>
                        <div className="db-card-body">
                            {thesis.supervisors.length === 0 ? (
                                <p className="db-hint">Belum ada pembimbing ditetapkan.</p>
                            ) : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {thesis.supervisors.map((supervisor) => (
                                        <div key={supervisor.id} className="db-summary">
                                            <div>
                                                <b>{supervisor.name ?? '-'}{supervisor.isMe ? ' (Anda)' : ''}</b>
                                                <small>{supervisor.nidn ?? '-'} · {supervisor.roleLabel}{supervisor.assignedAt ? ` · sejak ${supervisor.assignedAt}` : ''}</small>
                                            </div>
                                            <span className={`db-badge ${supervisor.isMe ? 'green' : 'gray'}`}>{supervisor.isMe ? 'Pembimbing Anda' : 'Pembimbing'}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><GraduationCap size={16} /> Milestone Bimbingan</h2></div>
                        <div className="db-card-body">
                            {thesis.milestones.length === 0 ? (
                                <p className="db-hint">Belum ada milestone bimbingan.</p>
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
                                                {milestone.feedback && <div className="db-hint" style={{ marginTop: 4 }}>Catatan: {milestone.feedback}</div>}
                                            </div>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                                                <span className={`db-badge ${milestone.statusTone}`}>{milestone.statusLabel}</span>
                                                {milestone.canReview && (
                                                    <button className="db-btn primary sm" type="button" onClick={() => setReviewMilestone(milestone)}>
                                                        <ShieldCheck size={13} /> Review
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
                        <div className="db-card-head"><h2 className="db-card-title"><FileText size={16} /> Dokumen</h2></div>
                        <div className="db-card-body">
                            {thesis.documents.length === 0 ? (
                                <p className="db-hint">Belum ada dokumen diunggah.</p>
                            ) : (
                                <div className="crud-table-wrap">
                                    <table className="crud-table">
                                        <thead><tr><th>Judul</th><th>Kategori</th><th>Versi</th><th>Diunggah</th><th style={{ width: 70 }}>Aksi</th></tr></thead>
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
                                                        <a className="db-btn ghost sm" href={document.downloadUrl} target="_blank" rel="noreferrer" title="Unduh"><Download size={13} /></a>
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
                                                    {defense.completedAt ? ` · Selesai ${defense.completedAt}` : ''}
                                                </small>
                                                {defense.meetingLink && (
                                                    <div className="db-hint" style={{ marginTop: 4 }}>
                                                        <a href={defense.meetingLink} target="_blank" rel="noreferrer"><Link2 size={12} /> {defense.meetingLink}</a>
                                                    </div>
                                                )}
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                                    {defense.examiners.map((examiner, index) => (
                                                        <span key={`${defense.id}-${index}`} className="db-badge gray">
                                                            {examiner.name ?? '-'} · {examiner.roleLabel}{examiner.score !== null ? ` · ${examiner.score}` : ''}
                                                        </span>
                                                    ))}
                                                </div>
                                                {defense.myExaminer && (
                                                    <div className="db-hint" style={{ marginTop: 6 }}>
                                                        Peran Anda: <b>{defense.myExaminer.roleLabel}</b>
                                                        {defense.myExaminer.score !== null ? ` · Nilai Anda ${defense.myExaminer.score}` : ' · Belum memberi nilai'}
                                                    </div>
                                                )}
                                                {defense.notes && <div className="db-hint" style={{ marginTop: 6 }}>Catatan: {defense.notes}</div>}
                                            </div>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                                                <span className={`db-badge ${defense.statusTone}`}>{defense.statusLabel}</span>
                                                {defense.resultLabel && <span className={`db-badge ${defense.resultLabel === 'Tidak Lulus' ? 'red' : 'green'}`}>{defense.resultLabel}</span>}
                                                {defense.score !== null && <span className="db-badge">{defense.score}{defense.grade ? ` · ${defense.grade}` : ''}</span>}
                                                {defense.myExaminer?.canScore && (
                                                    <button className="db-btn primary sm" type="button" onClick={() => setScoreDefense(defense)}>
                                                        <CheckCircle2 size={13} /> Beri Nilai
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    {thesis.status === 'cancelled' && (
                        <div className="db-note danger">
                            <XCircle size={15} />
                            <span>Pengajuan ini telah dibatalkan.</span>
                        </div>
                    )}
                </div>

                {reviewMilestone && <ReviewModal milestone={reviewMilestone} onClose={() => setReviewMilestone(null)} />}
                {scoreDefense && <ScoreModal defense={scoreDefense} onClose={() => setScoreDefense(null)} />}
            </div>
        </AdminShell>
    );
}
