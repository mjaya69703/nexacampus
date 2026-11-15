// Form tambah/ubah aset kampus.
import { Head, useForm } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    asset: {
        id?: number; asset_code: string; name: string; category_id: number | null; room_id: number | null;
        brand: string; model_number: string; serial_number: string; condition: string;
        purchase_date: string; purchase_cost: string; is_borrowable: boolean;
        status: string; notes: string; funding_source: string; funding_detail: string;
    };
    categoryOptions: { id: number; name: string }[];
    conditions: Record<string, string>;
    statuses: Record<string, string>;
    fundings: Record<string, string>;
    roomOptions: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function AssetForm({ shell, mode, asset, categoryOptions, conditions, statuses, fundings, roomOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        asset_code: asset.asset_code,
        name: asset.name,
        category_id: asset.category_id as number | null,
        room_id: asset.room_id as number | null,
        brand: asset.brand,
        model_number: asset.model_number,
        serial_number: asset.serial_number,
        condition: asset.condition,
        purchase_date: asset.purchase_date,
        purchase_cost: asset.purchase_cost,
        funding_source: asset.funding_source,
        funding_detail: asset.funding_detail,
        is_borrowable: asset.is_borrowable,
        status: asset.status,
        notes: asset.notes,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Aset · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Boxes}
                        eyebrow="Kampus"
                        title={isCreate ? 'Tambah Aset' : `Edit ${asset.name}`}
                        description="Kode aset terisi otomatis dan unik. Penempatan ruangan hanya yang aktif."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Boxes size={16} /> Form Aset</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Kode aset"
                                        required
                                        hint="Unik. Terisi otomatis, bisa diubah."
                                        value={form.data.asset_code}
                                        onChange={(e) => form.setData('asset_code', e.target.value)}
                                        error={form.errors.asset_code}
                                    />
                                    <TextField
                                        label="Nama barang / aset"
                                        required
                                        placeholder="Contoh: Proyektor Epson EB-X500"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <SelectField
                                        label="Kategori aset"
                                        value={form.data.category_id === null ? '' : String(form.data.category_id)}
                                        onChange={(e) => form.setData('category_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.category_id}
                                        hint="Hanya kategori aktif. Kosong = tanpa kategori."
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
                                        hint="Kosong = gudang / belum ditempatkan."
                                    >
                                        <option value="">Belum ditempatkan / Gudang</option>
                                        {roomOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Merk / brand"
                                        placeholder="Epson, Sony, dll."
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
                                        label="Serial number (S/N)"
                                        value={form.data.serial_number}
                                        onChange={(e) => form.setData('serial_number', e.target.value)}
                                        error={form.errors.serial_number}
                                    />
                                    <SelectField
                                        label="Kondisi barang"
                                        required
                                        value={form.data.condition}
                                        onChange={(e) => form.setData('condition', e.target.value)}
                                        error={form.errors.condition}
                                    >
                                        {Object.entries(conditions).map(([id, name]) => (
                                            <option key={id} value={id}>{name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Status aset"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={form.errors.status}
                                    >
                                        {Object.entries(statuses).map(([id, name]) => (
                                            <option key={id} value={id}>{name}</option>
                                        ))}
                                    </SelectField>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-purchase-date">Tanggal pengadaan</label>
                                        <input
                                            id="crud-purchase-date"
                                            type="date"
                                            className="db-input"
                                            value={form.data.purchase_date}
                                            onChange={(e) => form.setData('purchase_date', e.target.value)}
                                        />
                                        {form.errors.purchase_date && <div className="db-error">{form.errors.purchase_date}</div>}
                                    </div>
                                    <TextField
                                        label="Biaya pengadaan (Rp)"
                                        type="number"
                                        min={0}
                                        placeholder="Contoh: 8500000"
                                        value={form.data.purchase_cost}
                                        onChange={(e) => form.setData('purchase_cost', e.target.value)}
                                        error={form.errors.purchase_cost}
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
                                    submitLabel={isCreate ? 'Simpan Aset' : 'Simpan Perubahan'}
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
