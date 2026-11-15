// Form tambah/ubah ruangan.
import { Head, useForm } from '@inertiajs/react';
import { DoorOpen } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    room: {
        id?: number; building_id: number | null; name: string; code: string;
        floor: string; capacity: string; type: string; is_active: boolean; desc: string;
    };
    buildingOptions: { id: number; name: string }[];
    typeOptions: string[];
    urls: { index: string; submit: string };
};

export default function RoomForm({ shell, mode, room, buildingOptions, typeOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        building_id: room.building_id as number | null,
        name: room.name,
        code: room.code,
        floor: room.floor,
        capacity: room.capacity,
        type: room.type,
        is_active: room.is_active,
        desc: room.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Ruangan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={DoorOpen}
                        eyebrow="Kampus"
                        title={isCreate ? 'Tambah Ruangan' : `Edit ${room.name}`}
                        description="Ruangan aktif dapat dipakai jadwal kuliah. Kapasitas dipakai validasi reservasi."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><DoorOpen size={16} /> Form Ruangan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Gedung"
                                        value={form.data.building_id === null ? '' : String(form.data.building_id)}
                                        onChange={(e) => form.setData('building_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.building_id}
                                        hint="Hanya gedung aktif."
                                    >
                                        <option value="">Tanpa gedung</option>
                                        {buildingOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Nama ruangan"
                                        required
                                        placeholder="Contoh: Ruang 101"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode ruangan"
                                        placeholder="Contoh: R-101"
                                        hint="Opsional, unik bila diisi."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <SelectField
                                        label="Tipe ruangan"
                                        required
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        error={form.errors.type}
                                    >
                                        {typeOptions.map((t) => (
                                            <option key={t} value={t}>{t}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Lantai"
                                        placeholder="Contoh: 1"
                                        value={form.data.floor}
                                        onChange={(e) => form.setData('floor', e.target.value)}
                                        error={form.errors.floor}
                                    />
                                    <TextField
                                        label="Kapasitas (orang)"
                                        type="number"
                                        min={1}
                                        placeholder="Contoh: 30"
                                        value={form.data.capacity}
                                        onChange={(e) => form.setData('capacity', e.target.value)}
                                        error={form.errors.capacity}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            placeholder="Deskripsi ruangan"
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
                                        hint="Ruangan nonaktif disembunyikan dari penjadwalan."
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Ruangan' : 'Simpan Perubahan'}
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
