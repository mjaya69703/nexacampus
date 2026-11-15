// Form tambah/ubah fakultas.
import { Head, useForm } from '@inertiajs/react';
import { University } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    faculty: { id?: number; name: string; code: string; short_name: string; is_active: boolean; desc: string };
    urls: { index: string; submit: string };
};

export default function FacultyForm({ shell, mode, faculty, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: faculty.name,
        code: faculty.code,
        short_name: faculty.short_name,
        is_active: faculty.is_active,
        desc: faculty.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Fakultas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={University}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Fakultas' : `Edit ${faculty.name}`}
                        description="Menonaktifkan fakultas ikut menonaktifkan seluruh program studinya."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><University size={16} /> Form Fakultas</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama fakultas"
                                        required
                                        placeholder="Contoh: Fakultas Teknik"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        placeholder="Contoh: FT"
                                        hint="Unik, maks 20 karakter."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Nama singkat"
                                        placeholder="Contoh: FTEK"
                                        value={form.data.short_name}
                                        onChange={(e) => form.setData('short_name', e.target.value)}
                                        error={form.errors.short_name}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                        hint="Mematikan fakultas ikut mematikan prodinya."
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
                                    submitLabel={isCreate ? 'Simpan Fakultas' : 'Simpan Perubahan'}
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
