// Form tambah/ubah gedung.
import { Head, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    building: {
        id?: number; name: string; code: string; campus_location_id: number | null;
        address: string; floor_count: string; is_active: boolean; desc: string;
    };
    locationOptions: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function BuildingForm({ shell, mode, building, locationOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: building.name,
        code: building.code,
        campus_location_id: building.campus_location_id as number | null,
        address: building.address,
        floor_count: building.floor_count,
        is_active: building.is_active,
        desc: building.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Gedung · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Building2}
                        eyebrow="Kampus"
                        title={isCreate ? 'Tambah Gedung' : `Edit ${building.name}`}
                        description="Menonaktifkan gedung ikut menonaktifkan seluruh ruangannya."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Building2 size={16} /> Form Gedung</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama gedung"
                                        required
                                        placeholder="Contoh: Gedung Rektorat"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode gedung"
                                        placeholder="Contoh: BLD-RT"
                                        hint="Opsional, unik bila diisi."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <SelectField
                                        label="Lokasi kampus"
                                        value={form.data.campus_location_id === null ? '' : String(form.data.campus_location_id)}
                                        onChange={(e) => form.setData('campus_location_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.campus_location_id}
                                        hint="Hanya lokasi aktif."
                                    >
                                        <option value="">Tanpa lokasi</option>
                                        {locationOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Jumlah lantai"
                                        type="number"
                                        min={1}
                                        placeholder="Contoh: 5"
                                        value={form.data.floor_count}
                                        onChange={(e) => form.setData('floor_count', e.target.value)}
                                        error={form.errors.floor_count}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Alamat"
                                            placeholder="Alamat gedung"
                                            value={form.data.address}
                                            onChange={(e) => form.setData('address', e.target.value)}
                                            error={form.errors.address}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            placeholder="Deskripsi singkat gedung"
                                            value={form.data.desc}
                                            onChange={(e) => form.setData('desc', e.target.value)}
                                            error={form.errors.desc}
                                        />
                                    </div>
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                        hint="Mematikan gedung ikut mematikan ruangannya."
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Gedung' : 'Simpan Perubahan'}
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
