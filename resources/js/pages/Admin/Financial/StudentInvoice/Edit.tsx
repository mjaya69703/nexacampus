// Form ubah tagihan — hanya bila isEditable (paritas Blade).
import { Head, useForm } from '@inertiajs/react';
import { FileText, Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Item = { item_type: string; description: string; amount: string };

type Props = {
    shell: ShellProps;
    invoice: { id: number; number: string; student: string | null; nim: string | null };
    form: { academic_year_id: string; semester: string; invoice_type: string; due_date: string; notes: string | null };
    items: Item[];
    years: { id: number; name: string }[];
    invoiceTypes: { id: string; name: string }[];
    urls: { index: string; show: string; submit: string };
};

export default function StudentInvoiceEdit({ shell, invoice, form: initial, items: initialItems, years, invoiceTypes, urls }: Props) {
    const form = useForm({ ...initial, notes: initial.notes ?? '' });
    const [items, setItems] = useState<Item[]>(initialItems.length > 0 ? initialItems : [{ item_type: 'fee', description: '', amount: '' }]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, items }));
        form.put(urls.submit);
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`Ubah ${invoice.number} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Keuangan"
                        title={`Ubah ${invoice.number}`}
                        description={`${invoice.nim ?? '-'} — ${invoice.student ?? '-'}`}
                        actions={<a className="db-btn light" href={urls.show}>Kembali ke detail</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Data Tagihan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Tahun Akademik"
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={errors.academic_year_id}
                                    >
                                        <option value="">Tanpa tahun…</option>
                                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester"
                                        type="number"
                                        min={1}
                                        max={14}
                                        value={form.data.semester}
                                        onChange={(e) => form.setData('semester', e.target.value)}
                                        error={errors.semester}
                                    />
                                    <SelectField
                                        label="Kategori Invoice"
                                        required
                                        value={form.data.invoice_type}
                                        onChange={(e) => form.setData('invoice_type', e.target.value)}
                                        error={errors.invoice_type}
                                    >
                                        {invoiceTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Jatuh Tempo"
                                        required
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) => form.setData('due_date', e.target.value)}
                                        error={errors.due_date}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 14 }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                                        <span className="crud-label" style={{ margin: 0 }}>Rincian Item Tagihan</span>
                                        <button type="button" className="db-btn ghost sm" onClick={() => setItems((prev) => [...prev, { item_type: 'fee', description: '', amount: '' }])}>
                                            <Plus size={13} /> Tambah Item
                                        </button>
                                    </div>
                                    {items.map((item, index) => (
                                        <div key={index} className="crud-grid" style={{ marginBottom: 8 }}>
                                            <select
                                                className="db-input"
                                                value={item.item_type}
                                                onChange={(e) => setItems((prev) => prev.map((it, i) => (i === index ? { ...it, item_type: e.target.value } : it)))}
                                                aria-label="Jenis item"
                                            >
                                                <option value="fee">Biaya Pokok (Fee)</option>
                                                <option value="discount">Potongan (Discount)</option>
                                                <option value="adjustment">Penyesuaian (Adjustment)</option>
                                                <option value="penalty">Denda (Penalty)</option>
                                            </select>
                                            <input
                                                className="db-input"
                                                value={item.description}
                                                placeholder="Keterangan item"
                                                onChange={(e) => setItems((prev) => prev.map((it, i) => (i === index ? { ...it, description: e.target.value } : it)))}
                                                aria-label="Keterangan item"
                                            />
                                            <input
                                                className="db-input"
                                                type="number"
                                                min={0.01}
                                                step={100}
                                                value={item.amount}
                                                placeholder="Nominal (Rp)"
                                                onChange={(e) => setItems((prev) => prev.map((it, i) => (i === index ? { ...it, amount: e.target.value } : it)))}
                                                aria-label="Nominal item"
                                            />
                                            <div>
                                                <button
                                                    type="button"
                                                    className="db-btn ghost sm"
                                                    disabled={items.length <= 1}
                                                    onClick={() => setItems((prev) => prev.filter((_, i) => i !== index))}
                                                    aria-label="Hapus item"
                                                >
                                                    <Minus size={13} />
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                    {errors.items && <div className="db-error">{errors.items}</div>}
                                </div>

                                <FormActions
                                    cancelHref={urls.show}
                                    submitLabel="Simpan Perubahan"
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
