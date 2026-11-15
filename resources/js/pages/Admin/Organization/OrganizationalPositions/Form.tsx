// Form tambah/ubah master jabatan organisasi.
import { Head, useForm } from '@inertiajs/react';
import { Briefcase } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type PositionPayload = {
    id?: number;
    name: string;
    code: string;
    category: string;
    scope_type: string;
    description: string;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    position: PositionPayload;
    categories: Option[];
    scopes: Option[];
    urls: { index: string; submit: string };
};

export default function OrganizationalPositionForm({ shell, mode, position, categories, scopes, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: position.name,
        code: position.code,
        category: position.category,
        scope_type: position.scope_type,
        description: position.description,
        is_active: position.is_active,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Jabatan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Briefcase}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Jabatan' : `Edit ${position.name}`}
                        description="Tentukan nama, kode unik, kategori, serta cakupan kewenangan (scope) jabatan organisasi."
                        badges={isCreate ? ['Jabatan baru'] : [position.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Briefcase size={16} /> Identitas Jabatan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama jabatan"
                                        required
                                        placeholder="Contoh: Ketua Program Studi"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode jabatan"
                                        required
                                        placeholder="Contoh: KAPRODI"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Kode unik (huruf kapital) untuk identifikasi sistem."
                                    />
                                    <SelectField
                                        label="Kategori jabatan"
                                        required
                                        value={form.data.category}
                                        onChange={(event) => form.setData('category', event.target.value)}
                                        error={form.errors.category}
                                    >
                                        {categories.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Cakupan kewenangan (scope)"
                                        required
                                        value={form.data.scope_type}
                                        onChange={(event) => form.setData('scope_type', event.target.value)}
                                        error={form.errors.scope_type}
                                        hint="Menentukan entitas yang harus dipilih saat menugaskan pegawai ke jabatan ini."
                                    >
                                        {scopes.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi & uraian tugas"
                                            rows={3}
                                            placeholder="Tuliskan kewenangan, tanggung jawab pokok, atau landasan SK jabatan…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                    <SwitchField
                                        label="Status jabatan aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Jabatan nonaktif tidak muncul pada pilihan penugasan baru."
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Jabatan' : 'Simpan Perubahan'}
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
