// Form tambah/ubah peran — dipakai mode create & edit.
import { Head, useForm } from '@inertiajs/react';
import { UserCog } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { Checklist, ChecklistGroup } from '../../../../components/Shared/Crud/Checklist';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    role: { id?: number; name: string; guard_name: string; permission_ids: (number | string)[] };
    groups: ChecklistGroup[];
    urls: { index: string; submit: string };
};

export default function RoleForm({ shell, mode, role, groups, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: role.name,
        guard_name: role.guard_name,
        permission_ids: role.permission_ids as number[],
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Peran · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={UserCog}
                        eyebrow="Manajemen akses"
                        title={isCreate ? 'Tambah Peran' : `Edit ${role.name}`}
                        description="Tentukan nama dan guard peran, lalu centang permission yang dimiliki. Perubahan langsung berlaku untuk seluruh user ber-peran ini."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><UserCog size={16} /> Form Peran</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama peran"
                                        required
                                        placeholder="Contoh: operator-pmb"
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
                                    <span className="crud-label">Permission yang dimiliki</span>
                                    <Checklist
                                        groups={groups}
                                        selected={form.data.permission_ids}
                                        onChange={(ids) => form.setData('permission_ids', ids as number[])}
                                        placeholder="Cari permission…"
                                        hint="Centang per grup atau per permission. ID yang dikirim adalah ID asli database."
                                        error={form.errors.permission_ids}
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Peran' : 'Simpan Perubahan'}
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
