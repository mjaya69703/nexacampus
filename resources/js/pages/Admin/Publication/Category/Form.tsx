// Form tambah/ubah kategori publikasi.
import { Head, useForm } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    category: { id?: number; name: string; slug: string; is_active: boolean; sort_order: number; desc: string };
    urls: { index: string; submit: string };
};

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-');
}

export default function CategoryForm({ shell, mode, category, urls }: Props) {
    const isCreate = mode === 'create';
    const [slugTouched, setSlugTouched] = useState(!isCreate);
    const form = useForm({
        name: category.name,
        slug: category.slug,
        sort_order: category.sort_order,
        is_active: category.is_active,
        desc: category.desc,
    });

    const onNameChange = (value: string) => {
        form.setData('name', value);
        if (!slugTouched) {
            form.setData('slug', slugify(value));
        }
    };

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kategori Publikasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Tags}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Tambah Kategori' : `Edit ${category.name}`}
                        description="Kategori dipakai berita, agenda, dan galeri. Slug terisi otomatis dari nama dan bisa diubah manual."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Tags size={16} /> Form Kategori</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama kategori"
                                        required
                                        placeholder="Contoh: Berita Kampus"
                                        value={form.data.name}
                                        onChange={(e) => onNameChange(e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Slug"
                                        placeholder="auto-dari-nama"
                                        hint="Terisi otomatis dari nama. Ubah manual bila perlu."
                                        value={form.data.slug}
                                        onChange={(e) => {
                                            setSlugTouched(true);
                                            form.setData('slug', e.target.value);
                                        }}
                                        error={form.errors.slug}
                                    />
                                    <TextField
                                        label="Urutan tampil"
                                        type="number"
                                        min={0}
                                        hint="Terkecil (0, 1, 2) tampil lebih atas."
                                        value={String(form.data.sort_order)}
                                        onChange={(e) => form.setData('sort_order', e.target.value === '' ? 0 : Number(e.target.value))}
                                        error={form.errors.sort_order}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                        hint="Kategori nonaktif disembunyikan dari publik."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            placeholder="Deskripsi singkat tentang kategori ini…"
                                            value={form.data.desc}
                                            onChange={(e) => form.setData('desc', e.target.value)}
                                            error={form.errors.desc}
                                            hint="Maks 500 karakter."
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
