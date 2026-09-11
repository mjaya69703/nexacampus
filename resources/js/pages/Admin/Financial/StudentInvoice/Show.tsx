// Detail tagihan — ringkasan, item, penyesuaian, beasiswa, pembayaran, cicilan.
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FileDown, FileText, Megaphone, Pencil } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Item = { id: number; type: string; description: string; amount: string; rawAmount: number };
type Adjustment = { id: number; type: string; amount: string; reason: string | null; by: string | null; at: string | null };
type PayRow = { id: number; number: string; amount: string; method: string; status: string; at: string | null; verifiedBy: string | null };
type Installment = { id: number; amount: string; dueDate: string | null; status: string };
type InstallmentRequest = { id: number; status: string; reviewedBy: string | null; at: string | null };

type Invoice = {
    id: number; number: string; type: string; typeText: string;
    student: string; nim: string | null; program: string | null;
    year: string | null; semester: number | null;
    status: string; statusTone: string; statusText: string;
    total: string; paid: string; outstanding: string;
    dueDate: string | null; issuedAt: string | null; issuedBy: string | null;
    cancelledAt: string | null; cancelledBy: string | null;
    notes: string | null; isEditable: boolean;
    items: Item[]; adjustments: Adjustment[]; payments: PayRow[];
    installments: Installment[]; installmentRequests: InstallmentRequest[];
};

type Props = {
    shell: ShellProps;
    invoice: Invoice;
    adjustmentTypes: Record<string, string>;
    scholarshipOptions: { id: number; label: string }[];
    can: { update: boolean; cancel: boolean };
    urls: {
        index: string; edit: string; issue: string; cancel: string; pdf: string;
        adjust: string; applyScholarship: string;
    };
};

export default function StudentInvoiceShow({ shell, invoice, adjustmentTypes, scholarshipOptions, can, urls }: Props) {
    const [confirmIssue, setConfirmIssue] = useState(false);
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [processing, setProcessing] = useState(false);

    const adjustForm = useForm({ adjustment_type: 'discount', amount: '', reason: '' });
    const scholarshipForm = useForm({ scholarship_assignment_id: '' });

    const post = (url: string, done?: () => void) => {
        setProcessing(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => { setProcessing(false); done?.(); } });
    };

    const submitAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        adjustForm.post(urls.adjust, { preserveScroll: true });
    };

    const submitScholarship = (e: React.FormEvent) => {
        e.preventDefault();
        scholarshipForm.post(urls.applyScholarship, { preserveScroll: true });
    };

    const adjustErrors = adjustForm.errors as Record<string, string>;
    const scholarshipErrors = scholarshipForm.errors as Record<string, string>;

    const summary: [string, React.ReactNode][] = [
        ['Mahasiswa', <b key="s">{invoice.student} {invoice.nim ? `(${invoice.nim})` : ''}</b>],
        ['Prodi', invoice.program ?? '-'],
        ['Tahun / Semester', `${invoice.year ?? '-'}${invoice.semester ? ` · Smt ${invoice.semester}` : ''}`],
        ['Jenis', invoice.typeText],
        ['Status', <span key="st" className={`db-badge ${invoice.statusTone}`}>{invoice.statusText}</span>],
        ['Total', <b key="t">{invoice.total}</b>],
        ['Terbayar', invoice.paid],
        ['Sisa', <b key="o">{invoice.outstanding}</b>],
        ['Jatuh Tempo', invoice.dueDate ?? '-'],
        ['Diterbitkan', invoice.issuedAt ? `${invoice.issuedAt}${invoice.issuedBy ? ` oleh ${invoice.issuedBy}` : ''}` : '-'],
        ['Dibatalkan', invoice.cancelledAt ? `${invoice.cancelledAt}${invoice.cancelledBy ? ` oleh ${invoice.cancelledBy}` : ''}` : '-'],
        ['Catatan', invoice.notes ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Tagihan ${invoice.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Keuangan"
                        title={invoice.number}
                        description={`${invoice.student} — ${invoice.typeText}`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                <a className="db-btn light" href={urls.pdf} target="_blank" rel="noreferrer"><FileDown size={15} /> PDF</a>
                                {can.update && invoice.isEditable && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>}
                                {can.update && invoice.status === 'draft' && (
                                    <button type="button" className="db-btn light" disabled={processing} onClick={() => setConfirmIssue(true)}>
                                        <Megaphone size={15} /> Terbitkan
                                    </button>
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
                            <h2 className="db-card-title">Rincian Biaya ({invoice.items.length})</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="db-table-wrap">
                                <table className="db-table">
                                    <thead>
                                        <tr><th>Deskripsi</th><th>Jenis</th><th style={{ textAlign: 'right' }}>Nominal</th></tr>
                                    </thead>
                                    <tbody>
                                        {invoice.items.map((item) => (
                                            <tr key={item.id}>
                                                <td>{item.description}</td>
                                                <td><small className="db-hint">{item.type}</small></td>
                                                <td style={{ textAlign: 'right' }}><b>{item.amount}</b></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {can.update && invoice.status !== 'draft' && invoice.status !== 'cancelled' && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Penyesuaian & Beasiswa</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <div>
                                        <form onSubmit={submitAdjust}>
                                            <div className="crud-grid crud-grid-1">
                                                <SelectField
                                                    label="Jenis Penyesuaian"
                                                    value={adjustForm.data.adjustment_type}
                                                    onChange={(e) => adjustForm.setData('adjustment_type', e.target.value)}
                                                    error={adjustErrors.adjustment_type}
                                                >
                                                    {Object.entries(adjustmentTypes).map(([value, label]) => (
                                                        <option key={value} value={value}>{label}</option>
                                                    ))}
                                                </SelectField>
                                                <TextField
                                                    label="Nominal"
                                                    required
                                                    type="number"
                                                    min={1}
                                                    value={adjustForm.data.amount}
                                                    onChange={(e) => adjustForm.setData('amount', e.target.value)}
                                                    error={adjustErrors.amount}
                                                />
                                                <TextField
                                                    label="Alasan"
                                                    value={adjustForm.data.reason}
                                                    onChange={(e) => adjustForm.setData('reason', e.target.value)}
                                                    error={adjustErrors.reason}
                                                />
                                            </div>
                                            <div style={{ marginTop: 10 }}>
                                                <button type="submit" className="db-btn primary sm" disabled={adjustForm.processing}>
                                                    Terapkan Penyesuaian
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    <div>
                                        <form onSubmit={submitScholarship}>
                                            <SelectField
                                                label="Alokasi Beasiswa Aktif"
                                                value={scholarshipForm.data.scholarship_assignment_id}
                                                onChange={(e) => scholarshipForm.setData('scholarship_assignment_id', e.target.value)}
                                                error={scholarshipErrors.scholarship_assignment_id}
                                                hint={scholarshipOptions.length === 0 ? 'Tidak ada alokasi beasiswa aktif untuk mahasiswa ini.' : undefined}
                                            >
                                                <option value="">Pilih alokasi…</option>
                                                {scholarshipOptions.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
                                            </SelectField>
                                            <div style={{ marginTop: 10 }}>
                                                <button
                                                    type="submit"
                                                    className="db-btn primary sm"
                                                    disabled={scholarshipForm.processing || !scholarshipForm.data.scholarship_assignment_id}
                                                >
                                                    Terapkan Beasiswa
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {invoice.adjustments.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Riwayat Penyesuaian ({invoice.adjustments.length})</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Jenis</th><th style={{ textAlign: 'right' }}>Nominal</th><th>Alasan</th><th>Oleh</th></tr>
                                        </thead>
                                        <tbody>
                                            {invoice.adjustments.map((adj) => (
                                                <tr key={adj.id}>
                                                    <td><b>{adj.type}</b> <small className="db-hint">{adj.at ?? ''}</small></td>
                                                    <td style={{ textAlign: 'right' }}><b>{adj.amount}</b></td>
                                                    <td><small className="db-hint">{adj.reason ?? '-'}</small></td>
                                                    <td><small className="db-hint">{adj.by ?? '-'}</small></td>
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
                            <h2 className="db-card-title">Riwayat Pembayaran ({invoice.payments.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {invoice.payments.length === 0 ? (
                                <p className="db-hint">Belum ada pembayaran untuk tagihan ini.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Nomor</th><th style={{ textAlign: 'right' }}>Nominal</th><th>Metode</th><th>Status</th><th>Waktu</th></tr>
                                        </thead>
                                        <tbody>
                                            {invoice.payments.map((pay) => (
                                                <tr key={pay.id}>
                                                    <td><b>{pay.number}</b></td>
                                                    <td style={{ textAlign: 'right' }}><b>{pay.amount}</b></td>
                                                    <td><small className="db-hint">{pay.method}</small></td>
                                                    <td><span className="db-badge">{pay.status}</span></td>
                                                    <td><small className="db-hint">{pay.at ?? '-'}{pay.verifiedBy ? ` · ${pay.verifiedBy}` : ''}</small></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    {(invoice.installments.length > 0 || invoice.installmentRequests.length > 0) && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Cicilan</h2>
                            </div>
                            <div className="db-card-body tight">
                                {invoice.installments.map((inst) => (
                                    <div className="db-summary" key={`i-${inst.id}`}>
                                        <span className="db-hint">Termin · jatuh {inst.dueDate ?? '-'}</span>
                                        <span><b>{inst.amount}</b> <span className="db-badge" style={{ marginLeft: 8 }}>{inst.status}</span></span>
                                    </div>
                                ))}
                                {invoice.installmentRequests.map((req) => (
                                    <div className="db-summary" key={`r-${req.id}`}>
                                        <span className="db-hint">Pengajuan #{req.id} · {req.at ?? '-'}</span>
                                        <span><span className="db-badge">{req.status}</span>{req.reviewedBy ? <small className="db-hint"> · {req.reviewedBy}</small> : null}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={confirmIssue}
                    title="Terbitkan tagihan?"
                    message="Tagihan draft diterbitkan resmi ke mahasiswa dan notifikasi dikirim."
                    confirmLabel="Ya, terbitkan"
                    danger={false}
                    processing={processing}
                    onConfirm={() => post(urls.issue, () => setConfirmIssue(false))}
                    onCancel={() => setConfirmIssue(false)}
                />
                <ConfirmModal
                    open={confirmCancel}
                    title="Batalkan tagihan?"
                    message="Tagihan dibatalkan dan tidak dihitung sebagai tunggakan mahasiswa."
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={() => post(urls.cancel, () => setConfirmCancel(false))}
                    onCancel={() => setConfirmCancel(false)}
                />
            </div>
        </AdminShell>
    );
}
