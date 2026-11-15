// Detail permohonan approval: timeline langkah, riwayat sistem, dan panel keputusan.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Ban,
    CheckCircle2,
    Clock3,
    ExternalLink,
    FileSignature,
    History,
    Info,
    ShieldCheck,
    UserRound,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Step = {
    id: number;
    order: number;
    name: string;
    status: string;
    statusLabel: string;
    approverLabel: string;
    isRequired: boolean;
    canReject: boolean;
    dueAt: string | null;
    actedByName: string | null;
    actedAt: string | null;
    notes: string | null;
};

type ActionLog = {
    id: number;
    action: string;
    actionLabel: string;
    userName: string | null;
    notes: string | null;
    createdAt: string | null;
};

type Approval = {
    id: number;
    subject: string;
    reference: string | null;
    notes: string | null;
    status: string;
    statusLabel: string;
    templateName: string | null;
    templateCode: string | null;
    requesterName: string | null;
    requesterEmail: string | null;
    submittedAt: string | null;
    completedAt: string | null;
    currentStepOrder: number | null;
    waitingMessage: string;
    approvableType: string | null;
    approvableLabel: string | null;
    steps: Step[];
    actions: ActionLog[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    approval: Approval;
    review: {
        dedicated: boolean;
        url: string | null;
        label: string | null;
        canAct: boolean;
        active: boolean;
    };
    urls: { index: string; approve: string; reject: string; cancel: string };
};

function stepTone(status: string): string {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'current') return 'gold';
    return 'gray';
}

export default function ApprovalRequestShow({ shell, can, approval, review, urls }: Props) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState<string | null>(null);

    const decide = (action: 'approve' | 'reject' | 'cancel') => {
        setProcessing(action);

        router.post(urls[action], { notes }, {
            preserveScroll: true,
            onFinish: () => setProcessing(null),
        });
    };

    const statusTone = approval.status === 'approved'
        ? 'green'
        : ['rejected', 'cancelled'].includes(approval.status)
            ? 'red'
            : 'gold';

    return (
        <AdminShell shell={shell}>
            <Head title={`${approval.subject} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Kepegawaian"
                        title={approval.subject}
                        description={`Referensi: ${approval.reference ?? 'Tanpa referensi'} · Diajukan oleh ${approval.requesterName ?? '-'}`}
                        badges={[
                            approval.statusLabel,
                            approval.templateName ?? 'Tanpa template',
                            approval.currentStepOrder ? `Langkah ${approval.currentStepOrder}` : 'Selesai',
                        ]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={14} /> Kembali ke daftar</a>}
                    />

                    <section className="db-stats" aria-label="Ringkasan permohonan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><ShieldCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{approval.templateName ?? '-'}</span>
                                <span className="db-stat-label">Template Approval</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><UserRound size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{approval.requesterName ?? '-'}</span>
                                <span className="db-stat-label">Pemohon</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Clock3 size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{approval.submittedAt ?? '-'}</span>
                                <span className="db-stat-label">Waktu Submit</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className={`db-stat-icon ${statusTone === 'green' ? 'green' : statusTone === 'red' ? 'red' : 'gold'}`}><BadgeCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{approval.statusLabel}</span>
                                <span className="db-stat-label">Status Permohonan</span>
                            </div>
                        </div>
                    </section>

                    {approval.notes && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Info size={16} /> Catatan Pengaju</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-hint" style={{ marginTop: 0, whiteSpace: 'pre-line' }}>{approval.notes}</div>
                            </div>
                        </section>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 2fr) minmax(280px, 1fr)', gap: 16, alignItems: 'start' }}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><History size={16} /> Timeline & Langkah Approval</h2>
                            </div>
                            <div className="db-card-body">
                                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                                    {approval.steps.length === 0 && (
                                        <div className="db-hint" style={{ marginTop: 0 }}>Belum ada langkah persetujuan pada permohonan ini.</div>
                                    )}
                                    {approval.steps.map((step) => (
                                        <div
                                            key={step.id}
                                            style={{
                                                border: '1px solid var(--db-line)',
                                                borderRadius: 12,
                                                padding: 14,
                                                background: 'var(--db-card)',
                                            }}
                                        >
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                                                <span className={`db-badge ${stepTone(step.status)}`} style={{ width: 28, height: 28, justifyContent: 'center', borderRadius: 999, padding: 0 }}>
                                                    {step.order}
                                                </span>
                                                <div style={{ flex: 1, minWidth: 160 }}>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{step.name}</b>
                                                    <small className="db-hint">{step.approverLabel}</small>
                                                </div>
                                                <span className={`db-badge ${stepTone(step.status)}`}>{step.statusLabel}</span>
                                                {step.isRequired && <span className="db-badge gray">Wajib</span>}
                                                {step.canReject && <span className="db-badge gray">Boleh menolak</span>}
                                            </div>
                                            {step.dueAt && (
                                                <div className="db-hint" style={{ marginTop: 8 }}>
                                                    <Clock3 size={12} style={{ marginRight: 4 }} /> Tenggat: {step.dueAt}
                                                </div>
                                            )}
                                            {(step.actedByName || step.actedAt || step.notes) && (
                                                <div className="db-hint" style={{ marginTop: 8, paddingTop: 8, borderTop: '1px solid var(--db-line)' }}>
                                                    <div>
                                                        <CheckCircle2 size={12} style={{ marginRight: 4 }} />
                                                        {step.actedByName ?? 'System'}
                                                        {step.actedAt ? ` · ${step.actedAt}` : ''}
                                                    </div>
                                                    {step.notes && <div style={{ marginTop: 4, color: 'var(--db-ink)' }}>{step.notes}</div>}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </section>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ShieldCheck size={16} /> Proses Keputusan</h2>
                                </div>
                                <div className="db-card-body">
                                    {review.dedicated ? (
                                        <>
                                            <div className="db-hint" style={{ marginTop: 0, marginBottom: 12 }}>
                                                <Info size={13} style={{ marginRight: 4 }} />
                                                Pengajuan ini membutuhkan data review khusus modul. Proses keputusan dari halaman detail agar data operasional tidak terlewat.
                                            </div>
                                            {review.url && (
                                                <a className="db-btn primary" href={review.url} style={{ width: '100%', justifyContent: 'center' }}>
                                                    <ExternalLink size={14} /> {review.label ?? 'Buka Review Modul'}
                                                </a>
                                            )}
                                        </>
                                    ) : review.active ? (
                                        <>
                                            <TextareaField
                                                label="Catatan tindakan (opsional)"
                                                rows={3}
                                                placeholder="Tuliskan alasan persetujuan atau penolakan…"
                                                value={notes}
                                                onChange={(event) => setNotes(event.target.value)}
                                            />
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 12 }}>
                                                <button
                                                    className="db-btn primary"
                                                    type="button"
                                                    onClick={() => decide('approve')}
                                                    disabled={!review.canAct || processing !== null}
                                                    style={{ justifyContent: 'center' }}
                                                >
                                                    <CheckCircle2 size={14} /> {processing === 'approve' ? 'Memproses…' : 'Setujui Langkah Ini'}
                                                </button>
                                                <button
                                                    className="db-btn danger"
                                                    type="button"
                                                    onClick={() => decide('reject')}
                                                    disabled={!review.canAct || processing !== null}
                                                    style={{ justifyContent: 'center' }}
                                                >
                                                    <XCircle size={14} /> {processing === 'reject' ? 'Memproses…' : 'Tolak Permohonan'}
                                                </button>
                                                {can.update && (
                                                    <button
                                                        className="db-btn ghost"
                                                        type="button"
                                                        onClick={() => decide('cancel')}
                                                        disabled={processing !== null}
                                                        style={{ justifyContent: 'center' }}
                                                    >
                                                        <Ban size={14} /> {processing === 'cancel' ? 'Memproses…' : 'Batalkan Request'}
                                                    </button>
                                                )}
                                            </div>
                                            {!review.canAct && (
                                                <div className="db-hint" style={{ marginTop: 10 }}>
                                                    Anda tidak menjadi approver untuk langkah yang sedang berjalan saat ini.
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <div style={{ textAlign: 'center', padding: '18px 0' }}>
                                            <CheckCircle2 size={34} style={{ color: 'var(--db-green, #16a34a)', marginBottom: 8 }} />
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>Permohonan Telah Selesai</b>
                                            <div className="db-hint" style={{ marginTop: 4 }}>Tidak ada tindakan lanjutan yang diperlukan untuk status saat ini.</div>
                                        </div>
                                    )}
                                    {!review.dedicated && review.active && <div className="db-hint" style={{ marginTop: 10 }}>{approval.waitingMessage}</div>}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><History size={16} /> Riwayat Sistem</h2>
                                </div>
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                                        {approval.actions.length === 0 && (
                                            <div className="db-hint" style={{ marginTop: 0 }}>Belum ada riwayat sistem.</div>
                                        )}
                                        {approval.actions.map((action) => (
                                            <div key={action.id} style={{ borderBottom: '1px solid var(--db-line)', paddingBottom: 8 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{action.actionLabel}</b>
                                                <small className="db-hint">{action.userName ?? 'System'} · {action.createdAt ?? '-'}</small>
                                                {action.notes && <div className="db-hint" style={{ color: 'var(--db-ink)', marginTop: 4 }}>{action.notes}</div>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
