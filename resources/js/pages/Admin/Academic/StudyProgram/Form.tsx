// Form tambah/ubah program studi.
import { Head, useForm } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    program: {
        id?: number; faculty_id: number | string; name: string; code: string;
        short_name: string; degree: string; prefix_degree: string; suffix_degree: string;
        is_active: boolean; desc: string;
    };
    faculties: { id: number | string; name: string }[];
    urls: { index: string; submit: string };
};

const DEGREES = ['D3', 'D4', 'S1', 'S2', 'S3'];

export default function StudyProgramForm({ shell, mode, program, faculties, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        faculty_id: program.faculty_id,
        name: program.name,
        code: program.code,
        short_name: program.short_name,
        degree: program.degree,
        prefix_degree: program.prefix_degree,
        suffix_degree: program.suffix_degree,
        is_active: program.is_active,
        desc: program.desc,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Program Studi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Program Studi' : `Edit ${program.name}`}
                        description="Prodi aktif wajib terhubung ke fakultas yang aktif."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Form Program Studi</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Fakultas"
                                        value={form.data.faculty_id}
                                        onChange={(e) => form.setData('faculty_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.faculty_id}
                                        hint="Prodi aktif harus berfakultas aktif."
                                    >
                                        {faculties.map((faculty) => (
                                            <option key={String(faculty.id)} value={faculty.id}>{faculty.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Jenjang"
                                        required
                                        value={form.data.degree}
                                        onChange={(e) => form.setData('degree', e.target.value)}
                                        error={form.errors.degree}
                                    >
                                        {DEGREES.map((degree) => <option key={degree} value={degree}>{degree}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Nama program studi"
                                        required
                                        placeholder="Contoh: Teknik Informatika"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        placeholder="Contoh: TI"
                                        hint="Unik, maks 20 karakter."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Nama singkat"
                                        value={form.data.short_name}
                                        onChange={(e) => form.setData('short_name', e.target.value)}
                                        error={form.errors.short_name}
                                    />
                                    <div className="crud-grid" style={{ gridColumn: '1 / -1', gridTemplateColumns: '1fr 1fr' }}>
                                        <TextField
                                            label="Gelar depan"
                                            placeholder="Contoh: Dr."
                                            value={form.data.prefix_degree}
                                            onChange={(e) => form.setData('prefix_degree', e.target.value)}
                                            error={form.errors.prefix_degree}
                                        />
                                        <TextField
                                            label="Gelar belakang"
                                            placeholder="Contoh: S.T., M.T."
                                            value={form.data.suffix_degree}
                                            onChange={(e) => form.setData('suffix_degree', e.target.value)}
                                            error={form.errors.suffix_degree}
                                        />
                                    </div>
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
                                    submitLabel={isCreate ? 'Simpan Prodi' : 'Simpan Perubahan'}
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
