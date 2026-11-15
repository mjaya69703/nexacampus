// Form tambah/ubah kategori buku perpustakaan.
import { Head, useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    category: { id?: number; name: string; code: string; description: string; is_active: boolean };
    urls: { index: string; submit: string };
};

export default function BookCategoryForm({ shell, mode, category, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: category.name,
        code: category.code,
        description: category.description,
        is_active: category.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kategori Buku · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Tags}
                        eyebrow="Perpustakaan"
                        title={isCreate ? 'Tambah Kategori Buku' : `Edit ${category.name}`}
                        description="Kode kategori dipakai sebagai pengenal singkat pada laporan koleksi."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Informasi Kategori</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama kategori"
                                        required
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                        placeholder="Contoh: Teknologi Informasi"
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                                        error={form.errors.code}
                                        hint="Huruf kapital, spasi otomatis diubah menjadi garis bawah."
                                        placeholder="TI"
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                            placeholder="Ringkasan cakupan kategori ini…"
                                        />
                                    </div>
                                    <SwitchField
                                        label="Status kategori"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Kategori nonaktif tidak muncul saat menambah buku baru."
                                    />
                                </div>
                            </div>
                        </section>

                        <FormActions
                            cancelHref={urls.index}
                            submitLabel={isCreate ? 'Simpan Kategori' : 'Simpan Perubahan'}
                            processing={form.processing}
                        />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
