// Form tambah/ubah tahun akademik.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    year: {
        id?: number; name: string; code: string; semester: string;
        start_date: string; end_date: string; is_active: boolean; desc: string;
    };
    urls: { index: string; submit: string };
};

export default function AcademicYearForm({ shell, mode, year, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        name: year.name,
        code: year.code,
        semester: year.semester,
        start_date: year.start_date,
        end_date: year.end_date,
        is_active: year.is_active,
        desc: year.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Tahun Akademik · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Tahun Akademik' : `Edit ${year.name}`}
                        description="Mengaktifkan tahun ini otomatis menonaktifkan tahun lainnya."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Form Tahun Akademik</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama tahun"
                                        required
                                        placeholder="Contoh: 2026/2027 Ganjil"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        placeholder="Contoh: 2627G"
                                        hint="Unik."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <SelectField
                                        label="Semester"
                                        required
                                        value={form.data.semester}
                                        onChange={(e) => form.setData('semester', e.target.value)}
                                        error={form.errors.semester}
                                    >
                                        <option value="Ganjil">Ganjil</option>
                                        <option value="Genap">Genap</option>
                                        <option value="Pendek">Pendek</option>
                                    </SelectField>
                                    <SwitchField
                                        label="Aktifkan tahun ini"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                        hint="Mengaktifkan ini menonaktifkan tahun lain."
                                    />
                                    <TextField
                                        label="Tanggal mulai"
                                        required
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(e) => form.setData('start_date', e.target.value)}
                                        error={form.errors.start_date}
                                    />
                                    <TextField
                                        label="Tanggal selesai"
                                        required
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                        error={form.errors.end_date}
                                        hint="Minimal sama dengan tanggal mulai."
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
                                    submitLabel={isCreate ? 'Simpan Tahun' : 'Simpan Perubahan'}
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
