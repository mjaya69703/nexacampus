// Keberatan nilai — daftar pengajuan mahasiswa dan keputusan dosen.
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle, Check, CheckCircle2, Clock, FileText, Filter, Gavel, MessageSquareWarning,
    Paperclip, Search, User, X, XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Appeal = {
    id: number; student: string; nim: string; course: string; academicYear: string; status: string;
    category: string | null; reason: string; expectedOutcome: string | null; lecturerResponse: string | null;
    originalScore: number | string | null; requestedScore: number | string | null;
    currentScore: number | string | null; resolvedScore: number | string | null;
    submittedAt: string; reviewedAt: string | null; resolvedAt: string | null; canResolve: boolean;
    attachments: { id: number; name: string; url: string }[];
};

type Summary = { submitted: number; under_review: number; resolved: number };

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    statusFilter: string;
    appealRows: Appeal[];
    summary: Summary;
    urls: { index: string; resolve: string };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'closed') return 'gray';
    if (status === 'under_review') return 'amber';
    return 'gray';
};

const statusLabel: Record<string, string> = {
    submitted: 'Diajukan', under_review: 'Ditinjau', approved: 'Disetujui', rejected: 'Ditolak', closed: 'Ditutup',
};

function AppealCard({ appeal, urls }: { appeal: Appeal; urls: Props['urls'] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ decision: 'approved' | 'rejected'; lecturerResponse: string; resolvedScore: string | number }>({
        decision: 'approved',
        lecturerResponse: '',
        resolvedScore: appeal.requestedScore ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.resolve.replace('__APPEAL__', String(appeal.id)), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>{appeal.student}</b>
                    <small>{appeal.nim} · {appeal.course} · {appeal.academicYear}</small>
                </div>
                <span className={`db-badge ${statusTone(appeal.status)}`}>{statusLabel[appeal.status] ?? appeal.status}</span>
            </div>

            <div style={{ display: 'grid', gap: 6, marginTop: 8, fontSize: 13 }}>
                <div><b>Kategori:</b> {appeal.category ?? '-'}</div>
                <div><b>Alasan:</b> {appeal.reason}</div>
                {appeal.expectedOutcome && <div><b>Harapan mahasiswa:</b> {appeal.expectedOutcome}</div>}
                <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                    <span><b>Nilai saat ini:</b> {appeal.currentScore ?? appeal.originalScore ?? '-'}</span>
                    <span><b>Diminta:</b> {appeal.requestedScore ?? '-'}</span>
                    {appeal.resolvedScore !== null && <span><b>Hasil koreksi:</b> {appeal.resolvedScore}</span>}
                </div>
                <div className="db-hint">Diajukan {appeal.submittedAt}{appeal.reviewedAt ? ` · ditinjau ${appeal.reviewedAt}` : ''}{appeal.resolvedAt ? ` · selesai ${appeal.resolvedAt}` : ''}</div>
            </div>

            {appeal.lecturerResponse && (
                <div className="db-note info" style={{ marginTop: 8 }}>
                    <MessageSquareWarning size={14} />
                    <span>Respons Anda: {appeal.lecturerResponse}</span>
                </div>
            )}

            {appeal.attachments.length > 0 && (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 8 }}>
                    {appeal.attachments.map((attachment) => (
                        <a key={attachment.id} className="db-btn ghost sm" href={attachment.url} target="_blank" rel="noreferrer">
                            <Paperclip size={12} /> {attachment.name}
                        </a>
                    ))}
                </div>
            )}

            {appeal.canResolve && (
                <div className="db-class-actions">
                    <button className="db-btn primary sm" type="button" onClick={() => setOpen((value) => !value)}>
                        <Gavel size={12} /> {open ? 'Tutup keputusan' : 'Putuskan'}
                    </button>
                </div>
            )}

            {open && appeal.canResolve && (
                <form onSubmit={submit} style={{ display: 'grid', gap: 12, marginTop: 10, borderTop: '1px solid var(--db-line)', paddingTop: 10 }}>
                    <div className="crud-grid">
                        <div>
                            <label className="crud-label">Keputusan <span>*</span></label>
                            <select className="db-input" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value as 'approved' | 'rejected')}>
                                <option value="approved">Setujui koreksi nilai</option>
                                <option value="rejected">Tolak pengajuan</option>
                            </select>
                            {form.errors.decision && <div className="db-error">{form.errors.decision}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Skor koreksi {form.data.decision === 'approved' && <span>*</span>}</label>
                            <input
                                className="db-input"
                                type="number"
                                min={0}
                                max={100}
                                step="0.01"
                                value={form.data.resolvedScore}
                                onChange={(event) => form.setData('resolvedScore', event.target.value)}
                                disabled={form.data.decision !== 'approved'}
                            />
                            {form.errors.resolvedScore && <div className="db-error">{form.errors.resolvedScore}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Respons dosen <span>*</span></label>
                            <textarea
                                className="db-input"
                                rows={4}
                                value={form.data.lecturerResponse}
                                onChange={(event) => form.setData('lecturerResponse', event.target.value)}
                                placeholder="Jelaskan hasil peninjauan, dasar keputusan, dan tindak lanjutnya."
                            />
                            {form.errors.lecturerResponse && <div className="db-error">{form.errors.lecturerResponse}</div>}
                            <div className="crud-hint">Minimal 10 karakter.</div>
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className={`db-btn sm ${form.data.decision === 'approved' ? 'primary' : 'danger'}`} type="submit" disabled={form.processing}>
                            {form.data.decision === 'approved' ? <Check size={12} /> : <X size={12} />} {form.processing ? 'Menyimpan…' : 'Simpan keputusan'}
                        </button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setOpen(false)}>Batal</button>
                    </div>
                </form>
            )}
        </article>
    );
}

export default function GradeAppealsIndex({ shell, hasProfile, statusFilter, appealRows, summary, urls }: Props) {
    const [status, setStatus] = useState(statusFilter);

    const applyFilter = (value: string) => {
        setStatus(value);
        router.get(urls.index, { statusFilter: value }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Keberatan Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Gavel}
                        eyebrow="Akademik · Keberatan Nilai"
                        title="Keberatan Nilai"
                        description="Tinjau pengajuan keberatan nilai mahasiswa dan berikan keputusan yang tercatat."
                        badges={[`${summary.submitted} diajukan`, `${summary.under_review} ditinjau`, `${summary.resolved} selesai`]}
                    />

                    {!hasProfile && (
                        <div className="db-note warning"><AlertTriangle size={15} /><span>Profil dosen belum tersedia sehingga daftar keberatan tidak dapat dimuat.</span></div>
                    )}

                    <section className="db-stats" aria-label="Ringkasan keberatan">
                        <div className="db-stat"><span className="db-stat-icon gold"><Clock size={20} /></span><div><span className="db-stat-num">{summary.submitted}</span><span className="db-stat-label">Diajukan</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><Search size={20} /></span><div><span className="db-stat-num">{summary.under_review}</span><span className="db-stat-label">Ditinjau</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><CheckCircle2 size={20} /></span><div><span className="db-stat-num">{summary.resolved}</span><span className="db-stat-label">Selesai</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon red"><XCircle size={20} /></span><div><span className="db-stat-num">{appealRows.filter((row) => row.status === 'rejected').length}</span><span className="db-stat-label">Ditolak</span></div></div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Filter size={16} /> Filter status</h2>
                        </div>
                        <div className="db-card-body" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            {[
                                { value: 'active', label: 'Aktif' },
                                { value: 'submitted', label: 'Diajukan' },
                                { value: 'under_review', label: 'Ditinjau' },
                                { value: 'approved', label: 'Disetujui' },
                                { value: 'rejected', label: 'Ditolak' },
                                { value: 'all', label: 'Semua' },
                            ].map((option) => (
                                <button
                                    key={option.value}
                                    className={`db-btn sm ${status === option.value ? 'primary' : 'ghost'}`}
                                    type="button"
                                    onClick={() => applyFilter(option.value)}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Gavel size={16} /> Daftar keberatan ({appealRows.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {appealRows.length === 0 ? (
                                <EmptyState icon={Gavel} title="Tidak ada keberatan" description="Belum ada pengajuan yang cocok dengan filter ini." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {appealRows.map((appeal) => <AppealCard key={appeal.id} appeal={appeal} urls={urls} />)}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
