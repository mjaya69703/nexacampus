// Form terbit tagihan — SPP otomatis / kustom × tunggal/terpilih/massal.
import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Minus, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Item = { item_type: string; description: string; amount: string };
type StudentOption = { id: number; label: string };

type Props = {
    shell: ShellProps;
    form: {
        invoice_kind: string; generation_mode: string; student_profile_id: string;
        academic_year_id: string; semester: string; due_date: string;
        invoice_type: string; notes: string; issue_immediately: boolean;
    };
    items: Item[];
    selectedStudents: StudentOption[];
    years: { id: number; name: string }[];
    invoiceTypes: { id: string; name: string }[];
    urls: { index: string; submit: string; searchStudents: string };
};

const KIND_TEXT: Record<string, string> = { tuition: 'SPP Otomatis (tarif master)', custom: 'Tagihan Kustom (item manual)' };
const MODE_TEXT: Record<string, string> = {
    single: 'Satu Mahasiswa', selected_students: 'Mahasiswa Terpilih', active_students: 'Massal (sesuai filter)',
};
const ITEM_TEXT: Record<string, string> = { fee: 'Biaya Pokok (Fee)', discount: 'Potongan (Discount)', adjustment: 'Penyesuaian (Adjustment)', penalty: 'Denda (Penalty)' };

export default function StudentInvoiceForm({ shell, form: initial, items: initialItems, selectedStudents: initialSelected, years, invoiceTypes, urls }: Props) {
    const form = useForm({ ...initial });
    const [items, setItems] = useState<Item[]>(initialItems.length > 0 ? initialItems : [{ item_type: 'fee', description: '', amount: '' }]);
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<StudentOption[]>([]);
    const [searching, setSearching] = useState(false);
    const [picked, setPicked] = useState<StudentOption[]>(initialSelected);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(`${urls.searchStudents}?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                setOptions(json.options ?? []);
            } catch {
                setOptions([]);
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [query, urls.searchStudents]);

    const isTuition = form.data.invoice_kind === 'tuition';
    const isSingle = form.data.generation_mode === 'single';
    const isSelected = form.data.generation_mode === 'selected_students';

    const togglePicked = (option: StudentOption) => {
        setPicked((prev) => (prev.some((p) => p.id === option.id) ? prev.filter((p) => p.id !== option.id) : [...prev, option]));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload: Record<string, unknown> = { ...form.data };
        if (isSingle) {
            payload.student_profile_id = picked[0]?.id ?? '';
        } else {
            payload.student_profile_id = '';
        }
        if (isSelected) {
            payload.student_profile_ids = picked.map((p) => p.id);
        }
        if (!isTuition) {
            payload.items = items;
        }
        router.post(urls.submit, payload as never);
    };

    const errors = form.errors as Record<string, string>;
    const itemsTotal = items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);

    return (
        <AdminShell shell={shell}>
            <Head title={`Terbitkan Tagihan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Keuangan"
                        title="Terbitkan Tagihan"
                        description="SPP mengikuti tarif master; kustom memakai rincian item manual."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Formulir Penerbitan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Jenis Tagihan"
                                        required
                                        value={form.data.invoice_kind}
                                        onChange={(e) => {
                                            const kind = e.target.value;
                                            form.setData('invoice_kind', kind);
                                            form.setData('issue_immediately', kind === 'tuition');
                                            if (kind === 'tuition') {
                                                form.setData('invoice_type', 'tuition');
                                            } else if (form.data.invoice_type === 'tuition') {
                                                form.setData('invoice_type', 'custom');
                                            }
                                        }}
                                        error={errors.invoice_kind}
                                    >
                                        <option value="tuition">{KIND_TEXT.tuition}</option>
                                        <option value="custom">{KIND_TEXT.custom}</option>
                                    </SelectField>
                                    <SelectField
                                        label="Target Mahasiswa"
                                        required
                                        value={form.data.generation_mode}
                                        onChange={(e) => form.setData('generation_mode', e.target.value)}
                                        error={errors.generation_mode}
                                    >
                                        <option value="single">{MODE_TEXT.single}</option>
                                        <option value="selected_students">{MODE_TEXT.selected_students}</option>
                                        <option value="active_students">{MODE_TEXT.active_students}</option>
                                    </SelectField>
                                </div>

                                {(isSingle || isSelected) && (
                                    <div style={{ marginTop: 14 }}>
                                        <span className="crud-label">{isSingle ? 'Pilih Satu Mahasiswa' : 'Pilih Mahasiswa'} <span>*</span></span>
                                        <input
                                            className="db-input"
                                            style={{ marginBottom: 8 }}
                                            value={query}
                                            placeholder="Cari NIM, nama, atau email…"
                                            onChange={(e) => setQuery(e.target.value)}
                                            aria-label="Cari mahasiswa"
                                        />
                                        <div style={{ border: '1px solid var(--db-line)', borderRadius: 10, padding: 10, maxHeight: 220, overflowY: 'auto' }}>
                                            {searching && <span className="db-hint">Mencari…</span>}
                                            {!searching && options.length === 0 && <div className="db-hint" style={{ textAlign: 'center' }}>Mahasiswa tidak ditemukan.</div>}
                                            {options.map((option) => (
                                                <label key={option.id} style={{ display: 'block', padding: '4px 0', fontSize: 13 }}>
                                                    <input
                                                        type={isSingle ? 'radio' : 'checkbox'}
                                                        name="invoice-student"
                                                        checked={picked.some((p) => p.id === option.id)}
                                                        onChange={() => (isSingle ? setPicked([option]) : togglePicked(option))}
                                                        style={{ marginRight: 8 }}
                                                    />
                                                    {option.label}
                                                </label>
                                            ))}
                                        </div>
                                        <div className="db-hint" style={{ marginTop: 6 }}>{picked.length} mahasiswa terpilih.</div>
                                        {picked.length > 0 && (
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                                {picked.map((p) => (
                                                    <span key={p.id} className="db-badge green">
                                                        {p.label}
                                                        <button
                                                            type="button"
                                                            onClick={() => setPicked((prev) => prev.filter((x) => x.id !== p.id))}
                                                            style={{ background: 'none', border: 0, cursor: 'pointer', marginLeft: 4 }}
                                                            aria-label={`Hapus ${p.label}`}
                                                        >
                                                            <X size={11} />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        {(errors.student_profile_id || errors.student_profile_ids) && (
                                            <div className="db-error">{errors.student_profile_id ?? errors.student_profile_ids}</div>
                                        )}
                                    </div>
                                )}

                                <div className="crud-grid" style={{ marginTop: 14 }}>
                                    <SelectField
                                        label="Tahun Akademik"
                                        required={isTuition}
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={errors.academic_year_id}
                                        hint={isTuition ? undefined : 'Wajib untuk SPP, pendaftaran, dan ujian.'}
                                    >
                                        <option value="">Pilih tahun…</option>
                                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester"
                                        required={isTuition}
                                        type="number"
                                        min={1}
                                        max={14}
                                        value={form.data.semester}
                                        onChange={(e) => form.setData('semester', e.target.value)}
                                        error={errors.semester}
                                    />
                                    <TextField
                                        label="Jatuh Tempo"
                                        required={!isTuition}
                                        type="date"
                                        value={form.data.due_date}
                                        onChange={(e) => form.setData('due_date', e.target.value)}
                                        error={errors.due_date}
                                        hint={isTuition ? 'Kosongkan untuk memakai deadline tarif.' : undefined}
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
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label className="crud-label" htmlFor="inv-notes">Catatan</label>
                                        <textarea
                                            id="inv-notes"
                                            className="db-input"
                                            rows={2}
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                        />
                                        {errors.notes && <div className="db-error">{errors.notes}</div>}
                                    </div>
                                    <div>
                                        <span className="crud-label">Langsung Terbitkan (Issued)</span>
                                        <label style={{ display: 'inline-flex', alignItems: 'center', gap: 10, cursor: 'pointer' }}>
                                            <input
                                                type="checkbox"
                                                checked={form.data.issue_immediately}
                                                onChange={(e) => form.setData('issue_immediately', e.target.checked)}
                                                style={{ width: 18, height: 18, accentColor: 'var(--db-brand)' }}
                                            />
                                            <small className="db-hint">Mati = invoice jadi draf.</small>
                                        </label>
                                    </div>
                                </div>

                                {!isTuition && (
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
                                                    <option value="fee">{ITEM_TEXT.fee}</option>
                                                    <option value="discount">{ITEM_TEXT.discount}</option>
                                                    <option value="adjustment">{ITEM_TEXT.adjustment}</option>
                                                    <option value="penalty">{ITEM_TEXT.penalty}</option>
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
                                        <p className="db-hint">Estimasi total item: <b>Rp {itemsTotal.toLocaleString('id-ID')}</b></p>
                                    </div>
                                )}

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel="Terbitkan Tagihan"
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
