// Detail pembayaran — bukti, verifikasi/tolak, kwitansi (paritas Blade).
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, FileDown, Receipt, XCircle } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Payment = {
    id: number; number: string; invoice: string | null; invoiceUrl: string | null;
    student: string; nim: string | null; program: string | null; target: string;
    amount: string; method: string; methodText: string; reference: string | null;
    status: string; statusTone: string; statusText: string;
    paidAt: string | null; submittedBy: string | null;
    verifiedAt: string | null; verifiedBy: string | null;
    notes: string | null; verificationNotes: string | null;
    proofUrl: string | null; proofIsImage: boolean; proofIsPdf: boolean;
    isPending: boolean; receiptUrl: string | null;
};

type Props = {
    shell: ShellProps;
    payment: Payment;
    can: { verify: boolean };
    urls: { index: string; verify: string; reject: string };
};

export default function PaymentShow({ shell, payment, can, urls }: Props) {
    const [confirm, setConfirm] = useState<'verify' | 'reject' | null>(null);
    const [processing, setProcessing] = useState(false);
    const form = useForm({ verification_notes: payment.verificationNotes ?? '' });

    const submitDecision = () => {
        if (!confirm) return;
        setProcessing(true);
        form.post(confirm === 'verify' ? urls.verify : urls.reject, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirm(null);
            },
        });
    };

    const errors = form.errors as Record<string, string>;

    const summary: [string, React.ReactNode][] = [
        ['Mahasiswa', <b key="s">{payment.student} {payment.nim ? `(${payment.nim})` : ''}</b>],
        ['Prodi', payment.program ?? '-'],
        ['Tagihan', payment.invoiceUrl ? <a key="i" href={payment.invoiceUrl}>{payment.invoice}</a> : (payment.invoice ?? '-')],
        ['Target', payment.target],
        ['Nominal', <b key="a">{payment.amount}</b>],
        ['Metode', payment.methodText],
        ['Referensi Transaksi', payment.reference ?? '-'],
        ['Status', <span key="st" className={`db-badge ${payment.statusTone}`}>{payment.statusText}</span>],
        ['Waktu Bayar', payment.paidAt ?? '-'],
        ['Diajukan Oleh', payment.submittedBy ?? '-'],
        ['Diverifikasi', payment.verifiedAt ? `${payment.verifiedAt}${payment.verifiedBy ? ` oleh ${payment.verifiedBy}` : ''}` : '-'],
        ['Catatan Pengaju', payment.notes ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Pembayaran ${payment.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Receipt}
                        eyebrow="Keuangan"
                        title={payment.number}
                        description={`${payment.student} — ${payment.amount}`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {payment.receiptUrl && (
                                    <a className="db-btn light" href={payment.receiptUrl} target="_blank" rel="noreferrer">
                                        <FileDown size={15} /> Kwitansi
                                    </a>
                                )}
                                {can.verify && payment.isPending && (
                                    <>
                                        <button type="button" className="db-btn light" disabled={processing} onClick={() => setConfirm('verify')}>
                                            <BadgeCheck size={15} /> Verifikasi
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
                            <h2 className="db-card-title">Ringkasan</h2>
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

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Bukti Pembayaran</h2>
                            {payment.proofUrl && (
                                <a className="db-btn ghost sm" href={payment.proofUrl} target="_blank" rel="noreferrer">
                                    Buka di tab baru
                                </a>
                            )}
                        </div>
                        <div className="db-card-body">
                            {!payment.proofUrl ? (
                                <p className="db-hint">Tidak ada berkas bukti yang diunggah.</p>
                            ) : payment.proofIsImage ? (
                                <img src={payment.proofUrl} alt={`Bukti ${payment.number}`} style={{ maxWidth: '100%', borderRadius: 12, border: '1px solid var(--db-line)' }} />
                            ) : payment.proofIsPdf ? (
                                <iframe src={payment.proofUrl} title={`Bukti ${payment.number}`} style={{ width: '100%', minHeight: 520, borderRadius: 12, border: '1px solid var(--db-line)' }} />
                            ) : (
                                <p className="db-hint">Format berkas tidak bisa dipratinjau. <a href={payment.proofUrl} target="_blank" rel="noreferrer">Unduh berkas</a>.</p>
                            )}
                        </div>
                    </section>

                    {can.verify && payment.isPending && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Catatan Verifikasi</h2>
                            </div>
                            <div className="db-card-body">
                                <TextareaField
                                    label="Catatan (opsional, dipakai saat verifikasi / penolakan)"
                                    value={form.data.verification_notes}
                                    onChange={(e) => form.setData('verification_notes', e.target.value)}
                                    error={errors.verification_notes}
                                />
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={confirm === 'verify'}
                    title="Verifikasi pembayaran?"
                    message={`Pembayaran ${payment.amount} dinyatakan sah dan mengurangi tunggakan invoice.`}
                    confirmLabel="Ya, verifikasi"
                    danger={false}
                    processing={processing}
                    onConfirm={submitDecision}
                    onCancel={() => setConfirm(null)}
                />
                <ConfirmModal
                    open={confirm === 'reject'}
                    title="Tolak pembayaran?"
                    message="Bukti dinyatakan tidak sah. Mahasiswa perlu mengunggah ulang."
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
