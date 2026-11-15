// Form tambah/ubah kategori pengaduan mahasiswa.
import { Head, useForm } from '@inertiajs/react';
import { MessageSquareWarning } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type CategoryPayload = {
    id?: number;
    name: string;
    code: string;
    default_work_unit_id: string | number;
    description: string;
    default_sla_hours: number;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    category: CategoryPayload;
    workUnits: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function ComplaintCategoryForm({ shell, mode, category, workUnits, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: category.name,
        code: category.code,
        default_work_unit_id: String(category.default_work_unit_id ?? ''),
        description: category.description,
        default_sla_hours: category.default_sla_hours,
        is_active: category.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kategori Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MessageSquareWarning}
                        eyebrow="Layanan Mahasiswa · Pengaduan"
                        title={isCreate ? 'Tambah Kategori Pengaduan' : `Edit ${category.name}`}
                        description="Kategori menentukan unit kerja penanggung jawab awal dan target waktu penyelesaian pengaduan."
                        badges={isCreate ? ['Kategori baru'] : [category.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><MessageSquareWarning size={16} /> Identitas Kategori</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama kategori"
                                        required
                                        placeholder="Contoh: Layanan Akademik"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode kategori"
                                        required
                                        placeholder="Contoh: AKADEMIK"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Otomatis disimpan dalam huruf kapital."
                                    />
                                    <SelectField
                                        label="Unit kerja penanggung jawab"
                                        value={form.data.default_work_unit_id}
                                        onChange={(event) => form.setData('default_work_unit_id', event.target.value)}
                                        error={form.errors.default_work_unit_id}
                                        hint="Pengaduan baru otomatis diteruskan ke unit kerja ini."
                                    >
                                        <option value="">Belum ditentukan</option>
                                        {workUnits.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Target SLA (jam)"
                                        type="number"
                                        required
                                        min={1}
                                        max={720}
                                        value={form.data.default_sla_hours}
                                        onChange={(event) => form.setData('default_sla_hours', Number(event.target.value))}
                                        error={form.errors.default_sla_hours}
                                        hint="Batas waktu penyelesaian yang ditampilkan pada tiket."
                                    />
                                    <SwitchField
                                        label="Aktifkan kategori"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Kategori nonaktif tidak muncul saat mahasiswa membuat pengaduan."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            rows={3}
                                            placeholder="Jelaskan cakupan kategori ini…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Kategori' : 'Simpan Perubahan'}
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
