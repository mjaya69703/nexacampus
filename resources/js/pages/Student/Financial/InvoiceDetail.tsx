// Detail tagihan mahasiswa: rincian, pembayaran, dan pengajuan cicilan
// (Inertia React). Simulasi cicilan dihitung sekali di server.
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft, ArrowRight, BadgeCheck, CalendarClock, CircleDollarSign,
    CreditCard, Download, FileText, Info, Landmark, Paperclip, ReceiptText,
    Send, TriangleAlert, Upload, Wallet,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Invoice = {
    id: number;
    number: string;
    type: string;
    typeLabel: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    academicYear: string;
    semester: string | number | null;
    total: number;
    paid: number;
    outstanding: number;
    dueDate: string;
    isOverdue: boolean;
    issuedAt: string;
    notes: string | null;
    paidPercent: number;
    canPay: boolean;
    canRequestInstallment: boolean;
};

type Item = { id: number; description: string; type: string; typeLabel: string; amount: number };
type Adjustment = { id: number; label: string; reason: string; amount: number; date: string | null; by: string | null };

type Installment = {
    id: number;
    no: number;
    total: number;
    paid: number;
    outstanding: number;
    dueDate: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    paidPercent: number;
};

type Payment = {
    id: number;
    number: string;
    amount: number;
    method: string;
    methodLabel: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    date: string;
    verifiedBy: string | null;
    receiptUrl: string | null;
};

type InstallmentRequest = {
    id: number;
    tenor: number;
    status: string;
    statusLabel: string;
    statusTone: string;
    feeAmount: number;
    totalAmount: number;
    reason: string | null;
    financeNotes: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
};

type Simulation = {
    tenor: number;
    base_amount: number;
    fee_amount: number;
    total_amount: number;
    installments: Array<{
        installment_no: number;
        amount: number;
        fee_amount: number;
        total_amount: number;
        due_date: string;
    }>;
};

type Props = {
    shell: ShellProps;
    invoice: Invoice;
    items: Item[];
    adjustments: Adjustment[];
    installments: Installment[];
    payments: Payment[];
    installmentRequests: InstallmentRequest[];
    defaultPaymentAmount: number;
    simulations: Simulation[];
    paymentMethods: Array<{ value: string; label: string }>;
    urls: { index: string; pay: string; installment: string };
};

const money = (value: number): string => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        case 'blue':
            return '';
        default:
            return 'gray';
    }
};

const dateLabel = (value: string): string => {
    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) return value;

    return parsed.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

export default function InvoiceDetail({
    shell, invoice, items, adjustments, installments, payments, installmentRequests,
    defaultPaymentAmount, simulations, paymentMethods, urls,
}: Props) {
    const proofInput = useRef<HTMLInputElement>(null);
    const [tenor, setTenor] = useState<number | null>(simulations[0]?.tenor ?? null);

    const payment = useForm<{
        amount: string;
        method: string;
        reference: string;
        notes: string;
        proof: File | null;
    }>({
        amount: String(Math.round(defaultPaymentAmount)),
        method: paymentMethods[0]?.value ?? 'bank_transfer',
        reference: '',
        notes: '',
        proof: null,
    });

    const installmentForm = useForm<{ tenor: string; reason: string }>({
        tenor: '',
        reason: '',
    });

    const selectedSimulation = simulations.find((simulation) => simulation.tenor === tenor) ?? null;

    const submitPayment = (event: FormEvent): void => {
        event.preventDefault();

        payment.post(urls.pay, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                payment.reset();
                if (proofInput.current) proofInput.current.value = '';
            },
        });
    };

    const submitInstallment = (event: FormEvent): void => {
        event.preventDefault();

        if (!tenor) {
            installmentForm.setError('tenor', 'Pilih tenor cicilan terlebih dahulu.');
            return;
        }

        installmentForm.transform((data) => ({ ...data, tenor: String(tenor) }));
        installmentForm.post(urls.installment, {
            preserveScroll: true,
            onFinish: () => installmentForm.transform((data) => data),
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Invoice ${invoice.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ReceiptText}
                        eyebrow="Financial"
                        title={invoice.number}
                        description={`${invoice.typeLabel} · ${invoice.academicYear}${invoice.semester ? ` · Smt ${invoice.semester}` : ''}`}
                        badges={[invoice.statusLabel, invoice.isOverdue ? 'Lewat jatuh tempo' : `Jatuh tempo ${invoice.dueDate}`]}
                        actions={(
                            <Link href={urls.index} className="db-btn ghost"><ArrowLeft size={15} /> Tagihan Saya</Link>
                        )}
                    />

                    {invoice.isOverdue && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Invoice sudah melewati jatuh tempo ({invoice.dueDate}).</b> Segera kirim bukti pembayaran agar layanan akademik tidak terblokir.</span>
                        </div>
                    )}

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Wallet size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 20 }}>{money(invoice.total)}</span>
                                <span className="db-stat-label">Total tagihan</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BadgeCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 20 }}>{money(invoice.paid)}</span>
                                <span className="db-stat-label">Sudah dibayar</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><CircleDollarSign size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 20 }}>{money(invoice.outstanding)}</span>
                                <span className="db-stat-label">Sisa tagihan</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 20 }}>{invoice.dueDate}</span>
                                <span className="db-stat-label">Jatuh tempo</span>
                            </div>
                        </div>
                    </section>

                    <div className="db-progress"><i style={{ width: `${Math.min(100, Math.max(0, invoice.paidPercent))}%` }} /></div>
                    <div className="db-hint">{invoice.paidPercent}% dari total tagihan sudah terbayar · diterbitkan {invoice.issuedAt}</div>

                    {invoice.notes && (
                        <div className="db-note info"><Info size={15} /><span>{invoice.notes}</span></div>
                    )}

                    <div className="db-grid-2" style={{ alignItems: 'start' }}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileText size={16} /> Rincian Tagihan</h2>
                                    <span className="db-badge gray">{items.length} item</span>
                                </div>
                                <div className="db-card-body">
                                    {items.length === 0 ? (
                                        <div className="db-empty-calm">
                                            <FileText size={20} />
                                            <div><b>Belum ada rincian</b><small>Invoice ini belum memiliki item tagihan.</small></div>
                                        </div>
                                    ) : (
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead>
                                                    <tr><th>Deskripsi</th><th>Jenis</th><th style={{ textAlign: 'right' }}>Nominal</th></tr>
                                                </thead>
                                                <tbody>
                                                    {items.map((item) => (
                                                        <tr key={item.id}>
                                                            <td>{item.description}</td>
                                                            <td className="db-hint">{item.typeLabel}</td>
                                                            <td style={{ textAlign: 'right', fontWeight: 600 }}>{money(item.amount)}</td>
                                                        </tr>
                                                    ))}
                                                    <tr>
                                                        <td colSpan={2} style={{ fontWeight: 700 }}>Total</td>
                                                        <td style={{ textAlign: 'right', fontWeight: 700 }}>{money(invoice.total)}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    {adjustments.length > 0 && (
                                        <div style={{ marginTop: 14 }}>
                                            <div className="crud-label" style={{ marginBottom: 6 }}>Penyesuaian</div>
                                            <div style={{ display: 'grid', gap: 6 }}>
                                                {adjustments.map((adjustment) => (
                                                    <div key={adjustment.id} className="db-summary" style={{ padding: '8px 0' }}>
                                                        <span className="db-summary-icon gold"><CircleDollarSign size={16} /></span>
                                                        <div>
                                                            <b>{adjustment.label} · {money(adjustment.amount)}</b>
                                                            <small>{adjustment.reason}{adjustment.by ? ` · ${adjustment.by}` : ''}</small>
                                                        </div>
                                                        <span className="db-hint">{adjustment.date ?? '-'}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {installments.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><CalendarClock size={16} /> Jadwal Cicilan</h2>
                                        <span className="db-badge gray">{installments.length} angsuran</span>
                                    </div>
                                    <div className="db-card-body">
                                        <div className="db-table-wrap">
                                            <table className="db-table">
                                                <thead>
                                                    <tr><th>Ke</th><th>Jatuh tempo</th><th>Status</th><th style={{ textAlign: 'right' }}>Tagihan</th><th style={{ textAlign: 'right' }}>Sisa</th></tr>
                                                </thead>
                                                <tbody>
                                                    {installments.map((row) => (
                                                        <tr key={row.id}>
                                                            <td style={{ fontWeight: 700 }}>{row.no}</td>
                                                            <td>{dateLabel(row.dueDate)}</td>
                                                            <td><span className={`db-badge ${toneClass(row.statusTone)}`}>{row.statusLabel}</span></td>
                                                            <td style={{ textAlign: 'right' }}>{money(row.total)}</td>
                                                            <td style={{ textAlign: 'right', fontWeight: 600 }}>{money(row.outstanding)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><CreditCard size={16} /> Riwayat Pembayaran</h2>
                                    <span className="db-badge gray">{payments.length} pembayaran</span>
                                </div>
                                <div className="db-card-body">
                                    {payments.length === 0 ? (
                                        <EmptyState
                                            icon={CreditCard}
                                            title="Belum ada pembayaran"
                                            description="Kirim bukti pembayaran lewat form di samping untuk mulai melunasi tagihan."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 0 }}>
                                            {payments.map((row) => (
                                                <div key={row.id} className="db-inbox-row">
                                                    <span className="db-inbox-icon"><CreditCard size={18} /></span>
                                                    <div className="db-inbox-main">
                                                        <b>{row.number} · {money(row.amount)}</b>
                                                        <small className="db-hint">{row.methodLabel} · {row.date}{row.verifiedBy ? ` · diverifikasi ${row.verifiedBy}` : ''}</small>
                                                    </div>
                                                    <span className={`db-badge ${toneClass(row.statusTone)}`}>{row.statusLabel}</span>
                                                    {row.receiptUrl && (
                                                        <a href={row.receiptUrl} className="db-btn ghost sm"><Download size={13} /> Kwitansi</a>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>

                            {installmentRequests.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Landmark size={16} /> Pengajuan Cicilan</h2>
                                        <span className="db-badge gray">{installmentRequests.length} pengajuan</span>
                                    </div>
                                    <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                        {installmentRequests.map((request) => (
                                            <div key={request.id} className="db-class">
                                                <div className="db-class-top">
                                                    <div>
                                                        <b>{request.tenor} kali angsuran</b>
                                                        <small>Total simulasi {money(request.totalAmount)} · biaya {money(request.feeAmount)}</small>
                                                    </div>
                                                    <span className={`db-badge ${toneClass(request.statusTone)}`}>{request.statusLabel}</span>
                                                </div>
                                                {request.reason && <div className="db-hint" style={{ marginTop: 6 }}>Alasan: {request.reason}</div>}
                                                {request.financeNotes && <div className="db-note info" style={{ marginTop: 8 }}><Info size={14} /><span>{request.financeNotes}</span></div>}
                                                <div className="db-hint" style={{ marginTop: 6 }}>
                                                    Diajukan {request.submittedAt ?? '-'}{request.reviewedAt ? ` · direview ${request.reviewedAt}` : ''}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Upload size={16} /> Kirim Bukti Pembayaran</h2>
                                </div>
                                <div className="db-card-body">
                                    {!invoice.canPay ? (
                                        <div className="db-note muted">
                                            <Info size={15} />
                                            <span>Invoice ini sudah lunas atau dibatalkan, sehingga tidak menerima pembayaran baru.</span>
                                        </div>
                                    ) : (
                                        <form onSubmit={submitPayment} style={{ display: 'grid', gap: 10 }}>
                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Nominal</span>
                                                <input
                                                    type="number"
                                                    className="db-input"
                                                    min={1}
                                                    step="1"
                                                    value={payment.data.amount}
                                                    onChange={(event) => payment.setData('amount', event.target.value)}
                                                />
                                                {payment.errors.amount && <span className="db-error">{payment.errors.amount}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Metode</span>
                                                <select
                                                    className="db-input"
                                                    value={payment.data.method}
                                                    onChange={(event) => payment.setData('method', event.target.value)}
                                                >
                                                    {paymentMethods.map((method) => (
                                                        <option key={method.value} value={method.value}>{method.label}</option>
                                                    ))}
                                                </select>
                                                {payment.errors.method && <span className="db-error">{payment.errors.method}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Referensi transaksi (opsional)</span>
                                                <input
                                                    type="text"
                                                    className="db-input"
                                                    placeholder="No. referensi / berita transfer"
                                                    value={payment.data.reference}
                                                    onChange={(event) => payment.setData('reference', event.target.value)}
                                                />
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Bukti pembayaran</span>
                                                <input
                                                    ref={proofInput}
                                                    type="file"
                                                    className="db-input"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                    onChange={(event) => payment.setData('proof', event.target.files?.[0] ?? null)}
                                                />
                                                <span className="db-hint"><Paperclip size={11} /> PDF/JPG/PNG/WebP maksimal 4 MB.</span>
                                                {payment.errors.proof && <span className="db-error">{payment.errors.proof}</span>}
                                            </label>

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Catatan (opsional)</span>
                                                <textarea
                                                    className="db-input"
                                                    rows={2}
                                                    value={payment.data.notes}
                                                    onChange={(event) => payment.setData('notes', event.target.value)}
                                                />
                                            </label>

                                            <button type="submit" className="db-btn primary" disabled={payment.processing}>
                                                <Send size={15} /> {payment.processing ? 'Mengirim…' : 'Kirim Pembayaran'}
                                            </button>
                                        </form>
                                    )}
                                </div>
                            </section>

                            {invoice.canRequestInstallment && simulations.length > 0 && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><Landmark size={16} /> Ajukan Cicilan</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <form onSubmit={submitInstallment} style={{ display: 'grid', gap: 10 }}>
                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Tenor</span>
                                                <select
                                                    className="db-input"
                                                    value={tenor ?? ''}
                                                    onChange={(event) => setTenor(Number(event.target.value))}
                                                >
                                                    {simulations.map((simulation) => (
                                                        <option key={simulation.tenor} value={simulation.tenor}>
                                                            {simulation.tenor}x · {money(simulation.installments[0]?.total_amount ?? 0)}/bulan
                                                        </option>
                                                    ))}
                                                </select>
                                                {installmentForm.errors.tenor && <span className="db-error">{installmentForm.errors.tenor}</span>}
                                            </label>

                                            {selectedSimulation && (
                                                <div className="db-note info">
                                                    <CircleDollarSign size={15} />
                                                    <span>
                                                        Total <b>{money(selectedSimulation.total_amount)}</b> untuk {selectedSimulation.tenor} angsuran
                                                        (pokok {money(selectedSimulation.base_amount)}, biaya {money(selectedSimulation.fee_amount)}).
                                                        Angsuran pertama jatuh tempo {dateLabel(selectedSimulation.installments[0]?.due_date ?? '')}.
                                                    </span>
                                                </div>
                                            )}

                                            <label style={{ display: 'grid', gap: 4 }}>
                                                <span className="db-hint" style={{ fontWeight: 700 }}>Alasan pengajuan (opsional)</span>
                                                <textarea
                                                    className="db-input"
                                                    rows={2}
                                                    value={installmentForm.data.reason}
                                                    onChange={(event) => installmentForm.setData('reason', event.target.value)}
                                                />
                                            </label>

                                            <button type="submit" className="db-btn primary" disabled={installmentForm.processing}>
                                                <ArrowRight size={15} /> {installmentForm.processing ? 'Mengirim…' : 'Ajukan Cicilan'}
                                            </button>

                                            <span className="db-hint">
                                                Pengajuan akan direview finance. Setelah disetujui, jadwal angsuran otomatis dibuat.
                                            </span>
                                        </form>
                                    </div>
                                </section>
                            )}

                            <div className="db-note muted">
                                <Info size={15} />
                                <span>
                                    Pembayaran diverifikasi manual oleh bagian keuangan. Simpan bukti transfer sampai status
                                    pembayaran berubah menjadi terverifikasi.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
