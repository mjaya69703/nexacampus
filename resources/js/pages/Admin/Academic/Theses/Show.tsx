// Detail skripsi — ringkasan, pembimbing, milestone, dokumen, sidang, dan finalisasi.
import { Head, router, useForm } from '@inertiajs/react';
import {
    Award, BookOpen, CalendarDays, CheckCircle2, Clock, Download, FileText, Globe2, GraduationCap,
    Link2, Pencil, Plus, ShieldCheck, Trash2, Upload, UserRound, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Supervisor = {
    id: number; lecturerProfileId: number; name: string | null; nidn: string | null;
    role: string; roleLabel: string; isActive: boolean; assignedAt: string | null;
};

type Milestone = {
    id: number; title: string; description: string | null; sequence: number;
    status: string; statusLabel: string; statusTone: string; dueDate: string | null;
    submittedAt: string | null; reviewedAt: string | null; reviewedBy: string | null;
    feedback: string | null; updateUrl: string; deleteUrl: string;
};

type Document = {
    id: number; title: string; category: string; categoryLabel: string; fileName: string;
    fileSize: string; mimeType: string | null; version: number; uploadedBy: string | null;
    notes: string | null; createdAt: string | null; downloadUrl: string; deleteUrl: string;
};

type Examiner = {
    id: number; lecturerProfileId: number; name: string | null; nidn: string | null;
    role: string; roleLabel: string; score: number | null; notes: string | null;
};

type Defense = {
    id: number; type: string; typeLabel: string; scheduledAt: string | null; durationMinutes: number;
    room: string | null; meetingLink: string | null; status: string; statusLabel: string; statusTone: string;
    result: string | null; resultLabel: string | null; score: number | null; grade: string | null;
    notes: string | null; completedAt: string | null; examiners: Examiner[];
    completeUrl: string; cancelUrl: string; updateUrl: string;
};

type Thesis = {
    id: number; title: string; topic: string | null; abstract: string | null; keywords: string | null;
    type: string; typeLabel: string; status: string; statusLabel: string; statusTone: string;
    year: string | null; createdAt: string | null; proposalSubmittedAt: string | null;
    approvedAt: string | null; approvedBy: string | null; rejectedAt: string | null; rejectionReason: string | null;
    finalScore: number | null; finalGrade: string | null; completedAt: string | null;
    repositoryPublished: boolean; repositoryPublishedAt: string | null; notes: string | null;
    plagiarismStatus: string; plagiarismStatusLabel: string; plagiarismStatusTone: string;
    plagiarismScore: number | null; plagiarismTool: string | null; plagiarismCheckedAt: string | null;
    plagiarismCheckedAtInput: string | null; plagiarismNotes: string | null;
    student: { id: number | null; name: string | null; nim: string | null; program: string | null; entryYear: number | null; semester: number | null; email: string | null };
    proposal: { fileName: string | null; downloadUrl: string } | null;
    supervisors: Supervisor[]; milestones: Milestone[]; documents: Document[]; defenses: Defense[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; approve: boolean; defense: boolean; grade: boolean; plagiarism: boolean };
    thesis: Thesis;
    options: {
        types: Record<string, string>;
        milestoneStatuses: Record<string, string>;
        defenseTypes: Record<string, string>;
        examinerRoles: Record<string, string>;
        documentCategories: Record<string, string>;
        defenseResults: Record<string, string>;
        plagiarismStatuses: Record<string, string>;
    };
    urls: {
        index: string; edit: string; delete: string; approve: string; reject: string;
        milestoneStore: string; defenseStore: string; documentStore: string; finalize: string;
        plagiarism: string; publish: string; repository: string; searchLecturers: string;
    };
};

function ModalShell({ title, children, onClose, wide = false }: { title: string; children: ReactNode; onClose: () => void; wide?: boolean }) {
    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal" style={{ width: wide ? 'min(48rem, 100%)' : 'min(32rem, 100%)', textAlign: 'left' }} onClick={(event) => event.stopPropagation()}>
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

function ApproveModal({ thesis, url, searchUrl, onClose }: { thesis: Thesis; url: string; searchUrl: string; onClose: () => void }) {
    const existing = thesis.supervisors.filter((supervisor) => supervisor.isActive);
    const [picked, setPicked] = useState<{ id: number; label: string }[]>(
        existing.map((supervisor) => ({ id: supervisor.lecturerProfileId, label: supervisor.name ?? '-' })),
    );
    const [option, setOption] = useState<AsyncOption | null>(null);
    const form = useForm({ supervisor_ids: existing.map((supervisor) => supervisor.lecturerProfileId), notes: thesis.notes ?? '' });

    const add = () => {
        if (!option) return;
        const id = Number(option.id);
        if (picked.some((item) => item.id === id)) {
            form.setError('supervisor_ids', 'Dosen ini sudah dipilih.');
            return;
        }
        const next = [...picked, { id, label: option.label }];
        setPicked(next);
        form.setData('supervisor_ids', next.map((item) => item.id));
        form.clearErrors('supervisor_ids');
        setOption(null);
    };

    const remove = (id: number) => {
        const next = picked.filter((item) => item.id !== id);
        setPicked(next);
        form.setData('supervisor_ids', next.map((item) => item.id));
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Setujui Pengajuan & Tetapkan Pembimbing" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <AsyncSelect
                    label="Tambah dosen pembimbing"
                    fetchUrl={searchUrl}
                    value={option}
                    onChange={setOption}
                    placeholder="Ketik NIDN/nama dosen…"
                />
                <button className="db-btn ghost sm" type="button" onClick={add} disabled={!option}>
                    <Plus size={13} /> Tambahkan
                </button>
                {form.errors.supervisor_ids && <div className="db-error">{form.errors.supervisor_ids}</div>}
                <div style={{ display: 'grid', gap: 8 }}>
                    {picked.length === 0 && <p className="db-hint">Belum ada pembimbing dipilih.</p>}
                    {picked.map((item, index) => (
                        <div key={item.id} className="db-summary">
                            <div>
                                <b>{item.label}</b>
                                <small>{index === 0 ? 'Pembimbing Utama' : 'Pembimbing Pendamping'}</small>
                            </div>
                            <button className="db-btn ghost sm" type="button" onClick={() => remove(item.id)}><Trash2 size={13} /></button>
                        </div>
                    ))}
                </div>
                <div>
                    <label className="crud-label">Catatan persetujuan</label>
                    <textarea className="db-input" rows={3} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing || picked.length === 0}>
                        {form.processing ? 'Menyimpan…' : 'Setujui & Tetapkan'}
                    </button>
                </div>
            </form>
        </ModalShell>
    );
}

function RejectModal({ url, onClose }: { url: string; onClose: () => void }) {
    const form = useForm({ reason: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Tolak Pengajuan" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Alasan penolakan <span>*</span></label>
                    <textarea className="db-input" rows={4} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} />
                    {form.errors.reason && <div className="db-error">{form.errors.reason}</div>}
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn danger" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Tolak Pengajuan'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function MilestoneModal({ url, onClose }: { url: string; onClose: () => void }) {
    const form = useForm({ title: '', description: '', due_date: '', sequence: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Tambah Milestone Bimbingan" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Judul <span>*</span></label>
                    <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                    {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                </div>
                <div>
                    <label className="crud-label">Deskripsi</label>
                    <textarea className="db-input" rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                </div>
                <div className="crud-grid">
                    <div>
                        <label className="crud-label">Tenggat</label>
                        <input type="date" className="db-input" value={form.data.due_date} onChange={(event) => form.setData('due_date', event.target.value)} />
                    </div>
                    <div>
                        <label className="crud-label">Urutan</label>
                        <input type="number" min={1} className="db-input" value={form.data.sequence} onChange={(event) => form.setData('sequence', event.target.value)} />
                    </div>
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Tambah Milestone'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function MilestoneReviewModal({ milestone, onClose }: { milestone: Milestone; onClose: () => void }) {
    const form = useForm({ status: 'approved', feedback: milestone.feedback ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(milestone.updateUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title={`Review Milestone: ${milestone.title}`} onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Keputusan</label>
                    <select className="db-input" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                        <option value="approved">Setujui</option>
                        <option value="revision">Minta Revisi</option>
                    </select>
                </div>
                <div>
                    <label className="crud-label">Catatan</label>
                    <textarea className="db-input" rows={4} value={form.data.feedback} onChange={(event) => form.setData('feedback', event.target.value)} />
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

function DefenseModal({ url, searchUrl, roles, onClose }: { url: string; searchUrl: string; roles: Record<string, string>; onClose: () => void }) {
    const form = useForm<{
        type: string;
        scheduled_at: string;
        duration_minutes: number;
        room: string;
        meeting_link: string;
        notes: string;
        examiners: { lecturer_profile_id: number; role: string }[];
    }>({ type: 'result', scheduled_at: '', duration_minutes: 90, room: '', meeting_link: '', notes: '', examiners: [] });
    const [examiners, setExaminers] = useState<{ lecturer_profile_id: number; role: string; label: string }[]>([]);
    const [option, setOption] = useState<AsyncOption | null>(null);
    const [role, setRole] = useState('examiner');

    const syncExaminers = (next: { lecturer_profile_id: number; role: string; label: string }[]) => {
        setExaminers(next);
        form.setData('examiners', next.map(({ lecturer_profile_id, role: examinerRole }) => ({ lecturer_profile_id, role: examinerRole })));
    };

    const add = () => {
        if (!option) return;
        const id = Number(option.id);
        if (examiners.some((examiner) => examiner.lecturer_profile_id === id)) {
            form.setError('examiners', 'Dosen ini sudah dipilih sebagai penguji.');
            return;
        }
        syncExaminers([...examiners, { lecturer_profile_id: id, role, label: option.label }]);
        form.clearErrors('examiners');
        setOption(null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (examiners.length === 0) {
            form.setError('examiners', 'Pilih minimal satu dosen penguji.');
            return;
        }
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Jadwalkan Sidang" onClose={onClose} wide>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div className="crud-grid">
                    <div>
                        <label className="crud-label">Jenis sidang <span>*</span></label>
                        <select className="db-input" value={form.data.type} onChange={(event) => form.setData('type', event.target.value)}>
                            <option value="proposal">Seminar Proposal</option>
                            <option value="result">Sidang Hasil</option>
                        </select>
                    </div>
                    <div>
                        <label className="crud-label">Jadwal <span>*</span></label>
                        <input type="datetime-local" className="db-input" value={form.data.scheduled_at} onChange={(event) => form.setData('scheduled_at', event.target.value)} />
                        {form.errors.scheduled_at && <div className="db-error">{form.errors.scheduled_at}</div>}
                    </div>
                    <div>
                        <label className="crud-label">Durasi (menit)</label>
                        <input type="number" min={30} max={480} className="db-input" value={form.data.duration_minutes} onChange={(event) => form.setData('duration_minutes', Number(event.target.value))} />
                    </div>
                    <div>
                        <label className="crud-label">Ruang</label>
                        <input className="db-input" value={form.data.room} onChange={(event) => form.setData('room', event.target.value)} placeholder="Ruang sidang / gedung" />
                    </div>
                    <div style={{ gridColumn: '1 / -1' }}>
                        <label className="crud-label">Tautan daring</label>
                        <input className="db-input" value={form.data.meeting_link} onChange={(event) => form.setData('meeting_link', event.target.value)} placeholder="https://meet…" />
                        {form.errors.meeting_link && <div className="db-error">{form.errors.meeting_link}</div>}
                    </div>
                </div>

                <div style={{ borderTop: '1px solid var(--db-line)', paddingTop: 12 }}>
                    <span className="crud-label">Dosen penguji</span>
                    <div className="crud-toolbar">
                        <div style={{ flex: 1, minWidth: 220 }}>
                            <AsyncSelect label="Cari dosen" fetchUrl={searchUrl} value={option} onChange={setOption} placeholder="Ketik NIDN/nama dosen…" />
                        </div>
                        <select className="db-input" value={role} onChange={(event) => setRole(event.target.value)} aria-label="Peran penguji">
                            {Object.entries(roles).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <button className="db-btn ghost" type="button" onClick={add} disabled={!option}><Plus size={13} /> Tambah</button>
                    </div>
                    {form.errors.examiners && <div className="db-error">{form.errors.examiners}</div>}
                    <div style={{ display: 'grid', gap: 8, marginTop: 10 }}>
                        {examiners.map((examiner) => (
                            <div key={examiner.lecturer_profile_id} className="db-summary">
                                <div><b>{examiner.label}</b><small>{roles[examiner.role]}</small></div>
                                <button className="db-btn ghost sm" type="button" onClick={() => syncExaminers(examiners.filter((item) => item.lecturer_profile_id !== examiner.lecturer_profile_id))}><Trash2 size={13} /></button>
                            </div>
                        ))}
                    </div>
                </div>

                <div>
                    <label className="crud-label">Catatan</label>
                    <textarea className="db-input" rows={2} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan Jadwal'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function CompleteDefenseModal({ defense, results, onClose }: { defense: Defense; results: Record<string, string>; onClose: () => void }) {
    const initialScores = Object.fromEntries(defense.examiners.map((examiner) => [examiner.id, examiner.score ?? '']));
    const form = useForm({
        result: defense.result ?? 'passed',
        score: defense.score !== null ? String(defense.score) : '',
        notes: defense.notes ?? '',
        examiner_scores: initialScores as Record<number, string | number>,
        examiner_notes: {} as Record<number, string>,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(defense.completeUrl, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title={`Selesaikan ${defense.typeLabel}`} onClose={onClose} wide>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div className="crud-grid">
                    <div>
                        <label className="crud-label">Hasil <span>*</span></label>
                        <select className="db-input" value={form.data.result} onChange={(event) => form.setData('result', event.target.value)}>
                            {Object.entries(results).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="crud-label">Nilai akhir sidang</label>
                        <input type="number" min={0} max={100} step="0.01" className="db-input" value={form.data.score} onChange={(event) => form.setData('score', event.target.value)} placeholder="Kosong = rata-rata penguji" />
                        {form.errors.score && <div className="db-error">{form.errors.score}</div>}
                    </div>
                </div>

                <div>
                    <span className="crud-label">Nilai per penguji</span>
                    <div className="crud-table-wrap">
                        <table className="crud-table">
                            <thead><tr><th>Penguji</th><th>Peran</th><th style={{ width: 120 }}>Nilai</th></tr></thead>
                            <tbody>
                                {defense.examiners.map((examiner) => (
                                    <tr key={examiner.id}>
                                        <td>{examiner.name ?? '-'}</td>
                                        <td>{examiner.roleLabel}</td>
                                        <td>
                                            <input
                                                type="number"
                                                min={0}
                                                max={100}
                                                step="0.01"
                                                className="db-input"
                                                value={String(form.data.examiner_scores[examiner.id] ?? '')}
                                                onChange={(event) => form.setData('examiner_scores', { ...form.data.examiner_scores, [examiner.id]: event.target.value })}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <label className="crud-label">Catatan hasil</label>
                    <textarea className="db-input" rows={3} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                </div>
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan Hasil'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function DocumentModal({ url, categories, onClose }: { url: string; categories: Record<string, string>; onClose: () => void }) {
    const form = useForm({ category: 'chapter', title: '', notes: '', file: null as File | null });
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
                </div>
                <div>
                    <label className="crud-label">Judul dokumen <span>*</span></label>
                    <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
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

function FinalizeModal({ thesis, url, onClose }: { thesis: Thesis; url: string; onClose: () => void }) {
    const publishBlocked = thesis.plagiarismStatus === 'flagged';
    const form = useForm({
        final_score: thesis.finalScore !== null ? String(thesis.finalScore) : '',
        publish: publishBlocked ? false : thesis.repositoryPublished,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Finalisasi Nilai & Arsip" onClose={onClose}>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div>
                    <label className="crud-label">Nilai akhir <span>*</span></label>
                    <input type="number" min={0} max={100} step="0.01" className="db-input" value={form.data.final_score} onChange={(event) => form.setData('final_score', event.target.value)} />
                    {form.errors.final_score && <div className="db-error">{form.errors.final_score}</div>}
                </div>
                <label className="db-summary" style={{ cursor: publishBlocked ? 'not-allowed' : 'pointer', opacity: publishBlocked ? 0.65 : 1 }}>
                    <div>
                        <b>Publikasikan ke repositori</b>
                        <small>Karya akan ditandai siap diakses publik setelah finalisasi.</small>
                    </div>
                    <input type="checkbox" disabled={publishBlocked} checked={form.data.publish} onChange={(event) => form.setData('publish', event.target.checked)} />
                </label>
                {publishBlocked && <div className="db-error">Publikasi diblokir karena hasil cek plagiarisme masih ditandai.</div>}
                {form.errors.publish && <div className="db-error">{form.errors.publish}</div>}
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Finalisasi'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

function PlagiarismModal({ thesis, url, statuses, onClose }: { thesis: Thesis; url: string; statuses: Record<string, string>; onClose: () => void }) {
    const form = useForm({
        plagiarism_status: thesis.plagiarismStatus || 'not_checked',
        plagiarism_score: thesis.plagiarismScore !== null ? String(thesis.plagiarismScore) : '',
        plagiarism_tool: thesis.plagiarismTool ?? '',
        plagiarism_checked_at: thesis.plagiarismCheckedAtInput ?? '',
        plagiarism_notes: thesis.plagiarismNotes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ModalShell title="Metadata Cek Plagiarisme" onClose={onClose} wide>
            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                <div className="crud-grid">
                    <div>
                        <label className="crud-label">Status <span>*</span></label>
                        <select className="db-input" value={form.data.plagiarism_status} onChange={(event) => form.setData('plagiarism_status', event.target.value)}>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        {form.errors.plagiarism_status && <div className="db-error">{form.errors.plagiarism_status}</div>}
                    </div>
                    <div>
                        <label className="crud-label">Skor kemiripan (%)</label>
                        <input type="number" min={0} max={100} step="0.01" className="db-input" value={form.data.plagiarism_score} onChange={(event) => form.setData('plagiarism_score', event.target.value)} placeholder="Contoh: 12.5" />
                        {form.errors.plagiarism_score && <div className="db-error">{form.errors.plagiarism_score}</div>}
                    </div>
                    <div>
                        <label className="crud-label">Alat / layanan cek</label>
                        <input className="db-input" value={form.data.plagiarism_tool} onChange={(event) => form.setData('plagiarism_tool', event.target.value)} placeholder="Contoh: Turnitin" />
                        {form.errors.plagiarism_tool && <div className="db-error">{form.errors.plagiarism_tool}</div>}
                    </div>
                    <div>
                        <label className="crud-label">Tanggal pengecekan</label>
                        <input type="datetime-local" className="db-input" value={form.data.plagiarism_checked_at} onChange={(event) => form.setData('plagiarism_checked_at', event.target.value)} />
                        {form.errors.plagiarism_checked_at && <div className="db-error">{form.errors.plagiarism_checked_at}</div>}
                    </div>
                </div>
                <div>
                    <label className="crud-label">Catatan hasil</label>
                    <textarea className="db-input" rows={3} value={form.data.plagiarism_notes} onChange={(event) => form.setData('plagiarism_notes', event.target.value)} placeholder="Bagian yang terindikasi, tindakan perbaikan, atau catatan verifikator" />
                    {form.errors.plagiarism_notes && <div className="db-error">{form.errors.plagiarism_notes}</div>}
                </div>
                {form.data.plagiarism_status === 'flagged' && (
                    <div className="db-error">Status <b>Ditandai</b> otomatis menarik karya dari repositori publik.</div>
                )}
                <div className="db-modal-actions">
                    <button className="db-btn ghost" type="button" onClick={onClose} disabled={form.processing}>Batal</button>
                    <button className="db-btn primary" type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan Metadata'}</button>
                </div>
            </form>
        </ModalShell>
    );
}

export default function ThesisShow({ shell, can, thesis, options, urls }: Props) {
    const [approveOpen, setApproveOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [milestoneOpen, setMilestoneOpen] = useState(false);
    const [reviewMilestone, setReviewMilestone] = useState<Milestone | null>(null);
    const [defenseOpen, setDefenseOpen] = useState(false);
    const [completeDefense, setCompleteDefense] = useState<Defense | null>(null);
    const [cancelDefense, setCancelDefense] = useState<Defense | null>(null);
    const [documentOpen, setDocumentOpen] = useState(false);
    const [finalizeOpen, setFinalizeOpen] = useState(false);
    const [plagiarismOpen, setPlagiarismOpen] = useState(false);
    const [publishOpen, setPublishOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const canApprove = can.approve && ['submitted', 'rejected'].includes(thesis.status);
    const canScheduleDefense = can.defense && !['completed', 'cancelled'].includes(thesis.status);
    const canFinalize = can.grade && thesis.status === 'defended';
    const canPublish = can.grade && thesis.status === 'completed' && !thesis.repositoryPublished && thesis.plagiarismStatus !== 'flagged';
    const canModify = can.update && !['completed', 'cancelled'].includes(thesis.status);

    const deleteThesis = () => {
        setProcessing(true);
        router.delete(urls.delete, { onFinish: () => { setProcessing(false); setDeleteOpen(false); } });
    };

    const cancelDefenseAction = () => {
        if (!cancelDefense) return;
        setProcessing(true);
        router.post(cancelDefense.cancelUrl, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setCancelDefense(null); },
        });
    };

    const deleteDocument = (document: Document) => {
        if (!window.confirm(`Hapus dokumen "${document.title}"?`)) return;
        router.delete(document.deleteUrl, { preserveScroll: true });
    };

    const publishToRepository = () => {
        setProcessing(true);
        router.post(urls.publish, {}, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setPublishOpen(false); },
        });
    };

    const deleteMilestone = (milestone: Milestone) => {
        if (!window.confirm(`Hapus milestone "${milestone.title}"?`)) return;
        router.delete(milestone.deleteUrl, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${thesis.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik · Skripsi / Tugas Akhir"
                        title={thesis.title}
                        description={`${thesis.student.name ?? '-'} · ${thesis.student.nim ?? '-'} · ${thesis.student.program ?? '-'}`}
                        badges={[thesis.statusLabel, thesis.typeLabel, thesis.year ?? '-', thesis.finalGrade ? `Nilai ${thesis.finalGrade}` : 'Belum dinilai']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali</a>
                                {canModify && <a className="db-btn ghost" href={urls.edit}><Pencil size={14} /> Edit</a>}
                                {canApprove && <button className="db-btn primary" type="button" onClick={() => setApproveOpen(true)}><CheckCircle2 size={14} /> Setujui</button>}
                                {canApprove && <button className="db-btn danger" type="button" onClick={() => setRejectOpen(true)}><XCircle size={14} /> Tolak</button>}
                                {can.plagiarism && <button className="db-btn light" type="button" onClick={() => setPlagiarismOpen(true)}><ShieldCheck size={14} /> Cek Plagiarisme</button>}
                                {canFinalize && <button className="db-btn primary" type="button" onClick={() => setFinalizeOpen(true)}><Award size={14} /> Finalisasi</button>}
                                {can.delete && thesis.status !== 'completed' && <button className="db-btn ghost" type="button" onClick={() => setDeleteOpen(true)}><Trash2 size={14} /> Hapus</button>}
                            </>
                        )}
                    />

                    {thesis.rejectionReason && (
                        <section className="db-card">
                            <div className="db-card-body">
                                <div className="db-error"><b>Alasan penolakan:</b> {thesis.rejectionReason}</div>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><UserRound size={16} /> Ringkasan Pengajuan</h2></div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <Info label="Mahasiswa" value={thesis.student.name} hint={`${thesis.student.nim ?? '-'} · ${thesis.student.program ?? '-'}`} />
                                <Info label="Angkatan / Semester" value={`${thesis.student.entryYear ?? '-'} / ${thesis.student.semester ?? '-'}`} hint={thesis.student.email} />
                                <Info label="Jenis" value={thesis.typeLabel} hint={thesis.year} />
                                <Info label="Topik" value={thesis.topic} hint={thesis.keywords} />
                                <Info label="Diajukan" value={thesis.proposalSubmittedAt ?? thesis.createdAt} hint={thesis.approvedAt ? `Disetujui ${thesis.approvedAt} oleh ${thesis.approvedBy ?? '-'}` : 'Belum disetujui'} />
                                <Info
                                    label="Hasil Akhir"
                                    value={thesis.finalGrade ? `${thesis.finalGrade} (${thesis.finalScore ?? '-'})` : '-'}
                                    hint={thesis.completedAt ? `Selesai ${thesis.completedAt}` : thesis.repositoryPublished ? 'Terpublikasi di repositori' : 'Belum difinalisasi'}
                                />
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
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ShieldCheck size={16} /> Cek Plagiarisme</h2>
                            {can.plagiarism && <button className="db-btn ghost sm" type="button" onClick={() => setPlagiarismOpen(true)}><Pencil size={13} /> Edit Metadata</button>}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <Info label="Status" value={<span className={`db-badge ${thesis.plagiarismStatusTone}`}>{thesis.plagiarismStatusLabel}</span>} />
                                <Info label="Skor Kemiripan" value={thesis.plagiarismScore !== null ? `${thesis.plagiarismScore}%` : '-'} hint={thesis.plagiarismTool ?? 'Alat belum dicatat'} />
                                <Info label="Tanggal Pengecekan" value={thesis.plagiarismCheckedAt} hint={thesis.plagiarismStatus === 'flagged' ? 'Karya ditarik dari repositori publik' : undefined} />
                            </div>
                            {thesis.plagiarismNotes && (
                                <div className="db-hint" style={{ marginTop: 12 }}>Catatan: {thesis.plagiarismNotes}</div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Users size={16} /> Pembimbing</h2>
                        </div>
                        <div className="db-card-body">
                            {thesis.supervisors.length === 0 ? (
                                <p className="db-hint">Belum ada pembimbing ditetapkan.</p>
                            ) : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {thesis.supervisors.map((supervisor) => (
                                        <div key={supervisor.id} className="db-summary">
                                            <div>
                                                <b>{supervisor.name ?? '-'}</b>
                                                <small>{supervisor.nidn ?? '-'} · {supervisor.roleLabel}{supervisor.assignedAt ? ` · sejak ${supervisor.assignedAt}` : ''}</small>
                                            </div>
                                            <span className={`db-badge ${supervisor.isActive ? 'green' : 'gray'}`}>{supervisor.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Milestone Bimbingan</h2>
                            {canModify && <button className="db-btn ghost sm" type="button" onClick={() => setMilestoneOpen(true)}><Plus size={13} /> Tambah</button>}
                        </div>
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
                                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                <span className={`db-badge ${milestone.statusTone}`}>{milestone.statusLabel}</span>
                                                {canModify && (
                                                    <>
                                                        <button className="db-btn ghost sm" type="button" title="Review" onClick={() => setReviewMilestone(milestone)}><CheckCircle2 size={13} /></button>
                                                        <button className="db-btn ghost sm" type="button" title="Hapus" onClick={() => deleteMilestone(milestone)}><Trash2 size={13} /></button>
                                                    </>
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
                            {canModify && <button className="db-btn ghost sm" type="button" onClick={() => setDocumentOpen(true)}><Upload size={13} /> Unggah</button>}
                        </div>
                        <div className="db-card-body">
                            {thesis.documents.length === 0 ? (
                                <p className="db-hint">Belum ada dokumen diunggah.</p>
                            ) : (
                                <div className="crud-table-wrap">
                                    <table className="crud-table">
                                        <thead>
                                            <tr><th>Judul</th><th>Kategori</th><th>Versi</th><th>Ukuran</th><th>Diunggah</th><th style={{ width: 110 }}>Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            {thesis.documents.map((document) => (
                                                <tr key={document.id}>
                                                    <td>
                                                        <b className="crud-name">{document.title}</b>
                                                        <div className="db-hint">{document.fileName}</div>
                                                    </td>
                                                    <td><span className="db-badge gray">{document.categoryLabel}</span></td>
                                                    <td>v{document.version}</td>
                                                    <td>{document.fileSize}</td>
                                                    <td>
                                                        {document.createdAt ?? '-'}
                                                        <div className="db-hint">{document.uploadedBy ?? '-'}</div>
                                                    </td>
                                                    <td>
                                                        <div style={{ display: 'flex', gap: 6 }}>
                                                            <a className="db-btn ghost sm" href={document.downloadUrl} target="_blank" rel="noreferrer" title="Unduh"><Download size={13} /></a>
                                                            {canModify && <button className="db-btn ghost sm" type="button" title="Hapus" onClick={() => deleteDocument(document)}><Trash2 size={13} /></button>}
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
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Jadwal Sidang</h2>
                            {canScheduleDefense && <button className="db-btn ghost sm" type="button" onClick={() => setDefenseOpen(true)}><Plus size={13} /> Jadwalkan</button>}
                        </div>
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
                                                    {defense.completedAt ? ` · Selesai ${defense.completedAt}` : ''}
                                                </small>
                                                {defense.meetingLink && (
                                                    <div className="db-hint" style={{ marginTop: 4 }}>
                                                        <a href={defense.meetingLink} target="_blank" rel="noreferrer"><Link2 size={12} /> {defense.meetingLink}</a>
                                                    </div>
                                                )}
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                                    {defense.examiners.map((examiner) => (
                                                        <span key={examiner.id} className="db-badge gray">
                                                            {examiner.name ?? '-'} · {examiner.roleLabel}{examiner.score !== null ? ` · ${examiner.score}` : ''}
                                                        </span>
                                                    ))}
                                                </div>
                                                {defense.notes && <div className="db-hint" style={{ marginTop: 6 }}>Catatan: {defense.notes}</div>}
                                            </div>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, alignItems: 'flex-end' }}>
                                                <span className={`db-badge ${defense.statusTone}`}>{defense.statusLabel}</span>
                                                {defense.resultLabel && <span className={`db-badge ${defense.result === 'failed' ? 'red' : defense.result === 'passed_with_revision' ? 'amber' : 'green'}`}>{defense.resultLabel}</span>}
                                                {defense.score !== null && <span className="db-badge">{defense.score}{defense.grade ? ` · ${defense.grade}` : ''}</span>}
                                                <div style={{ display: 'flex', gap: 6 }}>
                                                    {can.grade && defense.status === 'scheduled' && (
                                                        <button className="db-btn primary sm" type="button" onClick={() => setCompleteDefense(defense)}><CheckCircle2 size={13} /> Selesaikan</button>
                                                    )}
                                                    {can.defense && defense.status === 'scheduled' && (
                                                        <button className="db-btn ghost sm" type="button" onClick={() => setCancelDefense(defense)}><XCircle size={13} /> Batalkan</button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    {thesis.status === 'completed' && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Award size={16} /> Arsip & Repositori</h2>
                                <div style={{ display: 'flex', gap: 6 }}>
                                    {thesis.repositoryPublished && <a className="db-btn ghost sm" href={urls.repository} target="_blank" rel="noreferrer"><Globe2 size={13} /> Lihat Repositori</a>}
                                    {canPublish && <button className="db-btn primary sm" type="button" onClick={() => setPublishOpen(true)}><Globe2 size={13} /> Publikasikan</button>}
                                </div>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <Info label="Nilai Akhir" value={thesis.finalScore !== null ? `${thesis.finalScore} (${thesis.finalGrade ?? '-'})` : '-'} />
                                    <Info label="Selesai" value={thesis.completedAt} />
                                    <Info label="Repositori" value={thesis.repositoryPublished ? 'Terpublikasi' : 'Belum dipublikasikan'} hint={thesis.repositoryPublishedAt} />
                                </div>
                                {thesis.plagiarismStatus === 'flagged' && !thesis.repositoryPublished && (
                                    <div className="db-error" style={{ marginTop: 12 }}>
                                        Karya ditarik dari repositori karena ditandai plagiarisme. Perbarui status cek plagiarisme menjadi "Lolos Cek" untuk mempublikasikan ulang.
                                    </div>
                                )}
                            </div>
                        </section>
                    )}
                </div>

                {approveOpen && <ApproveModal thesis={thesis} url={urls.approve} searchUrl={urls.searchLecturers} onClose={() => setApproveOpen(false)} />}
                {rejectOpen && <RejectModal url={urls.reject} onClose={() => setRejectOpen(false)} />}
                {milestoneOpen && <MilestoneModal url={urls.milestoneStore} onClose={() => setMilestoneOpen(false)} />}
                {reviewMilestone && <MilestoneReviewModal milestone={reviewMilestone} onClose={() => setReviewMilestone(null)} />}
                {defenseOpen && <DefenseModal url={urls.defenseStore} searchUrl={urls.searchLecturers} roles={options.examinerRoles} onClose={() => setDefenseOpen(false)} />}
                {completeDefense && <CompleteDefenseModal defense={completeDefense} results={options.defenseResults} onClose={() => setCompleteDefense(null)} />}
                {documentOpen && <DocumentModal url={urls.documentStore} categories={options.documentCategories} onClose={() => setDocumentOpen(false)} />}
                {finalizeOpen && <FinalizeModal thesis={thesis} url={urls.finalize} onClose={() => setFinalizeOpen(false)} />}
                {plagiarismOpen && <PlagiarismModal thesis={thesis} url={urls.plagiarism} statuses={options.plagiarismStatuses} onClose={() => setPlagiarismOpen(false)} />}

                <ConfirmModal
                    open={deleteOpen}
                    title="Hapus pengajuan skripsi?"
                    message={`"${thesis.title}" akan dipindah ke sampah.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={deleteThesis}
                    onCancel={() => setDeleteOpen(false)}
                />

                <ConfirmModal
                    open={cancelDefense !== null}
                    title="Batalkan jadwal sidang?"
                    message={cancelDefense ? `${cancelDefense.typeLabel} pada ${cancelDefense.scheduledAt} akan dibatalkan.` : ''}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={cancelDefenseAction}
                    onCancel={() => setCancelDefense(null)}
                />

                <ConfirmModal
                    open={publishOpen}
                    title="Publikasikan ke repositori?"
                    message={`"${thesis.title}" akan tampil di repositori publik dan dokumen finalnya dapat diunduh publik.`}
                    confirmLabel="Ya, publikasikan"
                    processing={processing}
                    onConfirm={publishToRepository}
                    onCancel={() => setPublishOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
