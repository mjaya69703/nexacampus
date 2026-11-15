// Detail pengajuan cicilan — simulasi, approval steps, putusan (paritas Blade).
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, CheckCircle2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type SimulationRow = { no: string | number; amount: string; fee: string; dueDate: string };
type Installment = { no: number; amount: string; dueDate: string | null; status: string };
type ApprovalStep = { name: string; status: string; by: string | null; at: string | null };

type InstallmentRequest = {
    id: number; invoice: string | null; invoiceUrl: string | null;
    student: string; nim: string | null; program: string | null;
    tenor: number; fee: string; simulated: string;
    simulation: SimulationRow[]; reason: string | null; financeNotes: string | null;
    status: string; statusTone: string; statusText: string;
    reviewedBy: string | null; reviewedAt: string | null; at: string | null;
    isReviewable: boolean; installments: Installment[]; approvalSteps: ApprovalStep[];
};

type Props = {
    shell: ShellProps;
    request: InstallmentRequest;
    can: { review: boolean };
    urls: { index: string; approve: string; reject: string };
};

export default function InstallmentRequestShow({ shell, request, can, urls }: Props) {
    const [confirm, setConfirm] = useState<'approve' | 'reject' | null>(null);
    const [processing, setProcessing] = useState(false);
    const form = useForm({ finance_notes: request.financeNotes ?? '' });

    const submitDecision = () => {
        if (!confirm) return;
        setProcessing(true);
        form.post(confirm === 'approve' ? urls.approve : urls.reject, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirm(null);
            },
        });
    };

    const errors = form.errors as Record<string, string>;

    const summary: [string, React.ReactNode][] = [
        ['Mahasiswa', <b key="s">{request.student} {request.nim ? `(${request.nim})` : ''}</b>],
        ['Prodi', request.program ?? '-'],
        ['Tagihan', request.invoiceUrl ? <a key="i" href={request.invoiceUrl}>{request.invoice}</a> : (request.invoice ?? '-')],
        ['Tenor', <b key="t">{request.tenor}× bayar</b>],
        ['Biaya Layanan', request.fee],
        ['Total Simulasi', <b key="sim">{request.simulated}</b>],
        ['Status', <span key="st" className={`db-badge ${request.statusTone}`}>{request.statusText}</span>],
        ['Alasan Mahasiswa', request.reason ?? '-'],
        ['Diajukan', request.at ?? '-'],
        ['Direview', request.reviewedAt ? `${request.reviewedAt}${request.reviewedBy ? ` oleh ${request.reviewedBy}` : ''}` : '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Cicilan ${request.invoice ?? ''} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Keuangan"
                        title={`Cicilan · ${request.invoice ?? '-'}`}
                        description={`${request.student} — ${request.tenor}× bayar`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.review && request.isReviewable && (
                                    <>
                                        <button type="button" className="db-btn light" disabled={processing} onClick={() => setConfirm('approve')}>
                                            <CheckCircle2 size={15} /> Setujui
                                        </button>
                                        <button type="button" className="db-btn light" disabled={processing} onClick={() => setConfirm('reject')}>
                                            <XCircle size={15} /> Tolak
                                        </button>
                                    </>
                                )}
                            </>
                        }
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan Pengajuan</h2>
                        </div>
                        <div className="db-card-body tight">
                            {summary.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <span className="db-hint">{label}</span>
                                    <span>{value}</span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {request.simulation.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Simulasi Angsuran</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Termin</th><th style={{ textAlign: 'right' }}>Angsuran</th><th style={{ textAlign: 'right' }}>Biaya</th><th>Jatuh Tempo</th></tr>
                                        </thead>
                                        <tbody>
                                            {request.simulation.map((row, i) => (
                                                <tr key={i}>
                                                    <td><b>Ke-{row.no}</b></td>
                                                    <td style={{ textAlign: 'right' }}><b>{row.amount}</b></td>
                                                    <td style={{ textAlign: 'right' }}>{row.fee}</td>
                                                    <td><small className="db-hint">{row.dueDate}</small></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    )}

                    {request.approvalSteps.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Jejak Approval</h2>
                            </div>
                            <div className="db-card-body tight">
                                {request.approvalSteps.map((step, i) => (
                                    <div className="db-summary" key={i}>
                                        <span className="db-hint">{step.name}</span>
                                        <span><span className="db-badge">{step.status}</span>{step.by ? <small className="db-hint"> · {step.by}{step.at ? ` · ${step.at}` : ''}</small> : null}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}

                    {request.installments.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Termin Terbentuk ({request.installments.length})</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Termin</th><th style={{ textAlign: 'right' }}>Nominal</th><th>Jatuh Tempo</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            {request.installments.map((inst) => (
                                                <tr key={inst.no}>
                                                    <td><b>Ke-{inst.no}</b></td>
                                                    <td style={{ textAlign: 'right' }}><b>{inst.amount}</b></td>
                                                    <td><small className="db-hint">{inst.dueDate ?? '-'}</small></td>
                                                    <td><span className="db-badge">{inst.status}</span></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    )}

                    {can.review && request.isReviewable && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Catatan Keuangan</h2>
                            </div>
                            <div className="db-card-body">
                                <TextareaField
                                    label="Catatan (opsional, dipakai saat setuju / tolak)"
                                    value={form.data.finance_notes}
                                    onChange={(e) => form.setData('finance_notes', e.target.value)}
                                    error={errors.finance_notes}
                                />
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={confirm === 'approve'}
                    title="Setujui pengajuan cicilan?"
                    message="Termin cicilan dibentuk dari simulasi dan approval berjenjang dijalankan."
                    confirmLabel="Ya, setujui"
                    danger={false}
                    processing={processing}
                    onConfirm={submitDecision}
                    onCancel={() => setConfirm(null)}
                />
                <ConfirmModal
                    open={confirm === 'reject'}
                    title="Tolak pengajuan cicilan?"
                    message="Mahasiswa perlu mengajukan ulang atau melunasi sekaligus."
                    confirmLabel="Ya, tolak"
                    danger
                    processing={processing}
                    onConfirm={submitDecision}
                    onCancel={() => setConfirm(null)}
                />
            </div>
        </AdminShell>
    );
}
