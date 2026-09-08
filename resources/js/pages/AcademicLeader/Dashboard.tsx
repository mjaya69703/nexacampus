// Dashboard pimpinan akademik — ruang keputusan (Inertia React).
// Blok: menunggu keputusan saya, kelas & dosen perlu perhatian,
// BKD terbaru, performa terbaru. Bukan port monitoring mentah.
import { Head, router } from '@inertiajs/react';
import {
    Bell, BookOpen, CircleCheck, Clock3, FileText, FlaskConical,
    GraduationCap, Info, Scale, ShieldCheck, TrendingUp, UserRound, UsersRound, X,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import '../../../css/dashboard.css';

type PendingApproval = {
    stepId: number; submissionId: number; stepName: string; subject: string; ownerName: string;
    programName: string | null; totalSks: number; dueLabel: string | null; isOverdue: boolean;
};
type WaitingOther = {
    ownerName: string; programName: string | null; totalSks: number;
    stepName: string; dueLabel: string | null;
};
type ProblemClass = { level: string; title: string; description: string; target: string; url: string };
type AttentionLecturer = {
    id: number; name: string; programName: string | null;
    workloadLabel: string; score: number | null; url: string;
};
type Submission = {
    id: number; ownerName: string; programName: string | null; periodName: string | null;
    totalSks: number; status: string; statusLabel: string; url: string;
};
type Review = {
    id: number; ownerName: string; programName: string | null;
    finalScore: number | null; edomScore: number | null; responses: number; url: string;
};

type Props = {
    shell: ShellProps;
    hasScope: boolean;
    scopeLabel: string | null;
    stats: {
        bkd: number; bkdApproval: number; avgSks: number; avgPerformance: number;
        lecturers: number; classes: number; problemClasses: number; alerts: number;
    };
    pendingApprovals: PendingApproval[];
    waitingOnOthers: WaitingOther[];
    problemClasses: ProblemClass[];
    attentionLecturers: AttentionLecturer[];
    submissions: Submission[];
    reviews: Review[];
    urls: { lecturers: string; classes: string; workloads: string; reports: string; edom: string };
};

const levelTone = (level: string): string => {
    if (level === 'danger') return 'red';
    if (level === 'warning') return 'amber';
    return 'gray';
};

export default function AcademicLeaderDashboard({
    shell, hasScope, scopeLabel, stats, pendingApprovals, waitingOnOthers, problemClasses,
    attentionLecturers, submissions, reviews, urls,
}: Props) {
    const [notes, setNotes] = useState<Record<number, string>>({});
    const [processing, setProcessing] = useState<number | null>(null);
    const [confirming, setConfirming] = useState<{ item: PendingApproval; decision: 'approve' | 'reject' } | null>(null);
    const [noteError, setNoteError] = useState<number | null>(null);

    const askDecide = (item: PendingApproval, decision: 'approve' | 'reject') => {
        if (decision === 'reject' && !(notes[item.stepId] ?? '').trim()) {
            setNoteError(item.stepId);
            return;
        }
        setNoteError(null);
        setConfirming({ item, decision });
    };

    const confirmDecide = () => {
        if (!confirming || processing !== null) return;
        const { item, decision } = confirming;
        setProcessing(item.stepId);
        router.post(`/academic-leader/approvals/steps/${item.stepId}/${decision}`, {
            notes: notes[item.stepId] ?? '',
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(null);
                setConfirming(null);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Pimpinan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <section className="db-hero">
                        <div className="db-hero-inner">
                            <div className="db-hero-copy">
                                <span className="db-hero-icon"><Scale size={26} /></span>
                                <div>
                                    <small>Pemantauan akademik</small>
                                    <h1>Ruang Keputusan Pimpinan</h1>
                                    <p>Setujui BKD yang menunggu, sidak kelas bermasalah, dan pantau dosen sesuai scope jabatan aktif.</p>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 12 }}>
                                        <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{stats.bkd} BKD</span>
                                        <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{stats.bkdApproval} approval</span>
                                        <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{stats.classes} kelas</span>
                                        <span className="db-badge gray" style={{ background: 'rgba(255,255,255,.14)', color: '#fff', border: '1px solid rgba(255,255,255,.22)' }}>{stats.alerts} alert</span>
                                    </div>
                                </div>
                            </div>
                            <div className="db-hero-actions">
                                <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, padding: '10px 16px', borderRadius: 999, border: '1px solid rgba(255,255,255,.22)', background: 'rgba(255,255,255,.1)', color: '#eaf1f9', fontSize: 13 }}>
                                    Scope <b style={{ fontSize: 15, color: '#fff' }}>{hasScope ? scopeLabel ?? 'Tersedia' : 'Belum diatur'}</b>
                                </div>
                            </div>
                        </div>
                    </section>

                    {!hasScope && (
                        <div className="db-note gold"><ShieldCheck size={15} /><span><b>Scope belum tersedia.</b> Akun ini belum memiliki jabatan fakultas atau program studi aktif, jadi data pemantauan belum bisa ditampilkan.</span></div>
                    )}

                    <section className="db-stats" aria-label="Ringkasan pimpinan">
                        <a className="db-stat" href={urls.workloads}>
                            <span className="db-stat-icon red"><Clock3 size={20} /></span>
                            <div><span className="db-stat-num">{pendingApprovals.length}</span><span className="db-stat-label">Menunggu saya</span></div>
                        </a>
                        <a className="db-stat" href={urls.classes}>
                            <span className="db-stat-icon gold"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.problemClasses}</span><span className="db-stat-label">Kelas bermasalah</span></div>
                        </a>
                        <a className="db-stat" href={urls.lecturers}>
                            <span className="db-stat-icon"><UsersRound size={20} /></span>
                            <div><span className="db-stat-num">{stats.lecturers}</span><span className="db-stat-label">Dosen scope</span></div>
                        </a>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><TrendingUp size={20} /></span>
                            <div><span className="db-stat-num">{stats.avgPerformance > 0 ? stats.avgPerformance.toFixed(1) : '-'}</span><span className="db-stat-label">Rata2 performa</span></div>
                        </div>
                    </section>

                    <section className="db-card" style={{ borderColor: pendingApprovals.length > 0 ? 'var(--db-gold-strong)' : undefined }}>
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Scale size={16} /> Menunggu keputusan saya</h2>
                            <span className={`db-badge ${pendingApprovals.length > 0 ? 'amber' : 'green'}`}>{pendingApprovals.length}</span>
                        </div>
                        <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                            {pendingApprovals.length === 0 && waitingOnOthers.length === 0 && (
                                <div className="db-empty-calm">
                                    <CircleCheck size={22} />
                                    <div><b>Tidak ada antrean.</b><small>Tidak ada pengajuan yang menunggu keputusan saat ini.</small></div>
                                </div>
                            )}
                            {pendingApprovals.map((item) => (
                                <div key={item.stepId} style={{ border: '1px solid var(--db-line)', borderRadius: 12, padding: '14px 16px', display: 'grid', gap: 10 }}>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' }}>
                                        <div>
                                            <b style={{ color: 'var(--db-heading)', fontSize: 13.5 }}>{item.subject}</b>
                                            <small className="db-hint" style={{ display: 'block' }}>
                                                {item.ownerName}{item.programName ? ` · ${item.programName}` : ''} · {item.totalSks} SKS · {item.stepName}
                                            </small>
                                        </div>
                                        {item.isOverdue
                                            ? <span className="db-badge red">Terlambat{ item.dueLabel ? ` · ${item.dueLabel}` : ''}</span>
                                            : <span className="db-badge gray">{item.dueLabel ? `SLA ${item.dueLabel}` : 'Tanpa SLA'}</span>}
                                    </div>
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        <input
                                            className="db-input"
                                            style={{ minHeight: 36 }}
                                            value={notes[item.stepId] ?? ''}
                                            onChange={(e) => {
                                                setNotes({ ...notes, [item.stepId]: e.target.value });
                                                if (noteError === item.stepId) setNoteError(null);
                                            }}
                                            placeholder="Catatan keputusan (wajib untuk pengembalian)"
                                        />
                                        {noteError === item.stepId && (
                                            <small className="db-error">Tulis alasan pengembalian terlebih dahulu.</small>
                                        )}
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            <button
                                                type="button"
                                                className="db-btn primary sm"
                                                style={{ flex: 1 }}
                                                disabled={processing === item.stepId}
                                                onClick={() => askDecide(item, 'approve')}
                                            >
                                                <CircleCheck size={14} /> {processing === item.stepId ? '…' : 'Setujui'}
                                            </button>
                                            <button
                                                type="button"
                                                className="db-btn ghost sm"
                                                style={{ flex: 1 }}
                                                disabled={processing === item.stepId}
                                                onClick={() => askDecide(item, 'reject')}
                                            >
                                                <X size={14} /> Kembalikan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {waitingOnOthers.length > 0 && (
                                <div>
                                    <small className="db-hint" style={{ display: 'block', fontWeight: 800, textTransform: 'uppercase', fontSize: 10, letterSpacing: '.07em', margin: '4px 0 8px' }}>
                                        Menunggu di tahap lain
                                    </small>
                                    <div style={{ display: 'grid', gap: 8 }}>
                                        {waitingOnOthers.map((item, index) => (
                                            <div key={`${item.ownerName}-${index}`} className="db-summary" style={{ border: '1px solid var(--db-line)', borderRadius: 10, padding: '10px 12px' }}>
                                                <span className="db-summary-icon"><Clock3 size={16} /></span>
                                                <div>
                                                    <b>{item.ownerName} · {item.totalSks} SKS</b>
                                                    <small>Sedang di tahap: {item.stepName}{item.programName ? ` · ${item.programName}` : ''}{item.dueLabel ? ` · SLA ${item.dueLabel}` : ''}</small>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Bell size={16} /> Kelas perlu perhatian</h2>
                                    <a className="db-btn ghost sm" href={urls.classes}>Semua kelas</a>
                                </div>
                                <div className="db-card-body tight">
                                    {problemClasses.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Tidak ada kelas bermasalah di scope Anda.</p>
                                    )}
                                    {problemClasses.map((alert, index) => (
                                        <div className="db-summary" key={`${alert.title}-${index}`}>
                                            <span className={`db-summary-icon ${alert.level === 'danger' ? '' : 'gold'}`} style={alert.level === 'danger' ? { color: 'var(--db-red)', background: 'var(--db-red-soft)' } : undefined}>
                                                <Info size={17} />
                                            </span>
                                            <div>
                                                <b>{alert.title}</b>
                                                <small>{alert.description}</small>
                                            </div>
                                            <a className="db-btn ghost sm" href={alert.url}>Sidak</a>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><GraduationCap size={16} /> BKD terbaru</h2>
                                    <a className="db-btn ghost sm" href={urls.workloads}>Semua BKD</a>
                                </div>
                                <div className="db-card-body tight">
                                    {submissions.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada pengajuan BKD pada scope ini.</p>
                                    )}
                                    {submissions.map((submission) => (
                                        <div className="db-summary" key={submission.id}>
                                            <span className="db-summary-icon"><FileText size={17} /></span>
                                            <div>
                                                <b>{submission.ownerName} · {submission.totalSks} SKS</b>
                                                <small>{[submission.periodName, submission.programName, submission.statusLabel].filter(Boolean).join(' · ')}</small>
                                            </div>
                                            <span className="db-badge gray">{submission.statusLabel}</span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h3 className="db-card-title"><UserRound size={16} /> Dosen perlu perhatian</h3>
                                    <a className="db-btn ghost sm" href={urls.lecturers}>Semua dosen</a>
                                </div>
                                <div className="db-card-body tight">
                                    {attentionLecturers.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Semua dosen tertib: BKD sudah diajukan dan skor di atas ambang.</p>
                                    )}
                                    {attentionLecturers.map((lecturer) => (
                                        <div className="db-summary" key={lecturer.id}>
                                            <span className="db-summary-icon gold"><UserRound size={17} /></span>
                                            <div>
                                                <b>{lecturer.name}</b>
                                                <small>
                                                    {[lecturer.programName, lecturer.workloadLabel].filter(Boolean).join(' · ')}
                                                    {lecturer.score !== null ? ` · skor ${lecturer.score}` : ''}
                                                </small>
                                            </div>
                                            <a className="db-btn ghost sm" href={lecturer.url}>Profil</a>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h3 className="db-card-title"><FlaskConical size={16} /> Performa terbaru</h3></div>
                                <div className="db-card-body tight">
                                    {reviews.length === 0 && (
                                        <p className="db-hint" style={{ margin: 0 }}>Belum ada review performa pada scope ini.</p>
                                    )}
                                    {reviews.map((review) => (
                                        <div className="db-summary" key={review.id}>
                                            <span className="db-summary-icon green"><TrendingUp size={17} /></span>
                                            <div>
                                                <b>{review.ownerName}</b>
                                                <small>
                                                    Akhir {review.finalScore ?? '-'} · EDOM {review.edomScore ?? '-'} · {review.responses} respon{review.programName ? ` · ${review.programName}` : ''}
                                                </small>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <div className="db-note info">
                                <Info size={15} />
                                <span>Keputusan approval tercatat atas nama Anda beserta catatannya. Penolakan wajib disertai alasan.</span>
                            </div>
                        </div>
                    </div>
                </div>
            {confirming && (
                <div
                    className="db-modal-backdrop"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Konfirmasi keputusan"
                    onMouseDown={(e) => { if (e.target === e.currentTarget && processing === null) setConfirming(null); }}
                >
                    <div className="db-modal">
                        <span className={`db-modal-icon ${confirming.decision === 'approve' ? 'green' : 'red'}`}>
                            {confirming.decision === 'approve' ? <CircleCheck size={20} /> : <X size={20} />}
                        </span>
                        <h3>{confirming.decision === 'approve' ? 'Setujui pengajuan ini?' : 'Kembalikan untuk revisi?'}</h3>
                        <p>
                            {confirming.item.subject} — {confirming.item.ownerName}
                            {confirming.item.programName ? ` · ${confirming.item.programName}` : ''} · {confirming.item.totalSks} SKS.
                            {(notes[confirming.item.stepId] ?? '').trim() && (
                                <span style={{ display: 'block', marginTop: 6, fontStyle: 'italic' }}>“{(notes[confirming.item.stepId] ?? '').trim()}”</span>
                            )}
                        </p>
                        <div className="db-modal-actions">
                            <button type="button" className="db-btn ghost" disabled={processing !== null} onClick={() => setConfirming(null)}>
                                Batal
                            </button>
                            <button
                                type="button"
                                className={`db-btn sm ${confirming.decision === 'approve' ? 'primary' : 'danger'}`}
                                style={{ minHeight: 40, padding: '0 18px', fontSize: 13 }}
                                disabled={processing !== null}
                                onClick={confirmDecide}
                            >
                                {processing !== null ? 'Memproses…' : confirming.decision === 'approve' ? 'Ya, setujui' : 'Ya, kembalikan'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            </div>
        </AdminShell>
    );
}
