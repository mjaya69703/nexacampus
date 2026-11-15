// Form tambah banyak unit aset sekaligus (nomor inventaris urut otomatis).
import { Head, useForm } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    categoryOptions: { id: number; name: string }[];
    roomOptions: { id: number; name: string }[];
    fundings: Record<string, string>;
    urls: { index: string; submit: string };
};

export default function AssetBulkForm({ shell, categoryOptions, roomOptions, fundings, urls }: Props) {
    const form = useForm({
        name: '',
        category_id: null as number | null,
        room_id: null as number | null,
        brand: '',
        model_number: '',
        condition: 'good',
        purchase_date: '',
        purchase_cost: '',
        funding_source: '',
        funding_detail: '',
        is_borrowable: false,
        status: 'active',
        quantity: 2,
        notes: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Tambah Banyak Unit · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Boxes}
                        eyebrow="Kampus"
                        title="Tambah Banyak Unit"
                        description="Satu spesifikasi untuk banyak unit sejenis. Nomor inventaris terisi urut otomatis."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Boxes size={16} /> Form Banyak Unit</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Nama barang"
                                            required
                                            placeholder="Contoh: Proyektor Epson EB-X500"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                            error={form.errors.name}
                                        />
                                    </div>
                                    <SelectField
                                        label="Kategori aset"
                                        value={form.data.category_id === null ? '' : String(form.data.category_id)}
                                        onChange={(e) => form.setData('category_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.category_id}
                                    >
                                        <option value="">Tanpa kategori</option>
                                        {categoryOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Lokasi ruangan"
                                        value={form.data.room_id === null ? '' : String(form.data.room_id)}
                                        onChange={(e) => form.setData('room_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.room_id}
                                    >
                                        <option value="">Belum ditempatkan / Gudang</option>
                                        {roomOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Merk / brand"
                                        value={form.data.brand}
                                        onChange={(e) => form.setData('brand', e.target.value)}
                                        error={form.errors.brand}
                                    />
                                    <TextField
                                        label="Nomor model"
                                        value={form.data.model_number}
                                        onChange={(e) => form.setData('model_number', e.target.value)}
                                        error={form.errors.model_number}
                                    />
                                    <TextField
                                        label="Jumlah unit"
                                        required
                                        type="number"
                                        min={2}
                                        hint="2 sampai 100. Nomor inventaris urut otomatis."
                                        value={String(form.data.quantity)}
                                        onChange={(e) => form.setData('quantity', e.target.value === '' ? 2 : Number(e.target.value))}
                                        error={form.errors.quantity}
                                    />
                                    <SelectField
                                        label="Kondisi awal"
                                        required
                                        value={form.data.condition}
                                        onChange={(e) => form.setData('condition', e.target.value)}
                                        error={form.errors.condition}
                                    >
                                        <option value="good">Baik (Normal)</option>
                                        <option value="minor_damage">Rusak Ringan</option>
                                        <option value="major_damage">Rusak Berat</option>
                                        <option value="in_repair">Sedang Dalam Perbaikan</option>
                                    </SelectField>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-bulk-date">Tanggal pengadaan</label>
                                        <input
                                            id="crud-bulk-date"
                                            type="date"
                                            className="db-input"
                                            value={form.data.purchase_date}
                                            onChange={(e) => form.setData('purchase_date', e.target.value)}
                                        />
                                        {form.errors.purchase_date && <div className="db-error">{form.errors.purchase_date}</div>}
                                    </div>
                                    <TextField
                                        label="Biaya per unit (Rp)"
                                        type="number"
                                        min={0}
                                        value={form.data.purchase_cost}
                                        onChange={(e) => form.setData('purchase_cost', e.target.value)}
                                        error={form.errors.purchase_cost}
                                        hint="Total = biaya × jumlah."
                                    />
                                    <SelectField
                                        label="Sumber dana"
                                        value={form.data.funding_source}
                                        onChange={(e) => form.setData('funding_source', e.target.value)}
                                        error={form.errors.funding_source}
                                    >
                                        <option value="">Tanpa sumber dana</option>
                                        {Object.entries(fundings).map(([id, name]) => (
                                            <option key={id} value={id}>{name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Nama pemberi / program"
                                        placeholder="Contoh: Hibah Kemendikbud 2025"
                                        value={form.data.funding_detail}
                                        onChange={(e) => form.setData('funding_detail', e.target.value)}
                                        error={form.errors.funding_detail}
                                    />
                                    <SwitchField
                                        label="Dapat dipinjam terpisah"
                                        checked={form.data.is_borrowable}
                                        onChange={(v) => form.setData('is_borrowable', v)}
                                        error={form.errors.is_borrowable}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={`Buat ${form.data.quantity} Unit`}
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
