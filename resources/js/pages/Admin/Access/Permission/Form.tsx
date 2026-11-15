// Form tambah/ubah permission — dipakai mode create & edit.
import { Head, useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { Checklist } from '../../../../components/Shared/Crud/Checklist';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    permission: { id?: number; name: string; guard_name: string; role_ids: (number | string)[] };
    roles: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function PermissionForm({ shell, mode, permission, roles, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: permission.name,
        guard_name: permission.guard_name,
        role_ids: permission.role_ids as number[],
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Permission · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={KeyRound}
                        eyebrow="Manajemen akses"
                        title={isCreate ? 'Tambah Permission' : `Edit ${permission.name}`}
                        description="Gunakan format resource.action yang konsisten, tentukan guard, lalu pasangkan ke role yang relevan."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><KeyRound size={16} /> Form Permission</h2>
                            <a className="db-btn ghost sm" href={urls.index}>Kembali ke daftar</a>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama permission"
                                        required
                                        placeholder="Contoh: user.viewAny"
                                        hint="Format umum: resource.action"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <SelectField
                                        label="Guard name"
                                        required
                                        value={form.data.guard_name}
                                        onChange={(e) => form.setData('guard_name', e.target.value)}
                                        error={form.errors.guard_name}
                                    >
                                        <option value="web">web</option>
                                        <option value="api">api</option>
                                    </SelectField>
                                </div>

                                <div style={{ marginTop: 14 }}>
                                    <span className="crud-label">Assign ke role</span>
                                    <Checklist
                                        options={roles.map((role) => ({ id: role.id, label: role.name }))}
                                        selected={form.data.role_ids}
                                        onChange={(ids) => form.setData('role_ids', ids as number[])}
                                        placeholder="Cari role…"
                                        hint="Pilih role yang akan otomatis mendapatkan permission ini."
                                        error={form.errors.role_ids}
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Permission' : 'Simpan Perubahan'}
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
