// Form tambah/ubah lokasi kampus.
import { Head, useForm } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    location: {
        id?: number; name: string; code: string; address: string; phone: string; email: string;
        latitude: string; longitude: string; radius_meters: number;
        is_main: boolean; is_attendance_point: boolean; is_active: boolean; desc: string;
    };
    urls: { index: string; submit: string };
};

export default function LocationForm({ shell, mode, location, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: location.name,
        code: location.code,
        address: location.address,
        phone: location.phone,
        email: location.email,
        latitude: location.latitude,
        longitude: location.longitude,
        radius_meters: location.radius_meters,
        is_main: location.is_main,
        is_attendance_point: location.is_attendance_point,
        is_active: location.is_active,
        desc: location.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Lokasi Kampus · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MapPin}
                        eyebrow="Kampus"
                        title={isCreate ? 'Tambah Lokasi' : `Edit ${location.name}`}
                        description="Hanya satu lokasi yang menjadi kampus utama — menandai yang baru menggantikan yang lama."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><MapPin size={16} /> Form Lokasi</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama lokasi"
                                        required
                                        placeholder="Contoh: Kampus Utama - Kebon Jeruk"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode lokasi"
                                        required
                                        placeholder="Contoh: KMP-A"
                                        hint="Unik, maks 50 karakter."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Alamat lengkap"
                                            rows={2}
                                            placeholder="Alamat jalan, kota, kode pos…"
                                            value={form.data.address}
                                            onChange={(e) => form.setData('address', e.target.value)}
                                            error={form.errors.address}
                                        />
                                    </div>
                                    <TextField
                                        label="Telepon"
                                        placeholder="021-xxxxxxxx"
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                        error={form.errors.phone}
                                    />
                                    <TextField
                                        label="Email kontak"
                                        type="email"
                                        placeholder="info@kampus.ac.id"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        error={form.errors.email}
                                    />
                                    <TextField
                                        label="Latitude GPS"
                                        type="number"
                                        hint="−90 sampai 90."
                                        placeholder="-6.1234567"
                                        value={form.data.latitude}
                                        onChange={(e) => form.setData('latitude', e.target.value)}
                                        error={form.errors.latitude}
                                    />
                                    <TextField
                                        label="Longitude GPS"
                                        type="number"
                                        hint="−180 sampai 180."
                                        placeholder="106.1234567"
                                        value={form.data.longitude}
                                        onChange={(e) => form.setData('longitude', e.target.value)}
                                        error={form.errors.longitude}
                                    />
                                    <TextField
                                        label="Radius geofence absensi (meter)"
                                        type="number"
                                        min={10}
                                        hint="10 sampai 10000. Dipakai validasi radius absensi pegawai bila titik absensi aktif."
                                        value={String(form.data.radius_meters)}
                                        onChange={(e) => form.setData('radius_meters', e.target.value === '' ? 100 : Number(e.target.value))}
                                        error={form.errors.radius_meters}
                                    />
                                    <SwitchField
                                        label="Kampus utama"
                                        checked={form.data.is_main}
                                        onChange={(v) => form.setData('is_main', v)}
                                        error={form.errors.is_main}
                                        hint="Menggantikan lokasi utama saat ini."
                                    />
                                    <SwitchField
                                        label="Titik absensi pegawai"
                                        checked={form.data.is_attendance_point}
                                        onChange={(v) => form.setData('is_attendance_point', v)}
                                        error={form.errors.is_attendance_point}
                                        hint="Aktifkan agar lokasi dipakai geofence absensi mandiri pegawai."
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            value={form.data.desc}
                                            onChange={(e) => form.setData('desc', e.target.value)}
                                            error={form.errors.desc}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Lokasi' : 'Simpan Perubahan'}
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
