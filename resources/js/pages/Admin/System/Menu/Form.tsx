// Form tambah/ubah menu sidebar.
import { Head, useForm } from '@inertiajs/react';
import { Menu as MenuIcon } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FaIcon } from '../../../../components/Shared/FaIcon';
import { FormActions, SelectField, SwitchField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    menu: {
        id?: number; type: string; parent_id: number | string; title: string;
        route_name: string; url: string; icon: string; permission_name: string;
        sort_order: number; is_active: boolean;
    };
    parents: { id: number | string; name: string }[];
    urls: { index: string; submit: string };
};

export default function MenuForm({ shell, mode, menu, parents, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        type: menu.type,
        parent_id: menu.parent_id as number | string,
        title: menu.title,
        route_name: menu.route_name,
        url: menu.url,
        icon: menu.icon,
        permission_name: menu.permission_name,
        sort_order: menu.sort_order,
        is_active: menu.is_active,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Menu · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={MenuIcon}
                        eyebrow="Sistem"
                        title={isCreate ? 'Tambah Menu' : `Edit ${menu.title}`}
                        description="Tipe grup hanya wadah; tipe tautan butuh route atau URL plus permission penjaga."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><MenuIcon size={16} /> Form Menu</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Judul menu"
                                        required
                                        placeholder="Contoh: Daftar Pengguna"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        error={form.errors.title}
                                    />
                                    <SelectField
                                        label="Tipe"
                                        required
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        error={form.errors.type}
                                        hint="Grup = wadah tanpa tautan. Tautan = menu yang bisa diklik."
                                    >
                                        <option value="link">Tautan</option>
                                        <option value="group">Grup</option>
                                    </SelectField>
                                    <SelectField
                                        label="Parent"
                                        value={form.data.parent_id}
                                        onChange={(e) => form.setData('parent_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.parent_id}
                                        hint="Hierarki ditandai indentasi —."
                                    >
                                        {parents.map((parent) => (
                                            <option key={String(parent.id)} value={parent.id}>{parent.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Urutan"
                                        required
                                        type="number"
                                        value={form.data.sort_order}
                                        onChange={(e) => form.setData('sort_order', e.target.value === '' ? 0 : Number(e.target.value))}
                                        error={form.errors.sort_order}
                                    />
                                    <TextField
                                        label="Nama route"
                                        placeholder="Contoh: admin.access.users.index"
                                        hint="Diutamakan daripada URL bila keduanya diisi."
                                        value={form.data.route_name}
                                        onChange={(e) => form.setData('route_name', e.target.value)}
                                        error={form.errors.route_name}
                                    />
                                    <TextField
                                        label="URL"
                                        placeholder="Contoh: /admin/access/users"
                                        value={form.data.url}
                                        onChange={(e) => form.setData('url', e.target.value)}
                                        error={form.errors.url}
                                    />
                                    <div>
                                        <TextField
                                            label="Ikon"
                                            placeholder="Contoh: fas fa-users"
                                            hint="Pratinjau di samping mengikuti pemetaan lucide."
                                            value={form.data.icon}
                                            onChange={(e) => form.setData('icon', e.target.value)}
                                            error={form.errors.icon}
                                        />
                                    </div>
                                    <div>
                                        <span className="crud-label">Pratinjau ikon</span>
                                        <span className="db-stat-icon" style={{ width: 42, height: 42 }}>
                                            <FaIcon name={form.data.icon || null} size={20} />
                                        </span>
                                    </div>
                                    <TextField
                                        label="Nama permission"
                                        placeholder="Contoh: user.viewAny"
                                        hint="Dikosongkan berarti selalu tampil."
                                        value={form.data.permission_name}
                                        onChange={(e) => form.setData('permission_name', e.target.value)}
                                        error={form.errors.permission_name}
                                    />
                                    <SwitchField
                                        label="Tampil di sidebar"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Menu' : 'Simpan Perubahan'}
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
